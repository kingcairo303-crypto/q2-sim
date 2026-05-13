<?php
declare(strict_types=1);

namespace App\Game;

use App\Entity\Character;
use App\Repository\CharacterRepository;
use DateTime;
use Exception;

/**
 * Combat engine: turn-based, deterministic, with crit mechanics.
 * Server-authoritative only, all calculations validated.
 */
final class CombatEngine
{
    private CharacterRepository $characterRepository;
    private WeightedRandom $random;
    private \mysqli $connection;

    public function __construct(
        CharacterRepository $characterRepository,
        WeightedRandom $random,
        \mysqli $connection
    ) {
        $this->characterRepository = $characterRepository;
        $this->random = $random;
        $this->connection = $connection;
    }

    /**
     * Execute attack with damage and crit calculation.
     *
     * @throws Exception
     */
    public function attack(
        Character $attacker,
        Character $defender,
        string $nonce,
        int $baseSkillLevel = 50
    ): array {
        // Verify action cooldown
        if (!$attacker->canPerformAction(5)) {
            throw new Exception('Action cooldown not elapsed');
        }

        // Damage formula: base_damage * (1 + skill_modifier) + random
        $baseDamage = 20;
        $skillModifier = ($baseSkillLevel / 100.0);
        $damageVariance = $this->random->randomInt(5, 15, $nonce, $attacker->getId());

        $totalDamage = intval($baseDamage * (1 + $skillModifier)) + $damageVariance;

        // Crit calculation using weighted array
        $critWeights = [80, 20]; // 80% non-crit, 20% crit
        $critResult = $this->random->selectWeighted($critWeights, $nonce . '_crit', $attacker->getId());
        $isCritical = $critResult === 1;

        if ($isCritical) {
            $totalDamage = intval($totalDamage * 1.5); // 50% crit multiplier
        }

        // Cap damage to defender's health
        $actualDamage = min($totalDamage, $defender->getHealth());

        // Update defender
        $damagedDefender = $defender->takeDamage($actualDamage);
        $this->characterRepository->save($damagedDefender);

        // Log combat action (for audit trail)
        $this->logCombat(
            $attacker->getId(),
            $defender->getId(),
            $totalDamage,
            $actualDamage,
            $isCritical,
            $nonce
        );

        // Award XP
        $xpGain = $isCritical ? 25 : 15;
        $updatedAttacker = $attacker->gainExperience($xpGain);
        $this->characterRepository->save($updatedAttacker);

        return [
            'success' => true,
            'damage_dealt' => $actualDamage,
            'damage_calculated' => $totalDamage,
            'is_critical' => $isCritical,
            'defender_health_remaining' => max(0, $defender->getHealth() - $actualDamage),
            'attacker_xp_gained' => $xpGain,
            'defender_defeated' => $damagedDefender->isDead(),
        ];
    }

    /**
     * Log combat action for auditing and reproducibility.
     *
     * @throws Exception
     */
    private function logCombat(
        int $attackerId,
        int $defenderId,
        int $damageCalculated,
        int $damageActual,
        bool $isCritical,
        string $nonce
    ): void {
        $stmt = $this->connection->prepare(
            'INSERT INTO combat_log 
             (attacker_id, defender_id, damage_dealt, is_critical, action_nonce, result, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );

        if (!$stmt) {
            throw new Exception('Failed to prepare combat log: ' . $this->connection->error);
        }

        $result = $isCritical ? 'crit' : 'normal';
        $stmt->bind_param('iiiiss', $attackerId, $defenderId, $damageActual, $isCritical, $nonce, $result);

        if (!$stmt->execute()) {
            throw new Exception('Failed to log combat: ' . $stmt->error);
        }

        $stmt->close();
    }

    /**
     * Execute NPC encounter (server-controlled opponent).
     *
     * @throws Exception
     */
    public function encounterNpc(
        Character $player,
        string $npcName,
        int $npcLevel,
        string $nonce
    ): array {
        // Simulate NPC stats
        $npcSkillLevel = 30 + ($npcLevel * 5);

        // Player attacks first
        $playerAttack = $this->calculateDamage(
            $player->getLevel(),
            $npcLevel,
            $nonce . '_p1'
        );

        // NPC counter-attacks
        $npcAttack = $this->calculateDamage(
            $npcLevel,
            $player->getLevel(),
            $nonce . '_n1'
        );

        $playerHealth = max(0, $player->getHealth() - $npcAttack);
        $playerDefeated = $playerHealth <= 0;

        // Award or penalty
        if (!$playerDefeated) {
            $xpGain = 30 + ($npcLevel * 5);
            $questReward = 50 + ($npcLevel * 10);
            $updatedPlayer = $player->gainExperience($xpGain)->addQubits($questReward);
            $this->characterRepository->save($updatedPlayer);

            return [
                'victory' => true,
                'player_damage_dealt' => $playerAttack,
                'npc_damage_taken' => $npcAttack,
                'xp_gained' => $xpGain,
                'qubits_gained' => $questReward,
            ];
        }

        // Defeat: lose qubits
        $penalty = intval($player->getQubits() * 0.1); // 10% loss
        $penalizedPlayer = $player->addQubits(-$penalty)->heal(50);
        $this->characterRepository->save($penalizedPlayer);

        return [
            'victory' => false,
            'player_damage_dealt' => $playerAttack,
            'npc_damage_taken' => $npcAttack,
            'qubits_lost' => $penalty,
            'player_defeated' => true,
        ];
    }

    /**
     * Calculate damage with nonlinear scaling and soft caps.
     */
    private function calculateDamage(
        int $attackerLevel,
        int $defenderLevel,
        string $seed
    ): int {
        // Logarithmic scaling: base * log(level + 1)
        $baseDamage = 20 * log($attackerLevel + 1);
        $defenseModifier = 1.0 - (($defenderLevel / 100.0) * 0.3); // Up to 30% mitigation

        // Apply soft cap (diminishing returns above level 50)
        if ($attackerLevel > 50) {
            $excess = $attackerLevel - 50;
            $baseDamage += (log($excess + 1) * 2); // Reduced slope
        }

        $totalDamage = intval($baseDamage * $defenseModifier);
        return max(5, $totalDamage); // Minimum 5 damage
    }
}

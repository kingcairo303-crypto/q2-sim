<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Character;
use App\Repository\CharacterRepository;
use App\Game\CombatEngine;
use App\Game\WeightedRandom;
use Exception;

/**
 * PlayerService: Business logic orchestration.
 * Coordinates multiple services and repositories.
 */
final class PlayerService
{
    private CharacterRepository $characterRepository;
    private CombatEngine $combatEngine;
    private WeightedRandom $random;

    public function __construct(
        CharacterRepository $characterRepository,
        CombatEngine $combatEngine,
        WeightedRandom $random
    ) {
        $this->characterRepository = $characterRepository;
        $this->combatEngine = $combatEngine;
        $this->random = $random;
    }

    /**
     * Execute crime action: deterministic RNG, weighted success.
     */
    public function performCrime(Character $player, string $nonce): array
    {
        if (!$player->canPerformAction(5)) {
            throw new Exception('Action cooldown not elapsed');
        }

        // Weighted success array: [fail, success]
        $weights = [40, 60];
        $success = $this->random->selectWeighted($weights, $nonce . '_crime', $player->getId()) === 1;

        $reward = $success
            ? 30 + $this->random->randomInt(0, 50, $nonce . '_reward', $player->getId())
            : -15;
        $xpGain = $success ? 12 : 3;

        $updated = $player->addQubits($reward)->gainExperience($xpGain);
        $this->characterRepository->save($updated);

        return [
            'success' => $success,
            'reward_qubits' => $reward,
            'xp_gained' => $xpGain,
            'level' => $updated->getLevel(),
        ];
    }

    /**
     * Execute heist action: high risk, high reward.
     */
    public function performHeist(Character $player, string $nonce): array
    {
        if (!$player->canPerformAction(10)) {
            throw new Exception('Action cooldown not elapsed');
        }

        // Heist: 30% success rate
        $weights = [70, 30];
        $success = $this->random->selectWeighted($weights, $nonce . '_heist', $player->getId()) === 1;

        $reward = $success
            ? 350 + $this->random->randomInt(0, 200, $nonce . '_heist_reward', $player->getId())
            : -80;
        $xpGain = $success ? 45 : 10;

        $updated = $player->addQubits($reward)->gainExperience($xpGain);
        $this->characterRepository->save($updated);

        return [
            'success' => $success,
            'reward_qubits' => $reward,
            'xp_gained' => $xpGain,
            'level' => $updated->getLevel(),
        ];
    }
}

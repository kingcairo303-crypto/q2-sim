<?php
declare(strict_types=1);

namespace App\Entity;

use DateTime;

/**
 * Character: Game entity with stats, progression, and state.
 * Immutable value object using factory pattern.
 */
final class Character
{
    private int $id;
    private int $userId;
    private string $name;
    private int $level;
    private int $experience;
    private int $health;
    private int $maxHealth;
    private int $qubits;
    private int $crimeSkill;
    private int $combatSkill;
    private int $hackingSkill;
    private int $districtId;
    private bool $alive;
    private DateTime $createdAt;
    private DateTime $lastActionAt;

    public function __construct(
        int $id,
        int $userId,
        string $name,
        int $level,
        int $experience,
        int $health,
        int $maxHealth,
        int $qubits,
        int $crimeSkill,
        int $combatSkill,
        int $hackingSkill,
        int $districtId,
        bool $alive,
        DateTime $createdAt,
        DateTime $lastActionAt
    ) {
        $this->id = $id;
        $this->userId = $userId;
        $this->name = $name;
        $this->level = $level;
        $this->experience = $experience;
        $this->health = $health;
        $this->maxHealth = $maxHealth;
        $this->qubits = $qubits;
        $this->crimeSkill = $crimeSkill;
        $this->combatSkill = $combatSkill;
        $this->hackingSkill = $hackingSkill;
        $this->districtId = $districtId;
        $this->alive = $alive;
        $this->createdAt = $createdAt;
        $this->lastActionAt = $lastActionAt;
    }

    public static function createNew(
        int $userId,
        string $name,
        int $startingDistrict = 13
    ): self {
        return new self(
            id: 0,
            userId: $userId,
            name: $name,
            level: 1,
            experience: 0,
            health: 100,
            maxHealth: 100,
            qubits: 420,
            crimeSkill: 20,
            combatSkill: 15,
            hackingSkill: 12,
            districtId: $startingDistrict,
            alive: true,
            createdAt: new DateTime(),
            lastActionAt: new DateTime()
        );
    }

    public function getId(): int { return $this->id; }
    public function getUserId(): int { return $this->userId; }
    public function getName(): string { return $this->name; }
    public function getLevel(): int { return $this->level; }
    public function getExperience(): int { return $this->experience; }
    public function getHealth(): int { return $this->health; }
    public function getMaxHealth(): int { return $this->maxHealth; }
    public function getQubits(): int { return $this->qubits; }
    public function getCrimeSkill(): int { return $this->crimeSkill; }
    public function getCombatSkill(): int { return $this->combatSkill; }
    public function getHackingSkill(): int { return $this->hackingSkill; }
    public function getDistrictId(): int { return $this->districtId; }
    public function isAlive(): bool { return $this->alive; }
    public function isDead(): bool { return !$this->alive; }
    public function getCreatedAt(): DateTime { return $this->createdAt; }
    public function getLastActionAt(): DateTime { return $this->lastActionAt; }

    public function takeDamage(int $damage): self
    {
        $newHealth = max(0, $this->health - $damage);
        $isAlive = $newHealth > 0;
        return new self(
            $this->id,
            $this->userId,
            $this->name,
            $this->level,
            $this->experience,
            $newHealth,
            $this->maxHealth,
            $this->qubits,
            $this->crimeSkill,
            $this->combatSkill,
            $this->hackingSkill,
            $this->districtId,
            $isAlive,
            $this->createdAt,
            new DateTime()
        );
    }

    public function heal(int $amount): self
    {
        $newHealth = min($this->maxHealth, $this->health + $amount);
        return new self(
            $this->id,
            $this->userId,
            $this->name,
            $this->level,
            $this->experience,
            $newHealth,
            $this->maxHealth,
            $this->qubits,
            $this->crimeSkill,
            $this->combatSkill,
            $this->hackingSkill,
            $this->districtId,
            $this->alive,
            $this->createdAt,
            new DateTime()
        );
    }

    public function addQubits(int $amount): self
    {
        $newQubits = max(0, $this->qubits + $amount);
        return new self(
            $this->id,
            $this->userId,
            $this->name,
            $this->level,
            $this->experience,
            $this->health,
            $this->maxHealth,
            $newQubits,
            $this->crimeSkill,
            $this->combatSkill,
            $this->hackingSkill,
            $this->districtId,
            $this->alive,
            $this->createdAt,
            new DateTime()
        );
    }

    public function gainExperience(int $xp): self
    {
        $newXp = $this->experience + $xp;
        $newLevel = $this->level;
        $remainingXp = $newXp;
        $crimeSkill = $this->crimeSkill;
        $combatSkill = $this->combatSkill;
        $hackingSkill = $this->hackingSkill;

        while ($remainingXp >= 100 * $newLevel) {
            $remainingXp -= 100 * $newLevel;
            $newLevel++;
            $crimeSkill += 5;
            $combatSkill += 4;
            $hackingSkill += 5;
        }

        return new self(
            $this->id,
            $this->userId,
            $this->name,
            $newLevel,
            $remainingXp,
            $this->health,
            $this->maxHealth,
            $this->qubits,
            $crimeSkill,
            $combatSkill,
            $hackingSkill,
            $this->districtId,
            $this->alive,
            $this->createdAt,
            new DateTime()
        );
    }

    public function travelToDistrict(int $districtId): self
    {
        if ($this->qubits < 4) {
            throw new \InvalidArgumentException('Insufficient qubits to travel');
        }
        return new self(
            $this->id,
            $this->userId,
            $this->name,
            $this->level,
            $this->experience,
            $this->health,
            $this->maxHealth,
            $this->qubits - 4,
            $this->crimeSkill,
            $this->combatSkill,
            $this->hackingSkill,
            $districtId,
            $this->alive,
            $this->createdAt,
            new DateTime()
        );
    }

    public function canPerformAction(int $cooldownSeconds = 5): bool
    {
        $now = new DateTime();
        $lastAction = $this->lastActionAt;
        $interval = $now->getTimestamp() - $lastAction->getTimestamp();
        return $interval >= $cooldownSeconds;
    }
}

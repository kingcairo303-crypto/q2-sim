<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\Character;
use DateTime;
use Exception;

/**
 * CharacterRepository: Data access layer with prepared statements only.
 * SOLID principle: Single Responsibility (DB access)
 */
final class CharacterRepository
{
    private \mysqli $connection;

    public function __construct(\mysqli $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Find character by ID.
     */
    public function findById(int $id): ?Character
    {
        $stmt = $this->connection->prepare(
            'SELECT id, user_id, name, level, experience, health, max_health, qubits, ' .
            'crime_skill, combat_skill, hacking_skill, district_id, alive, created_at, last_action_at ' .
            'FROM characters WHERE id = ?'
        );

        if (!$stmt) {
            throw new Exception('Failed to prepare statement: ' . $this->connection->error);
        }

        $stmt->bind_param('i', $id);
        if (!$stmt->execute()) {
            throw new Exception('Failed to execute: ' . $stmt->error);
        }

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        return $row ? $this->hydrate($row) : null;
    }

    /**
     * Find character by user ID.
     */
    public function findByUserId(int $userId): ?Character
    {
        $stmt = $this->connection->prepare(
            'SELECT id, user_id, name, level, experience, health, max_health, qubits, ' .
            'crime_skill, combat_skill, hacking_skill, district_id, alive, created_at, last_action_at ' .
            'FROM characters WHERE user_id = ? LIMIT 1'
        );

        if (!$stmt) {
            throw new Exception('Failed to prepare statement: ' . $this->connection->error);
        }

        $stmt->bind_param('i', $userId);
        if (!$stmt->execute()) {
            throw new Exception('Failed to execute: ' . $stmt->error);
        }

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        return $row ? $this->hydrate($row) : null;
    }

    /**
     * Find character by name (case-insensitive).
     */
    public function findByName(string $name): ?Character
    {
        $stmt = $this->connection->prepare(
            'SELECT id, user_id, name, level, experience, health, max_health, qubits, ' .
            'crime_skill, combat_skill, hacking_skill, district_id, alive, created_at, last_action_at ' .
            'FROM characters WHERE LOWER(name) = LOWER(?) LIMIT 1'
        );

        if (!$stmt) {
            throw new Exception('Failed to prepare statement: ' . $this->connection->error);
        }

        $stmt->bind_param('s', $name);
        if (!$stmt->execute()) {
            throw new Exception('Failed to execute: ' . $stmt->error);
        }

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        return $row ? $this->hydrate($row) : null;
    }

    /**
     * Get top characters by level (leaderboard).
     */
    public function getTopByLevel(int $limit = 10): array
    {
        $stmt = $this->connection->prepare(
            'SELECT id, user_id, name, level, experience, health, max_health, qubits, ' .
            'crime_skill, combat_skill, hacking_skill, district_id, alive, created_at, last_action_at ' .
            'FROM characters WHERE alive = 1 ORDER BY level DESC LIMIT ?'
        );

        if (!$stmt) {
            throw new Exception('Failed to prepare statement: ' . $this->connection->error);
        }

        $stmt->bind_param('i', $limit);
        if (!$stmt->execute()) {
            throw new Exception('Failed to execute: ' . $stmt->error);
        }

        $result = $stmt->get_result();
        $characters = [];
        while ($row = $result->fetch_assoc()) {
            $characters[] = $this->hydrate($row);
        }
        $stmt->close();

        return $characters;
    }

    /**
     * Save character (insert or update).
     */
    public function save(Character $character): void
    {
        $id = $character->getId();
        $userId = $character->getUserId();
        $name = $character->getName();
        $level = $character->getLevel();
        $experience = $character->getExperience();
        $health = $character->getHealth();
        $maxHealth = $character->getMaxHealth();
        $qubits = $character->getQubits();
        $crimeSkill = $character->getCrimeSkill();
        $combatSkill = $character->getCombatSkill();
        $hackingSkill = $character->getHackingSkill();
        $districtId = $character->getDistrictId();
        $alive = $character->isAlive() ? 1 : 0;
        $createdAt = $character->getCreatedAt()->format('Y-m-d H:i:s');
        $lastActionAt = $character->getLastActionAt()->format('Y-m-d H:i:s');

        if ($id === 0) {
            // Insert
            $stmt = $this->connection->prepare(
                'INSERT INTO characters (user_id, name, level, experience, health, max_health, qubits, ' .
                'crime_skill, combat_skill, hacking_skill, district_id, alive, created_at, last_action_at) ' .
                'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            if (!$stmt) {
                throw new Exception('Failed to prepare insert: ' . $this->connection->error);
            }
            $stmt->bind_param(
                'isiiiiiiiiisss',
                $userId, $name, $level, $experience, $health, $maxHealth, $qubits,
                $crimeSkill, $combatSkill, $hackingSkill, $districtId, $alive,
                $createdAt, $lastActionAt
            );
        } else {
            // Update
            $stmt = $this->connection->prepare(
                'UPDATE characters SET user_id=?, name=?, level=?, experience=?, health=?, max_health=?, ' .
                'qubits=?, crime_skill=?, combat_skill=?, hacking_skill=?, district_id=?, alive=?, ' .
                'created_at=?, last_action_at=? WHERE id=?'
            );
            if (!$stmt) {
                throw new Exception('Failed to prepare update: ' . $this->connection->error);
            }
            $stmt->bind_param(
                'isiiiiiiiiissi',
                $userId, $name, $level, $experience, $health, $maxHealth, $qubits,
                $crimeSkill, $combatSkill, $hackingSkill, $districtId, $alive,
                $createdAt, $lastActionAt, $id
            );
        }

        if (!$stmt->execute()) {
            throw new Exception('Failed to execute save: ' . $stmt->error);
        }
        $stmt->close();
    }

    /**
     * Hydrate Character from database row.
     */
    private function hydrate(array $row): Character
    {
        return new Character(
            id: (int)$row['id'],
            userId: (int)$row['user_id'],
            name: $row['name'],
            level: (int)$row['level'],
            experience: (int)$row['experience'],
            health: (int)$row['health'],
            maxHealth: (int)$row['max_health'],
            qubits: (int)$row['qubits'],
            crimeSkill: (int)$row['crime_skill'],
            combatSkill: (int)$row['combat_skill'],
            hackingSkill: (int)$row['hacking_skill'],
            districtId: (int)$row['district_id'],
            alive: (bool)$row['alive'],
            createdAt: new DateTime($row['created_at']),
            lastActionAt: new DateTime($row['last_action_at'])
        );
    }
}

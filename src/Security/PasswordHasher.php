<?php
declare(strict_types=1);

namespace App\Security;

use Exception;

/**
 * PasswordHasher: NIST-compliant Argon2ID hashing.
 * Memory: 65536 KB (64 MB), Time: 4 iterations, Parallelism: 2
 */
final class PasswordHasher
{
    private const int MEMORY_COST = 65536;
    private const int TIME_COST = 4;
    private const int PARALLELISM = 2;

    /**
     * Hash password using Argon2ID.
     */
    public function hash(string $password): string
    {
        $hashed = password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => self::MEMORY_COST,
            'time_cost' => self::TIME_COST,
            'threads' => self::PARALLELISM,
        ]);

        if ($hashed === false) {
            throw new Exception('Failed to hash password');
        }

        return $hashed;
    }

    /**
     * Verify password against hash.
     */
    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Check if password hash needs rehashing (algorithm change).
     */
    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, [
            'memory_cost' => self::MEMORY_COST,
            'time_cost' => self::TIME_COST,
            'threads' => self::PARALLELISM,
        ]);
    }
}

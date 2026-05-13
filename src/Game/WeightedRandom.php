<?php
declare(strict_types=1);

namespace App\Game;

use Exception;

/**
 * WeightedRandom: Deterministic RNG using cumulative probability arrays.
 * Prevents RNG exploits through server-seeded, reproducible randomization.
 */
final class WeightedRandom
{
    private string $serverSeed;

    public function __construct(string $serverSeed)
    {
        $this->serverSeed = $serverSeed;
    }

    /**
     * Select weighted option using cumulative probability (O(log n) binary search).
     * @param array<int> $weights Array of weights [10, 30, 40, 20]
     * @param string $nonce Action-specific nonce from client
     * @param int $userId Player ID for seeding
     * @return int Selected index (0-based)
     */
    public function selectWeighted(array $weights, string $nonce, int $userId): int
    {
        if (empty($weights)) {
            throw new Exception('Weights array cannot be empty');
        }

        // Build cumulative array: [10, 40, 80, 100]
        $cumulative = [];
        $sum = 0;
        foreach ($weights as $weight) {
            $sum += $weight;
            $cumulative[] = $sum;
        }

        // Deterministic roll: hash(nonce + server_seed + user_id) % total_sum
        $seed = hash('sha256', $nonce . $this->serverSeed . $userId, false);
        $hexValue = hexdec(substr($seed, 0, 16));
        $roll = $hexValue % $sum;

        // Binary search cumulative array
        $left = 0;
        $right = count($cumulative) - 1;
        while ($left < $right) {
            $mid = intval(($left + $right) / 2);
            if ($roll < $cumulative[$mid]) {
                $right = $mid;
            } else {
                $left = $mid + 1;
            }
        }

        return $left;
    }

    /**
     * Generate seeded integer in range [min, max].
     */
    public function randomInt(int $min, int $max, string $nonce, int $userId): int
    {
        if ($min > $max) {
            throw new Exception('Min cannot be greater than max');
        }

        $seed = hash('sha256', $nonce . $this->serverSeed . $userId, false);
        $hexValue = hexdec(substr($seed, 0, 16));
        $range = $max - $min + 1;
        $value = ($hexValue % $range) + $min;

        return $value;
    }

    /**
     * Generate seeded float in range [0.0, 1.0].
     */
    public function randomFloat(string $nonce, int $userId): float
    {
        $seed = hash('sha256', $nonce . $this->serverSeed . $userId, false);
        $hexValue = hexdec(substr($seed, 0, 16));
        // Normalize to 0.0-1.0
        $maxHex = 0xFFFFFFFFFFFFFF;
        return ($hexValue % 1000000) / 1000000.0;
    }
}

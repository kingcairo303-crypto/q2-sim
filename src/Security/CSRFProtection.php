<?php
declare(strict_types=1);

namespace App\Security;

use DateTime;
use Exception;

/**
 * CSRFProtection: One-time CSRF tokens with 1-hour expiry.
 * Prevents cross-site request forgery on state-changing actions.
 */
final class CSRFProtection
{
    private \mysqli $connection;
    private int $tokenExpirySeconds = 3600; // 1 hour

    public function __construct(\mysqli $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Generate CSRF token.
     */
    public function generateToken(int $userId): string
    {
        $token = bin2hex(random_bytes(32));
        $now = (new DateTime())->format('Y-m-d H:i:s');
        $expiresAt = (new DateTime())
            ->modify("+{$this->tokenExpirySeconds} seconds")
            ->format('Y-m-d H:i:s');

        $stmt = $this->connection->prepare(
            'INSERT INTO csrf_tokens (token, user_id, used, created_at, expires_at) VALUES (?, ?, ?, ?, ?)'
        );

        if (!$stmt) {
            throw new Exception('Failed to prepare CSRF insert: ' . $this->connection->error);
        }

        $used = 0;
        $stmt->bind_param('siiss', $token, $userId, $used, $now, $expiresAt);

        if (!$stmt->execute()) {
            throw new Exception('Failed to generate CSRF token: ' . $stmt->error);
        }
        $stmt->close();

        return $token;
    }

    /**
     * Verify CSRF token (one-time use, not expired).
     */
    public function verifyToken(string $token, int $userId): bool
    {
        $stmt = $this->connection->prepare(
            'SELECT used, expires_at FROM csrf_tokens WHERE token = ? AND user_id = ?'
        );

        if (!$stmt) {
            throw new Exception('Failed to prepare CSRF lookup: ' . $this->connection->error);
        }

        $stmt->bind_param('si', $token, $userId);
        if (!$stmt->execute()) {
            throw new Exception('Failed to verify CSRF token: ' . $stmt->error);
        }

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if (!$row) {
            return false; // Token not found
        }

        if ((bool)$row['used']) {
            return false; // Token already used
        }

        $expiresAt = new DateTime($row['expires_at']);
        if (new DateTime() > $expiresAt) {
            return false; // Token expired
        }

        // Mark token as used
        $this->markTokenUsed($token);

        return true;
    }

    /**
     * Mark token as used.
     */
    private function markTokenUsed(string $token): void
    {
        $stmt = $this->connection->prepare('UPDATE csrf_tokens SET used = 1 WHERE token = ?');

        if (!$stmt) {
            throw new Exception('Failed to prepare token mark: ' . $this->connection->error);
        }

        $stmt->bind_param('s', $token);
        if (!$stmt->execute()) {
            throw new Exception('Failed to mark token used: ' . $stmt->error);
        }
        $stmt->close();
    }

    /**
     * Clean up expired tokens.
     */
    public function cleanupExpiredTokens(): void
    {
        $now = (new DateTime())->format('Y-m-d H:i:s');
        $stmt = $this->connection->prepare('DELETE FROM csrf_tokens WHERE expires_at < ?');

        if (!$stmt) {
            throw new Exception('Failed to prepare cleanup: ' . $this->connection->error);
        }

        $stmt->bind_param('s', $now);
        if (!$stmt->execute()) {
            throw new Exception('Failed to cleanup CSRF tokens: ' . $stmt->error);
        }
        $stmt->close();
    }
}

<?php
declare(strict_types=1);

namespace App\Security;

use DateTime;
use Exception;

/**
 * SessionHandler: DB-backed sessions with IP+UA binding, idle/absolute timeouts.
 * Prevents session fixation, hijacking, and replay attacks.
 */
final class SessionHandler
{
    private \mysqli $connection;
    private int $idleTimeoutSeconds = 1800; // 30 minutes
    private int $absoluteTimeoutSeconds = 86400; // 24 hours

    public function __construct(\mysqli $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Create new session with IP+UA binding.
     */
    public function createSession(int $userId, string $ip, string $userAgent): string
    {
        $sessionId = bin2hex(random_bytes(32));
        $now = (new DateTime())->format('Y-m-d H:i:s');
        $ipHash = hash('sha256', $ip);
        $uaHash = hash('sha256', $userAgent);

        $stmt = $this->connection->prepare(
            'INSERT INTO sessions (session_id, user_id, ip_hash, user_agent_hash, payload, created_at, last_activity) ' .
            'VALUES (?, ?, ?, ?, ?, ?, ?)'
        );

        if (!$stmt) {
            throw new Exception('Failed to prepare session insert: ' . $this->connection->error);
        }

        $payload = json_encode(['privilege_level' => 0]);
        $stmt->bind_param(
            'sisssss',
            $sessionId, $userId, $ipHash, $uaHash, $payload, $now, $now
        );

        if (!$stmt->execute()) {
            throw new Exception('Failed to create session: ' . $stmt->error);
        }
        $stmt->close();

        return $sessionId;
    }

    /**
     * Validate session: check existence, IP+UA binding, timeouts.
     */
    public function validateSession(string $sessionId, string $ip, string $userAgent): ?int
    {
        $ipHash = hash('sha256', $ip);
        $uaHash = hash('sha256', $userAgent);

        $stmt = $this->connection->prepare(
            'SELECT user_id, last_activity, created_at FROM sessions ' .
            'WHERE session_id = ? AND ip_hash = ? AND user_agent_hash = ?'
        );

        if (!$stmt) {
            throw new Exception('Failed to prepare session lookup: ' . $this->connection->error);
        }

        $stmt->bind_param('sss', $sessionId, $ipHash, $uaHash);
        if (!$stmt->execute()) {
            throw new Exception('Failed to validate session: ' . $stmt->error);
        }

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if (!$row) {
            return null; // Invalid IP+UA binding
        }

        $lastActivity = new DateTime($row['last_activity']);
        $createdAt = new DateTime($row['created_at']);
        $now = new DateTime();

        $idleDuration = $now->getTimestamp() - $lastActivity->getTimestamp();
        $absoluteDuration = $now->getTimestamp() - $createdAt->getTimestamp();

        if ($idleDuration > $this->idleTimeoutSeconds || $absoluteDuration > $this->absoluteTimeoutSeconds) {
            $this->destroySession($sessionId);
            return null; // Timeout
        }

        // Update last_activity
        $this->updateLastActivity($sessionId);

        return (int)$row['user_id'];
    }

    /**
     * Regenerate session (on privilege escalation).
     */
    public function regenerateSession(string $oldSessionId, int $userId, string $ip, string $userAgent): string
    {
        $this->destroySession($oldSessionId);
        return $this->createSession($userId, $ip, $userAgent);
    }

    /**
     * Update last_activity timestamp.
     */
    private function updateLastActivity(string $sessionId): void
    {
        $now = (new DateTime())->format('Y-m-d H:i:s');
        $stmt = $this->connection->prepare('UPDATE sessions SET last_activity = ? WHERE session_id = ?');

        if (!$stmt) {
            throw new Exception('Failed to prepare activity update: ' . $this->connection->error);
        }

        $stmt->bind_param('ss', $now, $sessionId);
        if (!$stmt->execute()) {
            throw new Exception('Failed to update activity: ' . $stmt->error);
        }
        $stmt->close();
    }

    /**
     * Destroy session (logout).
     */
    public function destroySession(string $sessionId): void
    {
        $stmt = $this->connection->prepare('DELETE FROM sessions WHERE session_id = ?');

        if (!$stmt) {
            throw new Exception('Failed to prepare session delete: ' . $this->connection->error);
        }

        $stmt->bind_param('s', $sessionId);
        if (!$stmt->execute()) {
            throw new Exception('Failed to destroy session: ' . $stmt->error);
        }
        $stmt->close();
    }

    /**
     * Clean up expired sessions.
     */
    public function cleanupExpiredSessions(): void
    {
        $cutoff = (new DateTime())
            ->modify("-{$this->absoluteTimeoutSeconds} seconds")
            ->format('Y-m-d H:i:s');

        $stmt = $this->connection->prepare('DELETE FROM sessions WHERE created_at < ?');

        if (!$stmt) {
            throw new Exception('Failed to prepare cleanup: ' . $this->connection->error);
        }

        $stmt->bind_param('s', $cutoff);
        if (!$stmt->execute()) {
            throw new Exception('Failed to cleanup sessions: ' . $stmt->error);
        }
        $stmt->close();
    }
}

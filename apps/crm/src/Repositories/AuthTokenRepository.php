<?php

declare(strict_types=1);

final class AuthTokenRepository
{
    public const REMEMBER_PURPOSE = 'remember';
    public const PASSWORD_RESET_PURPOSE = 'password_reset';

    public static function ensureTable(): void
    {
        Database::connection()->exec(
            'CREATE TABLE IF NOT EXISTS auth_tokens (
                selector VARCHAR(32) NOT NULL PRIMARY KEY,
                user_id VARCHAR(191) NOT NULL,
                purpose VARCHAR(32) NOT NULL,
                token_hash CHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX auth_tokens_user_purpose_idx (user_id, purpose),
                INDEX auth_tokens_expiry_idx (expires_at)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );
    }

    public static function issue(string $userId, string $purpose, int $ttlSeconds): string
    {
        self::ensureTable();
        self::deleteExpired();

        $selector = bin2hex(random_bytes(9));
        $verifier = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + max(60, $ttlSeconds));

        $stmt = Database::connection()->prepare(
            'INSERT INTO auth_tokens (selector, user_id, purpose, token_hash, expires_at, created_at)
             VALUES (:selector, :user_id, :purpose, :token_hash, :expires_at, NOW())'
        );
        $stmt->execute([
            'selector' => $selector,
            'user_id' => $userId,
            'purpose' => $purpose,
            'token_hash' => hash('sha256', $verifier),
            'expires_at' => $expiresAt,
        ]);

        return $selector . '.' . $verifier;
    }

    public static function issuePasswordReset(string $userId): ?string
    {
        self::ensureTable();

        $recent = Database::connection()->prepare(
            'SELECT COUNT(*) FROM auth_tokens
             WHERE user_id = :user_id AND purpose = :purpose
             AND created_at >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)'
        );
        $recent->execute(['user_id' => $userId, 'purpose' => self::PASSWORD_RESET_PURPOSE]);
        if ((int) $recent->fetchColumn() > 0) {
            return null;
        }

        self::deleteForUser($userId, self::PASSWORD_RESET_PURPOSE);
        return self::issue($userId, self::PASSWORD_RESET_PURPOSE, 3600);
    }

    public static function validUserId(string $token, string $purpose): ?string
    {
        $parts = self::tokenParts($token);
        if ($parts === null) {
            return null;
        }

        self::ensureTable();
        [$selector, $verifier] = $parts;
        $stmt = Database::connection()->prepare(
            'SELECT user_id, token_hash FROM auth_tokens
             WHERE selector = :selector AND purpose = :purpose AND expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute(['selector' => $selector, 'purpose' => $purpose]);
        $stored = $stmt->fetch();

        if (!$stored || !hash_equals((string) $stored['token_hash'], hash('sha256', $verifier))) {
            return null;
        }

        return (string) $stored['user_id'];
    }

    public static function selector(string $token): ?string
    {
        return self::tokenParts($token)[0] ?? null;
    }

    public static function deleteSelector(?string $selector): void
    {
        if ($selector === null || $selector === '') {
            return;
        }

        self::ensureTable();
        $stmt = Database::connection()->prepare('DELETE FROM auth_tokens WHERE selector = :selector');
        $stmt->execute(['selector' => $selector]);
    }

    public static function deleteForUser(string $userId, ?string $purpose = null): void
    {
        self::ensureTable();
        $params = ['user_id' => $userId];
        $sql = 'DELETE FROM auth_tokens WHERE user_id = :user_id';
        if ($purpose !== null) {
            $sql .= ' AND purpose = :purpose';
            $params['purpose'] = $purpose;
        }

        Database::connection()->prepare($sql)->execute($params);
    }

    public static function resetPassword(string $token, string $passwordHash): bool
    {
        self::ensureTable();
        $parts = self::tokenParts($token);
        if ($parts === null) {
            return false;
        }

        [$selector, $verifier] = $parts;
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $tokenStmt = $pdo->prepare(
                'SELECT user_id, token_hash FROM auth_tokens
                 WHERE selector = :selector AND purpose = :purpose AND expires_at > NOW()
                 LIMIT 1 FOR UPDATE'
            );
            $tokenStmt->execute(['selector' => $selector, 'purpose' => self::PASSWORD_RESET_PURPOSE]);
            $stored = $tokenStmt->fetch();
            if (!$stored || !hash_equals((string) $stored['token_hash'], hash('sha256', $verifier))) {
                $pdo->rollBack();
                return false;
            }
            $userId = (string) $stored['user_id'];

            $stmt = $pdo->prepare('UPDATE users SET password_hash = :password_hash, updated_at = NOW() WHERE id = :id AND status = "ACTIVE"');
            $stmt->execute(['password_hash' => $passwordHash, 'id' => $userId]);
            if ($stmt->rowCount() !== 1) {
                $pdo->rollBack();
                return false;
            }

            $pdo->prepare('DELETE FROM auth_tokens WHERE user_id = :user_id')->execute(['user_id' => $userId]);
            $pdo->commit();
            return true;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    private static function deleteExpired(): void
    {
        Database::connection()->exec('DELETE FROM auth_tokens WHERE expires_at <= NOW()');
    }

    private static function tokenParts(string $token): ?array
    {
        if (!preg_match('/^([a-f0-9]{18})\.([a-f0-9]{64})$/', strtolower(trim($token)), $matches)) {
            return null;
        }

        return [$matches[1], $matches[2]];
    }
}

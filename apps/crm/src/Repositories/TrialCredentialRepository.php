<?php

declare(strict_types=1);

final class TrialCredentialRepository
{
    public static function issue(string $userId, string $accountEmail, string $companyName, string $password): string
    {
        self::ensureTable();
        self::deleteExpired();
        $token = bin2hex(random_bytes(32));
        $stmt = Database::connection()->prepare(
            'INSERT INTO trial_credential_deliveries
             (id, user_id, account_email, company_name, token_hash, password_encrypted, expires_at, viewed_at, created_at)
             VALUES (:id, :user_id, :account_email, :company_name, :token_hash, :password_encrypted, DATE_ADD(NOW(), INTERVAL 1 HOUR), NULL, NOW())'
        );
        $stmt->execute([
            'id' => cuid(),
            'user_id' => $userId,
            'account_email' => strtolower(trim($accountEmail)),
            'company_name' => trim($companyName),
            'token_hash' => hash('sha256', $token),
            'password_encrypted' => self::encrypt($password),
        ]);
        return $token;
    }

    public static function isValid(string $token): bool
    {
        self::ensureTable();
        $token = self::normalizeToken($token);
        if ($token === null) return false;
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM trial_credential_deliveries
             WHERE token_hash = :token_hash AND viewed_at IS NULL AND expires_at > NOW()'
        );
        $stmt->execute(['token_hash' => hash('sha256', $token)]);
        return (int) $stmt->fetchColumn() === 1;
    }

    public static function rotateTokenForUser(string $userId): ?string
    {
        self::ensureTable();
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'SELECT id FROM trial_credential_deliveries
                 WHERE user_id = :user_id AND viewed_at IS NULL
                 ORDER BY created_at DESC
                 LIMIT 1 FOR UPDATE'
            );
            $stmt->execute(['user_id' => $userId]);
            $deliveryId = trim((string) $stmt->fetchColumn());
            if ($deliveryId === '') {
                $pdo->rollBack();
                return null;
            }

            $token = bin2hex(random_bytes(32));
            $update = $pdo->prepare(
                'UPDATE trial_credential_deliveries
                 SET token_hash = :token_hash, expires_at = DATE_ADD(NOW(), INTERVAL 1 HOUR)
                 WHERE id = :id AND viewed_at IS NULL'
            );
            $update->execute([
                'id' => $deliveryId,
                'token_hash' => hash('sha256', $token),
            ]);
            if ($update->rowCount() !== 1) {
                $pdo->rollBack();
                return null;
            }

            $pdo->commit();
            return $token;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public static function consume(string $token): ?array
    {
        self::ensureTable();
        $token = self::normalizeToken($token);
        if ($token === null) return null;
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'SELECT * FROM trial_credential_deliveries
                 WHERE token_hash = :token_hash AND viewed_at IS NULL AND expires_at > NOW()
                 LIMIT 1 FOR UPDATE'
            );
            $stmt->execute(['token_hash' => hash('sha256', $token)]);
            $delivery = $stmt->fetch();
            if (!$delivery) {
                $pdo->rollBack();
                return null;
            }
            $password = self::decrypt((string) $delivery['password_encrypted']);
            $update = $pdo->prepare('UPDATE trial_credential_deliveries SET viewed_at = NOW() WHERE id = :id AND viewed_at IS NULL');
            $update->execute(['id' => $delivery['id']]);
            if ($update->rowCount() !== 1) {
                $pdo->rollBack();
                return null;
            }
            $pdo->commit();
            return [
                'email' => (string) $delivery['account_email'],
                'company' => (string) $delivery['company_name'],
                'password' => $password,
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
    }

    public static function revoke(string $token): void
    {
        self::ensureTable();
        $token = self::normalizeToken($token);
        if ($token === null) return;
        $stmt = Database::connection()->prepare('DELETE FROM trial_credential_deliveries WHERE token_hash = :token_hash');
        $stmt->execute(['token_hash' => hash('sha256', $token)]);
    }

    private static function ensureTable(): void
    {
        Database::connection()->exec(
            'CREATE TABLE IF NOT EXISTS trial_credential_deliveries (
                id VARCHAR(191) NOT NULL PRIMARY KEY,
                user_id VARCHAR(191) NOT NULL,
                account_email VARCHAR(191) NOT NULL,
                company_name VARCHAR(191) NOT NULL,
                token_hash CHAR(64) NOT NULL UNIQUE,
                password_encrypted TEXT NOT NULL,
                expires_at DATETIME NOT NULL,
                viewed_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX trial_credentials_user_idx (user_id),
                INDEX trial_credentials_expiry_idx (expires_at),
                INDEX trial_credentials_viewed_idx (viewed_at)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );
    }

    private static function deleteExpired(): void
    {
        Database::connection()->exec(
            'DELETE FROM trial_credential_deliveries
             WHERE expires_at <= NOW() OR viewed_at < DATE_SUB(NOW(), INTERVAL 1 DAY)'
        );
    }

    private static function normalizeToken(string $token): ?string
    {
        $token = strtolower(trim($token));
        return preg_match('/^[a-f0-9]{64}$/', $token) ? $token : null;
    }

    private static function encryptionKey(): string
    {
        $appKey = trim((string) (getenv('APP_KEY') ?: ''));
        $seed = $appKey !== '' ? $appKey : (string) (getenv('DB_PASSWORD') ?: '');
        if ($seed === '') throw new RuntimeException('Falta una clave estable para proteger las credenciales temporales.');
        return hash('sha256', 'membora-trial-credentials|' . $seed, true);
    }

    private static function encrypt(string $password): string
    {
        if (!function_exists('openssl_encrypt')) throw new RuntimeException('OpenSSL es obligatorio para proteger las credenciales temporales.');
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($password, 'aes-256-gcm', self::encryptionKey(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($ciphertext === false) throw new RuntimeException('No se pudo proteger la contraseña temporal.');
        return base64_encode($iv . $tag . $ciphertext);
    }

    private static function decrypt(string $encrypted): string
    {
        $raw = base64_decode($encrypted, true);
        if ($raw === false || strlen($raw) <= 28 || !function_exists('openssl_decrypt')) {
            throw new RuntimeException('La credencial temporal no se puede recuperar.');
        }
        $password = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', self::encryptionKey(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        if ($password === false) throw new RuntimeException('La credencial temporal no se puede recuperar.');
        return $password;
    }
}

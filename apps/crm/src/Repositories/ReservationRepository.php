<?php

declare(strict_types=1);

final class ReservationRepository
{
    private const ACTIVE_STATUSES = ['reserved', 'attended', 'no_show'];

    public static function ensureTable(): void
    {
        Database::connection()->exec(
            'CREATE TABLE IF NOT EXISTS reservations (
                id VARCHAR(191) NOT NULL PRIMARY KEY,
                tenant_id VARCHAR(191) NOT NULL,
                member_id VARCHAR(191) NOT NULL,
                class_session_id VARCHAR(191) NOT NULL,
                status VARCHAR(32) NOT NULL DEFAULT "reserved",
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                cancelled_at DATETIME NULL,
                INDEX reservations_tenant_id_idx (tenant_id),
                INDEX reservations_member_id_idx (member_id),
                INDEX reservations_class_session_id_idx (class_session_id),
                UNIQUE KEY reservations_session_member_unique (tenant_id, class_session_id, member_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );
    }

    public static function bySessionIds(string $tenantId, array $sessionIds): array
    {
        self::ensureTable();

        if (!$sessionIds) {
            return [];
        }

        $params = ['tenant_id' => $tenantId];
        $placeholders = [];
        foreach (array_values(array_unique($sessionIds)) as $index => $sessionId) {
            $key = 'session_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $sessionId;
        }

        $stmt = Database::connection()->prepare(
            'SELECT reservations.*, members.first_name, members.last_name, members.email, members.phone
             FROM reservations
             INNER JOIN members ON members.id = reservations.member_id AND members.tenant_id = reservations.tenant_id
             WHERE reservations.tenant_id = :tenant_id
             AND reservations.class_session_id IN (' . implode(', ', $placeholders) . ')
             ORDER BY FIELD(reservations.status, "reserved", "attended", "no_show", "cancelled"), reservations.created_at DESC'
        );
        $stmt->execute($params);

        $grouped = [];
        foreach ($stmt->fetchAll() as $reservation) {
            $reservation['status'] = self::normalizeStatus((string) $reservation['status']);
            $grouped[$reservation['class_session_id']][] = $reservation;
        }

        return $grouped;
    }

    public static function byMemberIds(string $tenantId, array $memberIds): array
    {
        self::ensureTable();

        if (!$memberIds) {
            return [];
        }

        $params = ['tenant_id' => $tenantId];
        $placeholders = [];
        foreach (array_values(array_unique($memberIds)) as $index => $memberId) {
            $key = 'member_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $memberId;
        }

        $stmt = Database::connection()->prepare(
            'SELECT reservations.*, class_sessions.starts_at, class_sessions.ends_at,
                    class_types.name AS class_name, users.name AS instructor_name
             FROM reservations
             INNER JOIN class_sessions ON class_sessions.id = reservations.class_session_id
                AND class_sessions.tenant_id = reservations.tenant_id
             INNER JOIN class_types ON class_types.id = class_sessions.class_type_id
                AND class_types.tenant_id = reservations.tenant_id
             LEFT JOIN users ON users.id = class_sessions.instructor_user_id
                AND users.tenant_id = reservations.tenant_id
             WHERE reservations.tenant_id = :tenant_id
             AND reservations.member_id IN (' . implode(', ', $placeholders) . ')
             ORDER BY class_sessions.starts_at DESC, reservations.created_at DESC'
        );
        $stmt->execute($params);

        $grouped = [];
        foreach ($stmt->fetchAll() as $reservation) {
            $reservation['status'] = self::normalizeStatus((string) $reservation['status']);
            $grouped[$reservation['member_id']][] = $reservation;
        }

        return $grouped;
    }

    public static function activeCount(string $tenantId, string $sessionId): int
    {
        self::ensureTable();
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*)
             FROM reservations
             WHERE tenant_id = :tenant_id
             AND class_session_id = :class_session_id
             AND LOWER(status) IN ("reserved", "attended", "no_show")'
        );
        $stmt->execute(['tenant_id' => $tenantId, 'class_session_id' => $sessionId]);
        return (int) $stmt->fetchColumn();
    }

    public static function sessionCapacity(string $tenantId, string $sessionId): ?int
    {
        $stmt = Database::connection()->prepare('SELECT capacity FROM class_sessions WHERE id = :id AND tenant_id = :tenant_id LIMIT 1');
        $stmt->execute(['id' => $sessionId, 'tenant_id' => $tenantId]);
        $capacity = $stmt->fetchColumn();
        return $capacity === false ? null : (int) $capacity;
    }

    public static function create(string $tenantId, string $memberId, string $sessionId): void
    {
        self::ensureTable();
        $pdo = Database::connection();

        $memberStmt = $pdo->prepare('SELECT id FROM members WHERE id = :id AND tenant_id = :tenant_id AND status <> "INACTIVE" LIMIT 1');
        $memberStmt->execute(['id' => $memberId, 'tenant_id' => $tenantId]);
        if (!$memberStmt->fetchColumn()) {
            throw new RuntimeException('Selecciona un socio activo para reservar.');
        }

        $capacity = self::sessionCapacity($tenantId, $sessionId);
        if ($capacity === null) {
            throw new RuntimeException('La clase seleccionada no existe.');
        }

        $existingStmt = $pdo->prepare(
            'SELECT id, LOWER(status) AS status FROM reservations
             WHERE tenant_id = :tenant_id
             AND member_id = :member_id
             AND class_session_id = :class_session_id
             LIMIT 1'
        );
        $existingStmt->execute([
            'tenant_id' => $tenantId,
            'member_id' => $memberId,
            'class_session_id' => $sessionId,
        ]);
        $existing = $existingStmt->fetch();

        if ($existing && in_array($existing['status'], self::ACTIVE_STATUSES, true)) {
            throw new RuntimeException('Este socio ya tiene una reserva activa en esta clase.');
        }

        if (self::activeCount($tenantId, $sessionId) >= $capacity) {
            throw new RuntimeException('La clase esta llena. No se pueden anadir mas reservas activas.');
        }

        if ($existing) {
            $stmt = $pdo->prepare(
                'UPDATE reservations
                 SET status = "reserved", cancelled_at = NULL, created_at = NOW()
                 WHERE id = :id AND tenant_id = :tenant_id'
            );
            $stmt->execute(['id' => $existing['id'], 'tenant_id' => $tenantId]);
            return;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO reservations (id, tenant_id, member_id, class_session_id, status, created_at, cancelled_at)
             VALUES (:id, :tenant_id, :member_id, :class_session_id, "reserved", NOW(), NULL)'
        );
        $stmt->execute([
            'id' => cuid(),
            'tenant_id' => $tenantId,
            'member_id' => $memberId,
            'class_session_id' => $sessionId,
        ]);
    }

    public static function updateStatus(string $tenantId, string $reservationId, string $status): void
    {
        self::ensureTable();
        $allowed = ['reserved', 'cancelled', 'attended', 'no_show'];
        if (!in_array($status, $allowed, true)) {
            throw new RuntimeException('Estado de reserva no valido.');
        }

        $currentStmt = Database::connection()->prepare(
            'SELECT id, class_session_id, LOWER(status) AS status
             FROM reservations
             WHERE id = :id AND tenant_id = :tenant_id
             LIMIT 1'
        );
        $currentStmt->execute(['id' => $reservationId, 'tenant_id' => $tenantId]);
        $current = $currentStmt->fetch();
        if (!$current) {
            throw new RuntimeException('No se encontro la reserva seleccionada.');
        }

        if (in_array($status, self::ACTIVE_STATUSES, true) && !in_array($current['status'], self::ACTIVE_STATUSES, true)) {
            $capacity = self::sessionCapacity($tenantId, (string) $current['class_session_id']);
            if ($capacity !== null && self::activeCount($tenantId, (string) $current['class_session_id']) >= $capacity) {
                throw new RuntimeException('La clase esta llena. No se puede reactivar esta reserva.');
            }
        }

        $stmt = Database::connection()->prepare(
            'UPDATE reservations
             SET status = :status,
                 cancelled_at = CASE WHEN :status_cancelled = "cancelled" THEN NOW() ELSE NULL END
             WHERE id = :id AND tenant_id = :tenant_id'
        );
        $stmt->execute([
            'status' => $status,
            'status_cancelled' => $status,
            'id' => $reservationId,
            'tenant_id' => $tenantId,
        ]);
    }

    public static function deleteForMember(string $tenantId, string $memberId): void
    {
        self::ensureTable();
        $stmt = Database::connection()->prepare('DELETE FROM reservations WHERE member_id = :member_id AND tenant_id = :tenant_id');
        $stmt->execute(['member_id' => $memberId, 'tenant_id' => $tenantId]);
    }

    public static function deleteForSession(string $tenantId, string $sessionId): void
    {
        self::ensureTable();
        $stmt = Database::connection()->prepare('DELETE FROM reservations WHERE class_session_id = :class_session_id AND tenant_id = :tenant_id');
        $stmt->execute(['class_session_id' => $sessionId, 'tenant_id' => $tenantId]);
    }

    private static function normalizeStatus(string $status): string
    {
        return strtolower(trim($status));
    }
}

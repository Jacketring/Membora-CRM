<?php

declare(strict_types=1);

final class CheckinRepository
{
    public static function ensureTable(): void
    {
        Database::connection()->exec(
            'CREATE TABLE IF NOT EXISTS checkins (
                id VARCHAR(191) NOT NULL PRIMARY KEY,
                tenant_id VARCHAR(191) NOT NULL,
                member_id VARCHAR(191) NOT NULL,
                class_session_id VARCHAR(191) NULL,
                reservation_id VARCHAR(191) NULL,
                method VARCHAR(32) NOT NULL DEFAULT "MANUAL",
                checked_in_at DATETIME NOT NULL,
                notes TEXT NULL,
                created_by_user_id VARCHAR(191) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX checkins_tenant_id_idx (tenant_id),
                INDEX checkins_member_id_idx (member_id),
                INDEX checkins_class_session_id_idx (class_session_id),
                INDEX checkins_reservation_id_idx (reservation_id),
                INDEX checkins_checked_in_at_idx (checked_in_at)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );

        self::ensureColumn('checkins', 'tenant_id', 'VARCHAR(191) NULL');
        self::ensureColumn('checkins', 'member_id', 'VARCHAR(191) NULL');
        self::ensureColumn('checkins', 'class_session_id', 'VARCHAR(191) NULL');
        self::ensureColumn('checkins', 'reservation_id', 'VARCHAR(191) NULL');
        self::ensureColumn('checkins', 'method', 'VARCHAR(32) NOT NULL DEFAULT "MANUAL"');
        self::ensureColumn('checkins', 'checked_in_at', 'DATETIME NULL');
        self::ensureColumn('checkins', 'notes', 'TEXT NULL');
        self::ensureColumn('checkins', 'created_by_user_id', 'VARCHAR(191) NULL');
        self::ensureColumn('checkins', 'created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
    }

    public static function metrics(string $tenantId): array
    {
        self::ensureTable();
        $pdo = Database::connection();

        return [
            'today' => self::count($pdo, $tenantId, 'DATE(checked_in_at) = CURDATE()'),
            'week' => self::count($pdo, $tenantId, 'checked_in_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)'),
            'manual' => self::count($pdo, $tenantId, 'method = "MANUAL"'),
            'with_class' => self::count($pdo, $tenantId, 'class_session_id IS NOT NULL'),
        ];
    }

    public static function all(string $tenantId, string $query = '', string $dateFrom = '', string $dateTo = '', int $limit = 200): array
    {
        self::ensureTable();
        ClassRepository::ensureTables();
        ReservationRepository::ensureTable();

        $params = ['tenant_id' => $tenantId];
        $where = ['checkins.tenant_id = :tenant_id'];

        if ($query !== '') {
            $where[] = '(members.first_name LIKE :query OR members.last_name LIKE :query OR members.email LIKE :query OR members.phone LIKE :query OR class_types.name LIKE :query OR checkins.notes LIKE :query)';
            $params['query'] = '%' . $query . '%';
        }

        if ($dateFrom !== '') {
            $where[] = 'DATE(checkins.checked_in_at) >= :date_from';
            $params['date_from'] = $dateFrom;
        }

        if ($dateTo !== '') {
            $where[] = 'DATE(checkins.checked_in_at) <= :date_to';
            $params['date_to'] = $dateTo;
        }

        $stmt = Database::connection()->prepare(
            'SELECT checkins.*,
                    members.first_name,
                    members.last_name,
                    members.email,
                    members.phone,
                    class_sessions.starts_at,
                    class_sessions.ends_at,
                    class_types.name AS class_name,
                    users.name AS created_by_name
             FROM checkins
             INNER JOIN members ON members.id = checkins.member_id AND members.tenant_id = checkins.tenant_id
             LEFT JOIN class_sessions ON class_sessions.id = checkins.class_session_id AND class_sessions.tenant_id = checkins.tenant_id
             LEFT JOIN class_types ON class_types.id = class_sessions.class_type_id AND class_types.tenant_id = checkins.tenant_id
             LEFT JOIN users ON users.id = checkins.created_by_user_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY checkins.checked_in_at DESC, checkins.created_at DESC
             LIMIT ' . max(1, min($limit, 300))
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function create(string $tenantId, array $data): void
    {
        self::ensureTable();
        $params = self::params($tenantId, $data);

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO checkins (id, tenant_id, member_id, class_session_id, reservation_id, method, checked_in_at, notes, created_by_user_id, created_at)
                 VALUES (:id, :tenant_id, :member_id, :class_session_id, :reservation_id, :method, :checked_in_at, :notes, :created_by_user_id, NOW())'
            );
            $stmt->execute($params + ['id' => cuid()]);

            if (!empty($params['reservation_id'])) {
                $updateReservation = $pdo->prepare(
                    'UPDATE reservations
                     SET status = "attended"
                     WHERE id = :id AND tenant_id = :tenant_id AND member_id = :member_id'
                );
                $updateReservation->execute([
                    'id' => $params['reservation_id'],
                    'tenant_id' => $tenantId,
                    'member_id' => $params['member_id'],
                ]);
            }

            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public static function delete(string $tenantId, string $id): void
    {
        self::ensureTable();
        $stmt = Database::connection()->prepare('DELETE FROM checkins WHERE id = :id AND tenant_id = :tenant_id');
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
    }

    public static function memberOptions(string $tenantId): array
    {
        return PaymentRepository::memberOptions($tenantId);
    }

    public static function reservationOptions(string $tenantId): array
    {
        ReservationRepository::ensureTable();
        ClassRepository::ensureTables();

        $stmt = Database::connection()->prepare(
            'SELECT reservations.id,
                    reservations.member_id,
                    reservations.class_session_id,
                    reservations.status,
                    members.first_name,
                    members.last_name,
                    class_sessions.starts_at,
                    class_sessions.ends_at,
                    class_types.name AS class_name
             FROM reservations
             INNER JOIN members ON members.id = reservations.member_id AND members.tenant_id = reservations.tenant_id
             INNER JOIN class_sessions ON class_sessions.id = reservations.class_session_id AND class_sessions.tenant_id = reservations.tenant_id
             INNER JOIN class_types ON class_types.id = class_sessions.class_type_id AND class_types.tenant_id = reservations.tenant_id
             WHERE reservations.tenant_id = :tenant_id
             AND reservations.status IN ("reserved", "attended", "no_show")
             ORDER BY class_sessions.starts_at DESC
             LIMIT 300'
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $row['member_name'] = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
        }
        unset($row);

        return $rows;
    }

    public static function deleteForMember(string $tenantId, string $memberId): void
    {
        self::ensureTable();
        $stmt = Database::connection()->prepare('DELETE FROM checkins WHERE member_id = :member_id AND tenant_id = :tenant_id');
        $stmt->execute(['member_id' => $memberId, 'tenant_id' => $tenantId]);
    }

    public static function deleteForSession(string $tenantId, string $sessionId): void
    {
        self::ensureTable();
        $stmt = Database::connection()->prepare('DELETE FROM checkins WHERE class_session_id = :class_session_id AND tenant_id = :tenant_id');
        $stmt->execute(['class_session_id' => $sessionId, 'tenant_id' => $tenantId]);
    }

    private static function params(string $tenantId, array $data): array
    {
        $memberId = trim((string) ($data['member_id'] ?? ''));
        if ($memberId === '' || !self::memberExists($tenantId, $memberId)) {
            throw new RuntimeException('Selecciona un socio valido para registrar el check-in.');
        }

        $reservationId = trim((string) ($data['reservation_id'] ?? '')) ?: null;
        $classSessionId = trim((string) ($data['class_session_id'] ?? '')) ?: null;

        if ($reservationId !== null) {
            $reservation = self::reservationForMember($tenantId, $reservationId, $memberId);
            if (!$reservation) {
                throw new RuntimeException('La reserva seleccionada no pertenece al socio.');
            }
            $classSessionId = (string) $reservation['class_session_id'];
        } elseif ($classSessionId !== null && !self::classSessionExists($tenantId, $classSessionId)) {
            throw new RuntimeException('La clase seleccionada no existe.');
        }

        $date = trim((string) ($data['checkin_date'] ?? '')) ?: date('Y-m-d');
        $time = trim((string) ($data['checkin_time'] ?? '')) ?: date('H:i');
        $checkedInAt = $date . ' ' . $time . ':00';

        if (!strtotime($checkedInAt)) {
            throw new RuntimeException('Indica una fecha y hora validas para el check-in.');
        }

        $method = strtoupper(trim((string) ($data['method'] ?? 'MANUAL')));
        if (!in_array($method, ['MANUAL', 'QR'], true)) {
            $method = 'MANUAL';
        }

        return [
            'tenant_id' => $tenantId,
            'member_id' => $memberId,
            'class_session_id' => $classSessionId,
            'reservation_id' => $reservationId,
            'method' => $method,
            'checked_in_at' => $checkedInAt,
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            'created_by_user_id' => Auth::user()['id'] ?? null,
        ];
    }

    private static function memberExists(string $tenantId, string $memberId): bool
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM members WHERE id = :id AND tenant_id = :tenant_id AND status <> "INACTIVE"');
        $stmt->execute(['id' => $memberId, 'tenant_id' => $tenantId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private static function reservationForMember(string $tenantId, string $reservationId, string $memberId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM reservations
             WHERE id = :id AND tenant_id = :tenant_id AND member_id = :member_id
             LIMIT 1'
        );
        $stmt->execute(['id' => $reservationId, 'tenant_id' => $tenantId, 'member_id' => $memberId]);
        $reservation = $stmt->fetch();

        return $reservation ?: null;
    }

    private static function classSessionExists(string $tenantId, string $classSessionId): bool
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM class_sessions WHERE id = :id AND tenant_id = :tenant_id');
        $stmt->execute(['id' => $classSessionId, 'tenant_id' => $tenantId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private static function count(PDO $pdo, string $tenantId, string $where): int
    {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM checkins WHERE tenant_id = :tenant_id AND {$where}");
        $stmt->execute(['tenant_id' => $tenantId]);

        return (int) $stmt->fetchColumn();
    }

    private static function ensureColumn(string $table, string $column, string $definition): void
    {
        try {
            $stmt = Database::connection()->query('SHOW COLUMNS FROM ' . $table . ' LIKE "' . $column . '"');
            if ($stmt && $stmt->fetchColumn()) {
                return;
            }

            Database::connection()->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
        } catch (Throwable) {
        }
    }
}

<?php

declare(strict_types=1);

final class PlatformPlanRepository
{
    public static function ensureTable(): void
    {
        Database::connection()->exec(
            'CREATE TABLE IF NOT EXISTS saas_plans (
                id VARCHAR(191) NOT NULL PRIMARY KEY,
                code VARCHAR(64) NOT NULL UNIQUE,
                name VARCHAR(191) NOT NULL,
                monthly_price DECIMAL(10,2) NOT NULL DEFAULT 0,
                setup_price DECIMAL(10,2) NOT NULL DEFAULT 0,
                discount_price DECIMAL(10,2) NULL,
                discount_label VARCHAR(120) NULL,
                max_users INT NULL,
                max_members INT NULL,
                status VARCHAR(32) NOT NULL DEFAULT "ACTIVE",
                features TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX saas_plans_status_idx (status)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );

        self::ensureColumn('discount_price', 'ALTER TABLE saas_plans ADD COLUMN discount_price DECIMAL(10,2) NULL AFTER setup_price');
        self::ensureColumn('discount_label', 'ALTER TABLE saas_plans ADD COLUMN discount_label VARCHAR(120) NULL AFTER discount_price');
        self::ensureColumn('stripe_monthly_price_id', 'ALTER TABLE saas_plans ADD COLUMN stripe_monthly_price_id VARCHAR(191) NULL AFTER discount_label');
        self::ensureColumn('stripe_annual_price_id', 'ALTER TABLE saas_plans ADD COLUMN stripe_annual_price_id VARCHAR(191) NULL AFTER stripe_monthly_price_id');
        self::seedDefaults();
    }

    public static function metrics(): array
    {
        self::ensureTable();
        $pdo = Database::connection();

        return [
            'active' => (int) $pdo->query('SELECT COUNT(*) FROM saas_plans WHERE status = "ACTIVE"')->fetchColumn(),
            'average_price' => (float) $pdo->query('SELECT COALESCE(AVG(monthly_price), 0) FROM saas_plans WHERE status = "ACTIVE"')->fetchColumn(),
            'enterprise' => (int) $pdo->query('SELECT COUNT(*) FROM saas_plans WHERE code = "ENTERPRISE" AND status = "ACTIVE"')->fetchColumn(),
        ];
    }

    public static function all(string $query = '', string $status = ''): array
    {
        self::ensureTable();
        $params = [];
        $where = ['1 = 1'];

        if ($query !== '') {
            $where[] = '(name LIKE :query OR code LIKE :query OR features LIKE :query)';
            $params['query'] = '%' . $query . '%';
        }

        if ($status !== '') {
            $where[] = 'status = :status';
            $params['status'] = $status;
        }

        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM saas_plans
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY monthly_price ASC, name ASC'
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function options(): array
    {
        $options = [];
        foreach (self::all('', 'ACTIVE') as $plan) {
            $options[$plan['code']] = $plan['name'];
        }

        return $options ?: ['TRIAL' => 'Prueba', 'BASIC' => 'Basico', 'PRO' => 'Pro', 'BUSINESS' => 'Business', 'ENTERPRISE' => 'Enterprise'];
    }

    public static function priceMap(): array
    {
        $prices = [];
        foreach (self::all('', 'ACTIVE') as $plan) {
            $prices[$plan['code']] = number_format(self::effectiveMonthlyPrice($plan), 2, '.', '');
        }

        return $prices ?: [
            'TRIAL' => '0.00',
            'BASIC' => '49.00',
            'PRO' => '89.00',
            'BUSINESS' => '149.00',
            'ENTERPRISE' => '299.00',
        ];
    }

    public static function create(array $data): void
    {
        self::ensureTable();
        $stmt = Database::connection()->prepare(
            'INSERT INTO saas_plans (id, code, name, monthly_price, setup_price, discount_price, discount_label, stripe_monthly_price_id, stripe_annual_price_id, max_users, max_members, status, features, created_at, updated_at)
             VALUES (:id, :code, :name, :monthly_price, :setup_price, :discount_price, :discount_label, :stripe_monthly_price_id, :stripe_annual_price_id, :max_users, :max_members, :status, :features, NOW(), NOW())'
        );
        $stmt->execute(self::planParams($data) + ['id' => cuid()]);
    }

    public static function update(string $id, array $data): void
    {
        self::ensureTable();
        $stmt = Database::connection()->prepare(
            'UPDATE saas_plans
             SET code = :code,
                 name = :name,
                 monthly_price = :monthly_price,
                 setup_price = :setup_price,
                 discount_price = :discount_price,
                 discount_label = :discount_label,
                 stripe_monthly_price_id = :stripe_monthly_price_id,
                 stripe_annual_price_id = :stripe_annual_price_id,
                 max_users = :max_users,
                 max_members = :max_members,
                 status = :status,
                 features = :features,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute(self::planParams($data) + ['id' => $id]);
    }

    private static function seedDefaults(): void
    {
        $pdo = Database::connection();
        $insert = $pdo->prepare(
            'INSERT IGNORE INTO saas_plans (id, code, name, monthly_price, setup_price, max_users, max_members, status, features, created_at, updated_at)
             VALUES (:id, :code, :name, :monthly_price, :setup_price, :max_users, :max_members, "ACTIVE", :features, NOW(), NOW())'
        );

        foreach ([
            ['TRIAL', 'Prueba', '0.00', '0.00', 2, 100, 'Plan de prueba configurable sin cobro ni renovacion automatica.'],
            ['BASIC', 'Basico', '49.00', '0.00', 3, 300, 'Leads, socios, tareas y membresias base.'],
            ['PRO', 'Pro', '89.00', '99.00', 8, 1000, 'Calendario de clases, usuarios y soporte prioritario.'],
            ['BUSINESS', 'Business', '149.00', '199.00', 20, 3000, 'Multi-equipo, reporting avanzado y soporte preferente.'],
            ['ENTERPRISE', 'Enterprise', '299.00', '499.00', null, null, 'Condiciones personalizadas para cadenas o franquicias.'],
        ] as $plan) {
            $insert->execute([
                'id' => cuid(),
                'code' => $plan[0],
                'name' => $plan[1],
                'monthly_price' => $plan[2],
                'setup_price' => $plan[3],
                'max_users' => $plan[4],
                'max_members' => $plan[5],
                'features' => $plan[6],
            ]);
        }

        $pdo->exec(
            'UPDATE saas_plans
             SET name = "Prueba",
                 monthly_price = 0,
                 setup_price = 0,
                 status = "ACTIVE",
                 features = "Plan de prueba configurable sin cobro ni renovacion automatica.",
                 updated_at = NOW()
             WHERE code = "TRIAL"'
        );
    }

    private static function planParams(array $data): array
    {
        $monthlyPrice = str_replace(',', '.', (string) ($data['monthly_price'] ?? '0'));
        $setupPrice = str_replace(',', '.', (string) ($data['setup_price'] ?? '0'));
        $discountPrice = str_replace(',', '.', trim((string) ($data['discount_price'] ?? '')));
        $status = in_array($data['status'] ?? '', ['ACTIVE', 'INACTIVE', 'ARCHIVED'], true) ? $data['status'] : 'ACTIVE';
        $monthly = max(0, (float) $monthlyPrice);
        $discount = $discountPrice !== '' ? max(0, (float) $discountPrice) : null;
        if ($discount !== null && ($discount <= 0 || $discount >= $monthly)) {
            $discount = null;
        }

        return [
            'code' => strtoupper(preg_replace('/[^A-Z0-9_]/', '', trim((string) ($data['code'] ?? '')))) ?: 'CUSTOM',
            'name' => trim((string) ($data['name'] ?? '')),
            'monthly_price' => number_format($monthly, 2, '.', ''),
            'setup_price' => number_format(max(0, (float) $setupPrice), 2, '.', ''),
            'discount_price' => $discount !== null ? number_format($discount, 2, '.', '') : null,
            'discount_label' => trim((string) ($data['discount_label'] ?? '')) ?: null,
            'stripe_monthly_price_id' => trim((string) ($data['stripe_monthly_price_id'] ?? '')) ?: null,
            'stripe_annual_price_id' => trim((string) ($data['stripe_annual_price_id'] ?? '')) ?: null,
            'max_users' => trim((string) ($data['max_users'] ?? '')) !== '' ? max(0, (int) $data['max_users']) : null,
            'max_members' => trim((string) ($data['max_members'] ?? '')) !== '' ? max(0, (int) $data['max_members']) : null,
            'status' => $status,
            'features' => trim((string) ($data['features'] ?? '')) ?: null,
        ];
    }

    public static function publicPlans(): array
    {
        $plans = [];
        foreach (self::all('', 'ACTIVE') as $plan) {
            if (strtoupper((string) $plan['code']) === 'TRIAL') {
                continue;
            }

            $effectivePrice = self::effectiveMonthlyPrice($plan);
            $monthlyPrice = (float) $plan['monthly_price'];
            $features = array_values(array_filter(array_map('trim', preg_split('/[\r\n]+|;/', (string) ($plan['features'] ?? '')) ?: [])));
            $plans[] = [
                'code' => $plan['code'],
                'name' => $plan['name'],
                'monthly_price' => number_format($effectivePrice, 2, '.', ''),
                'original_monthly_price' => $effectivePrice < $monthlyPrice ? number_format($monthlyPrice, 2, '.', '') : null,
                'setup_price' => number_format((float) $plan['setup_price'], 2, '.', ''),
                'discount_label' => $effectivePrice < $monthlyPrice ? ($plan['discount_label'] ?: 'Oferta activa') : null,
                'max_users' => $plan['max_users'] !== null ? (int) $plan['max_users'] : null,
                'max_members' => $plan['max_members'] !== null ? (int) $plan['max_members'] : null,
                'features' => $features,
            ];
        }

        return $plans;
    }

    private static function effectiveMonthlyPrice(array $plan): float
    {
        $monthlyPrice = (float) ($plan['monthly_price'] ?? 0);
        $discountPrice = isset($plan['discount_price']) ? (float) $plan['discount_price'] : 0.0;

        return $discountPrice > 0 && $discountPrice < $monthlyPrice ? $discountPrice : $monthlyPrice;
    }

    private static function ensureColumn(string $column, string $sql): void
    {
        $stmt = Database::connection()->query('SHOW COLUMNS FROM saas_plans LIKE ' . Database::connection()->quote($column));
        if (!$stmt->fetch()) {
            Database::connection()->exec($sql);
        }
    }
}

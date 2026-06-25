<?php

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/View.php';

Actions::handle();

$route = $_GET['route'] ?? 'dashboard';

if ($route === 'login') {
    if (Auth::user()) {
        redirect(is_platform_admin(Auth::user()) ? 'platform-dashboard' : 'dashboard');
    }

    render('login');
    exit;
}

$currentUser = Auth::requireUser();

if ($route === 'global-search') {
    $query = trim((string) ($_GET['q'] ?? ''));
    $items = [];
    if (is_platform_admin($currentUser)) {
        foreach (array_slice(EmpresaRepository::all($query), 0, 10) as $empresa) {
            $items[] = [
                'type' => 'Empresa',
                'kind' => 'empresa',
                'title' => $empresa['name'],
                'description' => empresa_status_label($empresa['status']) . ' - ' . empresa_payment_status_label($empresa['payment_status']),
                'href' => 'index.php?route=platform-companies&q=' . urlencode($query),
            ];
        }
        foreach (array_slice(PlatformPaymentRepository::all($query), 0, 5) as $payment) {
            $items[] = [
                'type' => 'Pago',
                'kind' => 'payment',
                'title' => $payment['concept'],
                'description' => $payment['empresa_name'] . ' - ' . platform_payment_status_label($payment['status']),
                'href' => 'index.php?route=platform-payments&q=' . urlencode($query),
            ];
        }
        foreach (array_slice(PlatformPlanRepository::all($query), 0, 5) as $plan) {
            $items[] = [
                'type' => 'Plan',
                'kind' => 'plan',
                'title' => $plan['name'],
                'description' => money_amount($plan['monthly_price']) . ' / mes',
                'href' => 'index.php?route=platform-plans&q=' . urlencode($query),
            ];
        }
    } else {
        $items = GlobalSearchRepository::autocomplete(Auth::tenantId(), $query);
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'query' => $query,
        'items' => $items,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

switch ($route) {
    case 'platform-dashboard':
        if (!is_platform_admin($currentUser)) {
            redirect('dashboard');
        }

        $allEmpresas = EmpresaRepository::all();
        $filters = [
            'q' => '',
            'status' => '',
            'payment_status' => '',
        ];
        render_layout('Admin CRM', 'platform-dashboard', [
            'filters' => $filters,
            'metrics' => EmpresaRepository::metrics(),
            'paymentMetrics' => PlatformPaymentRepository::metrics(),
            'planMetrics' => PlatformPlanRepository::metrics(),
            'allEmpresas' => $allEmpresas,
            'empresas' => array_slice($allEmpresas, 0, 6),
            'payments' => array_slice(PlatformPaymentRepository::all(), 0, 6),
        ]);
        break;

    case 'platform-companies':
        if (!is_platform_admin($currentUser)) {
            redirect('dashboard');
        }

        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? '')),
            'payment_status' => trim((string) ($_GET['payment_status'] ?? '')),
        ];
        render_layout('Empresas', 'platform-companies', [
            'filters' => $filters,
            'metrics' => EmpresaRepository::metrics(),
            'planOptions' => PlatformPlanRepository::options(),
            'allEmpresas' => EmpresaRepository::all(),
            'empresas' => EmpresaRepository::all($filters['q'], $filters['status'], $filters['payment_status']),
        ]);
        break;

    case 'platform-payments':
        if (!is_platform_admin($currentUser)) {
            redirect('dashboard');
        }

        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? '')),
        ];
        render_layout('Pagos CRM', 'platform-payments', [
            'filters' => $filters,
            'metrics' => PlatformPaymentRepository::metrics(),
            'empresas' => EmpresaRepository::all(),
            'payments' => PlatformPaymentRepository::all($filters['q'], $filters['status']),
        ]);
        break;

    case 'platform-plans':
        if (!is_platform_admin($currentUser)) {
            redirect('dashboard');
        }

        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? '')),
        ];
        render_layout('Planes CRM', 'platform-plans', [
            'filters' => $filters,
            'metrics' => PlatformPlanRepository::metrics(),
            'plans' => PlatformPlanRepository::all($filters['q'], $filters['status']),
        ]);
        break;

    case 'dashboard':
        if (is_platform_admin($currentUser)) {
            redirect('platform-dashboard');
        }

        $tenantId = Auth::tenantId();
        render_layout('Panel', 'dashboard', [
            'summary' => DashboardRepository::summary($tenantId),
        ]);
        break;

    case 'leads':
        $tenantId = Auth::tenantId();
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'stage' => trim((string) ($_GET['stage'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? '')),
            'date_from' => trim((string) ($_GET['date_from'] ?? '')),
            'date_to' => trim((string) ($_GET['date_to'] ?? '')),
        ];
        $leads = LeadRepository::all(
            $tenantId,
            $filters['q'],
            $filters['stage'],
            $filters['status'],
            $filters['date_from'],
            $filters['date_to']
        );
        render_layout('Leads', 'leads', [
            'filters' => $filters,
            'stages' => PipelineRepository::all($tenantId),
            'metrics' => LeadRepository::metrics($tenantId),
            'leads' => $leads,
            'leadNotes' => LeadRepository::notesByLeadIds($tenantId, array_column($leads, 'id')),
        ]);
        break;

    case 'tasks':
        $tenantId = Auth::tenantId();
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? '')),
            'type' => trim((string) ($_GET['type'] ?? '')),
            'assigned_user_id' => trim((string) ($_GET['assigned_user_id'] ?? '')),
            'date_from' => trim((string) ($_GET['date_from'] ?? '')),
            'date_to' => trim((string) ($_GET['date_to'] ?? '')),
        ];
        render_layout('Tareas', 'tasks', [
            'filters' => $filters,
            'staff' => StaffRepository::all($tenantId),
            'members' => MemberRepository::all($tenantId),
            'metrics' => TaskRepository::metrics($tenantId),
            'tasks' => TaskRepository::all(
                $tenantId,
                $filters['q'],
                $filters['status'],
                $filters['type'],
                $filters['assigned_user_id'],
                $filters['date_from'],
                $filters['date_to']
            ),
        ]);
        break;

    case 'users':
        $tenantId = Auth::tenantId();
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'role_id' => trim((string) ($_GET['role_id'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? '')),
        ];
        render_layout('Usuarios', 'users', [
            'filters' => $filters,
            'roles' => UserRepository::roles(),
            'metrics' => UserRepository::metrics($tenantId),
            'users' => UserRepository::all($tenantId, $filters['q'], $filters['role_id'], $filters['status']),
        ]);
        break;

    case 'profile':
        render_layout('Mi perfil', 'profile', []);
        break;

    case 'settings':
        render_layout('Configuracion', 'settings', []);
        break;

    case 'members':
        $tenantId = Auth::tenantId();
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? '')),
            'date_from' => trim((string) ($_GET['date_from'] ?? '')),
            'date_to' => trim((string) ($_GET['date_to'] ?? '')),
        ];
        $members = MemberRepository::all(
            $tenantId,
            $filters['q'],
            $filters['status'],
            $filters['date_from'],
            $filters['date_to']
        );
        render_layout('Socios', 'members', [
            'filters' => $filters,
            'metrics' => MemberRepository::metrics($tenantId),
            'members' => $members,
            'membershipPlans' => MembershipRepository::plans($tenantId, '', 'ACTIVE'),
        ]);
        break;

    case 'memberships':
        $tenantId = Auth::tenantId();
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? '')),
        ];
        render_layout('Membresias', 'memberships', [
            'filters' => $filters,
            'metrics' => MembershipRepository::metrics($tenantId),
            'plans' => MembershipRepository::plans($tenantId, $filters['q'], $filters['status']),
            'subscriptions' => MembershipRepository::subscriptions($tenantId, $filters['q']),
        ]);
        break;

    case 'classes':
        $tenantId = Auth::tenantId();
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'type' => trim((string) ($_GET['type'] ?? '')),
            'date_from' => trim((string) ($_GET['date_from'] ?? date('Y-m-d'))),
            'date_to' => trim((string) ($_GET['date_to'] ?? date('Y-m-d', strtotime('+14 days')))),
            'month' => trim((string) ($_GET['month'] ?? date('Y-m'))),
        ];
        render_layout('Clases', 'classes', [
            'filters' => $filters,
            'staff' => StaffRepository::all($tenantId),
            'classTypes' => ClassRepository::types($tenantId),
            'activeClassTypes' => ClassRepository::types($tenantId, true),
            'metrics' => ClassRepository::metrics($tenantId),
            'sessions' => ClassRepository::sessions(
                $tenantId,
                $filters['q'],
                $filters['type'],
                $filters['date_from'],
                $filters['date_to']
            ),
            'calendar' => ClassRepository::calendar($tenantId, $filters['month']),
        ]);
        break;

    default:
        redirect('dashboard');
}

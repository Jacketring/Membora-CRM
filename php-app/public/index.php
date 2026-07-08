<?php

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/View.php';

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$isPublicPlansRequest = $requestPath === '/api/plans' || ($_GET['action'] ?? '') === 'public_plans';
if ($isPublicPlansRequest) {
    $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
    $allowedOrigins = array_filter([
        rtrim((string) (getenv('WEB_APP_URL') ?: 'https://app.web.josehurtado.dev'), '/'),
        rtrim((string) (getenv('APP_WEB_URL') ?: ''), '/'),
    ]);
    if ($origin !== '' && in_array(rtrim($origin, '/'), $allowedOrigins, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Headers: Content-Type');
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        header('Vary: Origin');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        header('Content-Type: application/json; charset=utf-8', true, 405);
        echo json_encode(['success' => false, 'message' => 'Metodo no permitido'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode([
        'success' => true,
        'currency' => 'EUR',
        'plans' => PlatformPlanRepository::publicPlans(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$isWebhookLeadRequest = $requestPath === '/webhook/lead' || ($_GET['action'] ?? '') === 'webhook_lead';
if ($isWebhookLeadRequest) {
    $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
    $allowedOrigins = array_filter([
        rtrim((string) (getenv('WEB_APP_URL') ?: 'https://app.web.josehurtado.dev'), '/'),
        rtrim((string) (getenv('APP_WEB_URL') ?: ''), '/'),
    ]);
    if ($origin !== '' && in_array(rtrim($origin, '/'), $allowedOrigins, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Headers: Content-Type');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Vary: Origin');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Content-Type: application/json; charset=utf-8', true, 405);
        echo json_encode(['success' => false, 'message' => 'Metodo no permitido'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    $payload = $_POST;
    if (str_contains($contentType, 'application/json')) {
        $raw = file_get_contents('php://input') ?: '';
        $decoded = json_decode($raw, true);
        $payload = is_array($decoded) ? $decoded : [];
    }

    $result = WebhookIntegrationRepository::handleIncoming($payload, $_SERVER['HTTP_X_MEMBORA_TOKEN'] ?? null);
    header('Content-Type: application/json; charset=utf-8', true, !empty($result['success']) ? 200 : 400);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

$postAction = $_SERVER['REQUEST_METHOD'] === 'POST' ? (string) ($_POST['action'] ?? '') : '';
if (!in_array($postAction, ['login', 'demo_login'], true)) {
    Auth::enforceDemoExpiry();
}

Actions::handle();

$route = $_GET['route'] ?? 'dashboard';

if ($route === 'demo-expired') {
    Auth::logout();
    header('Location: ' . Auth::demoReturnUrl());
    exit;
}

Auth::enforceDemoExpiry();

if ($route === 'login') {
    if (Auth::user()) {
        redirect(is_platform_admin(Auth::user()) ? 'platform-dashboard' : 'dashboard');
    }

    render('login');
    exit;
}

$currentUser = Auth::requireUser();

if (!can_access_route((string) $route, $currentUser)) {
    flash('No tienes permisos para acceder a esta seccion.', 'error');
    redirect(is_platform_admin($currentUser) ? 'platform-dashboard' : 'dashboard');
}

if ($route === 'global-search') {
    $query = trim((string) ($_GET['q'] ?? ''));
    $items = [];
    if (is_platform_admin($currentUser)) {
        foreach (array_slice(PlatformLeadRepository::all($query), 0, 8) as $lead) {
            $items[] = [
                'type' => 'Lead',
                'kind' => 'lead',
                'title' => $lead['company_name'] ?: $lead['contact_name'],
                'description' => platform_lead_status_label($lead['status']) . ' - ' . ($lead['email'] ?: ($lead['phone'] ?: 'Sin contacto')),
                'href' => 'index.php?route=platform-contacts&q=' . urlencode($query) . '&type=lead',
            ];
        }
        foreach (array_slice(PlatformClientRepository::all($query), 0, 8) as $client) {
            $items[] = [
                'type' => 'Cliente',
                'kind' => 'client',
                'title' => $client['company_name'],
                'description' => platform_client_status_label($client['status']) . ' - ' . ($client['email'] ?: 'Sin email'),
                'href' => 'index.php?route=platform-contacts&q=' . urlencode($query) . '&type=client',
            ];
        }
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

if ($route === 'billing-export') {
    $tenantId = Auth::tenantId();
    $csv = BillingIntegrationRepository::exportCsv($tenantId);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="membora-pagos-' . date('Ymd-His') . '.csv"');
    echo $csv;
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

    case 'platform-clients':
    case 'platform-leads':
        redirect('platform-contacts');

    case 'platform-contacts':
        if (!is_platform_admin($currentUser)) {
            redirect('dashboard');
        }

        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? '')),
            'type' => trim((string) ($_GET['type'] ?? '')),
        ];
        render_layout('Contactos CRM', 'platform-contacts', [
            'filters' => $filters,
            'metrics' => PlatformContactRepository::metrics(),
            'contacts' => PlatformContactRepository::all($filters['q'], $filters['status'], $filters['type']),
            'clients' => PlatformClientRepository::all($filters['q'], $filters['status']),
            'leads' => PlatformLeadRepository::all($filters['q'], $filters['status']),
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
        $selectedClientId = trim((string) ($_GET['client_id'] ?? ''));
        render_layout('Empresas', 'platform-companies', [
            'filters' => $filters,
            'metrics' => EmpresaRepository::metrics(),
            'planOptions' => PlatformPlanRepository::options(),
            'planPrices' => PlatformPlanRepository::priceMap(),
            'clients' => PlatformClientRepository::all(),
            'selectedClient' => $selectedClientId !== '' ? PlatformClientRepository::find($selectedClientId) : null,
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

    case 'platform-payment-invoice':
        if (!is_platform_admin($currentUser)) {
            redirect('dashboard');
        }

        $payment = PlatformPaymentRepository::findWithEmpresa(trim((string) ($_GET['id'] ?? '')));
        if (!$payment) {
            flash('No se encontro el pago para generar la factura.', 'error');
            redirect('platform-payments');
        }

        render('platform-payment-invoice', [
            'payment' => $payment,
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

    case 'platform-web':
        if (!is_platform_admin($currentUser)) {
            redirect('dashboard');
        }

        redirect('platform-dashboard');

    case 'platform-audit':
        if (!is_platform_admin($currentUser)) {
            redirect('dashboard');
        }

        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'tenant_id' => trim((string) ($_GET['tenant_id'] ?? '')),
            'action' => trim((string) ($_GET['action_filter'] ?? '')),
            'date_from' => trim((string) ($_GET['date_from'] ?? '')),
            'date_to' => trim((string) ($_GET['date_to'] ?? '')),
        ];
        try {
            AuditLogRepository::record('view_audit', ['scope' => 'platform', 'filters' => $filters]);
        } catch (Throwable $exception) {
            $_SESSION['audit_log_error'] = $exception->getMessage();
        }
        if (!empty($_SESSION['audit_log_error'])) {
            flash('La auditoria no se esta guardando: ' . $_SESSION['audit_log_error'], 'error');
            unset($_SESSION['audit_log_error']);
        }
        render_layout('Logs CRM', 'platform-audit', [
            'filters' => $filters,
            'metrics' => AuditLogRepository::platformMetrics($filters['tenant_id']),
            'tenantOptions' => AuditLogRepository::tenantOptions(),
            'actionOptions' => AuditLogRepository::platformActionOptions($filters['tenant_id']),
            'logs' => AuditLogRepository::platformAll($filters['tenant_id'], $filters['q'], $filters['action'], $filters['date_from'], $filters['date_to']),
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
            'source' => trim((string) ($_GET['source'] ?? '')),
            'date_from' => trim((string) ($_GET['date_from'] ?? '')),
            'date_to' => trim((string) ($_GET['date_to'] ?? '')),
        ];
        $leads = LeadRepository::all(
            $tenantId,
            $filters['q'],
            $filters['stage'],
            $filters['status'],
            $filters['source'],
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

    case 'novedades':
        render_layout('Novedades', 'novedades', []);
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
            'reservationHistory' => ReservationRepository::byMemberIds($tenantId, array_column($members, 'id')),
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

    case 'payments':
        $tenantId = Auth::tenantId();
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? '')),
            'date_from' => trim((string) ($_GET['date_from'] ?? '')),
            'date_to' => trim((string) ($_GET['date_to'] ?? '')),
        ];
        render_layout('Pagos', 'payments', [
            'filters' => $filters,
            'metrics' => PaymentRepository::metrics($tenantId),
            'payments' => PaymentRepository::all($tenantId, $filters['q'], $filters['status'], $filters['date_from'], $filters['date_to']),
            'members' => PaymentRepository::memberOptions($tenantId),
            'subscriptions' => PaymentRepository::subscriptionOptions($tenantId),
        ]);
        break;

    case 'payment-invoice':
        $tenantId = Auth::tenantId();
        $payment = PaymentRepository::findWithMember($tenantId, trim((string) ($_GET['id'] ?? '')));
        if (!$payment) {
            flash('No se encontro el pago para generar la factura.', 'error');
            redirect('payments');
        }

        render('payment-invoice', [
            'payment' => $payment,
        ]);
        break;

    case 'billing':
        $tenantId = Auth::tenantId();
        render_layout('Facturacion', 'billing', [
            'settings' => BillingIntegrationRepository::settings($tenantId),
            'metrics' => BillingIntegrationRepository::metrics($tenantId),
            'payments' => BillingIntegrationRepository::eligiblePayments($tenantId),
            'logs' => BillingIntegrationRepository::logs($tenantId),
        ]);
        break;

    case 'checkins':
        $tenantId = Auth::tenantId();
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'date_from' => trim((string) ($_GET['date_from'] ?? '')),
            'date_to' => trim((string) ($_GET['date_to'] ?? '')),
        ];
        render_layout('Check-ins', 'checkins', [
            'filters' => $filters,
            'metrics' => CheckinRepository::metrics($tenantId),
            'checkins' => CheckinRepository::all($tenantId, $filters['q'], $filters['date_from'], $filters['date_to']),
            'members' => CheckinRepository::memberOptions($tenantId),
            'reservations' => CheckinRepository::reservationOptions($tenantId),
        ]);
        break;

    case 'alerts':
        $tenantId = Auth::tenantId();
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? 'OPEN')),
            'type' => trim((string) ($_GET['type'] ?? '')),
        ];
        render_layout('Alertas', 'alerts', [
            'filters' => $filters,
            'metrics' => RiskAlertRepository::metrics($tenantId),
            'typeOptions' => RiskAlertRepository::typeOptions(),
            'alerts' => RiskAlertRepository::all($tenantId, $filters['q'], $filters['status'], $filters['type']),
        ]);
        break;

    case 'audit':
        $tenantId = Auth::tenantId();
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'action' => trim((string) ($_GET['action_filter'] ?? '')),
            'user_id' => trim((string) ($_GET['user_id'] ?? '')),
            'date_from' => trim((string) ($_GET['date_from'] ?? '')),
            'date_to' => trim((string) ($_GET['date_to'] ?? '')),
        ];
        try {
            AuditLogRepository::record('view_audit', ['scope' => 'tenant', 'filters' => $filters]);
        } catch (Throwable $exception) {
            $_SESSION['audit_log_error'] = $exception->getMessage();
        }
        if (!empty($_SESSION['audit_log_error'])) {
            flash('La auditoria no se esta guardando: ' . $_SESSION['audit_log_error'], 'error');
            unset($_SESSION['audit_log_error']);
        }
        render_layout('Auditoria', 'audit', [
            'filters' => $filters,
            'metrics' => AuditLogRepository::metrics($tenantId),
            'actionOptions' => AuditLogRepository::actionOptions($tenantId),
            'staff' => StaffRepository::all($tenantId),
            'logs' => AuditLogRepository::all($tenantId, $filters['q'], $filters['action'], $filters['user_id'], $filters['date_from'], $filters['date_to']),
        ]);
        break;

    case 'classes':
        $tenantId = Auth::tenantId();
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'type' => trim((string) ($_GET['type'] ?? '')),
            'date_from' => trim((string) ($_GET['date_from'] ?? '')),
            'date_to' => trim((string) ($_GET['date_to'] ?? '')),
            'month' => trim((string) ($_GET['month'] ?? date('Y-m'))),
        ];
        $sessions = ClassRepository::sessions(
            $tenantId,
            $filters['q'],
            $filters['type'],
            $filters['date_from'],
            $filters['date_to']
        );
        render_layout('Clases', 'classes', [
            'filters' => $filters,
            'staff' => StaffRepository::all($tenantId),
            'members' => MemberRepository::all($tenantId, '', 'ACTIVE'),
            'classTypes' => ClassRepository::types($tenantId),
            'activeClassTypes' => ClassRepository::types($tenantId, true),
            'metrics' => ClassRepository::metrics($tenantId),
            'sessions' => $sessions,
            'reservationsBySession' => ReservationRepository::bySessionIds($tenantId, array_column($sessions, 'id')),
            'calendar' => ClassRepository::calendar($tenantId, $filters['month']),
        ]);
        break;

    default:
        redirect('dashboard');
}

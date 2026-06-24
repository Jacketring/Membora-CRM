<?php

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/View.php';

Actions::handle();

$route = $_GET['route'] ?? 'dashboard';

if ($route === 'login') {
    if (Auth::user()) {
        redirect('dashboard');
    }

    render('login');
    exit;
}

Auth::requireUser();
$tenantId = Auth::tenantId();

if ($route === 'global-search') {
    $query = trim((string) ($_GET['q'] ?? ''));
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'query' => $query,
        'items' => GlobalSearchRepository::autocomplete($tenantId, $query),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

switch ($route) {
    case 'dashboard':
        render_layout('Panel', 'dashboard', [
            'summary' => DashboardRepository::summary($tenantId),
        ]);
        break;

    case 'leads':
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

    case 'members':
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
        ]);
        break;

    default:
        redirect('dashboard');
}

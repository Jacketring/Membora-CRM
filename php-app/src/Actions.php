<?php

final class Actions
{
    public static function handle(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $action = post_value('action', '');

        match ($action) {
            'login' => self::login(),
            'logout' => self::logout(),
            'create_lead' => self::createLead(),
            'update_lead_stage' => self::updateLeadStage(),
            'convert_lead' => self::convertLead(),
            'mark_lead_lost' => self::markLeadLost(),
            'delete_lead' => self::deleteLead(),
            'create_task' => self::createTask(),
            'update_task_status' => self::updateTaskStatus(),
            'delete_task' => self::deleteTask(),
            default => null,
        };
    }

    private static function login(): never
    {
        $email = post_value('email', '');
        $password = post_value('password', '');

        if (Auth::attempt($email, $password)) {
            redirect('dashboard');
        }

        flash('Credenciales incorrectas.', 'error');
        redirect('login');
    }

    private static function logout(): never
    {
        Auth::logout();
        redirect('login');
    }

    private static function createLead(): never
    {
        $tenantId = Auth::tenantId();
        $stageId = post_value('pipeline_stage_id') ?: PipelineRepository::firstId($tenantId);

        $stmt = Database::connection()->prepare(
            'INSERT INTO leads (id, tenant_id, pipeline_stage_id, assigned_user_id, first_name, last_name, email, phone, source, interest, status, created_at, updated_at)
             VALUES (:id, :tenant_id, :stage_id, :assigned_user_id, :first_name, :last_name, :email, :phone, :source, :interest, "OPEN", NOW(), NOW())'
        );
        $stmt->execute([
            'id' => cuid(),
            'tenant_id' => $tenantId,
            'stage_id' => $stageId,
            'assigned_user_id' => Auth::user()['id'] ?? null,
            'first_name' => post_value('first_name', ''),
            'last_name' => post_value('last_name') ?: null,
            'email' => post_value('email') ?: null,
            'phone' => post_value('phone') ?: null,
            'source' => post_value('source', 'OTHER'),
            'interest' => post_value('interest') ?: null,
        ]);

        flash('Lead creado correctamente.');
        redirect('leads');
    }

    private static function updateLeadStage(): never
    {
        $stmt = Database::connection()->prepare(
            'UPDATE leads SET pipeline_stage_id = :stage_id, updated_at = NOW()
             WHERE id = :id AND tenant_id = :tenant_id'
        );
        $stmt->execute([
            'stage_id' => post_value('pipeline_stage_id'),
            'id' => post_value('id'),
            'tenant_id' => Auth::tenantId(),
        ]);
        redirect('leads');
    }

    private static function convertLead(): never
    {
        $pdo = Database::connection();
        $tenantId = Auth::tenantId();
        $leadId = post_value('id');

        $leadStmt = $pdo->prepare('SELECT * FROM leads WHERE id = :id AND tenant_id = :tenant_id LIMIT 1');
        $leadStmt->execute(['id' => $leadId, 'tenant_id' => $tenantId]);
        $lead = $leadStmt->fetch();

        if ($lead) {
            $memberId = cuid();
            $insert = $pdo->prepare(
                'INSERT INTO members (id, tenant_id, lead_id, first_name, last_name, email, phone, status, joined_at, created_at, updated_at)
                 VALUES (:id, :tenant_id, :lead_id, :first_name, :last_name, :email, :phone, "ACTIVE", NOW(), NOW(), NOW())'
            );
            $insert->execute([
                'id' => $memberId,
                'tenant_id' => $tenantId,
                'lead_id' => $leadId,
                'first_name' => $lead['first_name'],
                'last_name' => $lead['last_name'],
                'email' => $lead['email'],
                'phone' => $lead['phone'],
            ]);
            $update = $pdo->prepare('UPDATE leads SET status = "CONVERTED", updated_at = NOW() WHERE id = :id');
            $update->execute(['id' => $leadId]);
            flash('Lead convertido a socio.');
        }

        redirect('leads');
    }

    private static function markLeadLost(): never
    {
        $tenantId = Auth::tenantId();
        $lostStageId = PipelineRepository::lostId($tenantId);
        $stmt = Database::connection()->prepare(
            'UPDATE leads SET status = "LOST", pipeline_stage_id = COALESCE(:stage_id, pipeline_stage_id), updated_at = NOW()
             WHERE id = :id AND tenant_id = :tenant_id'
        );
        $stmt->execute([
            'stage_id' => $lostStageId,
            'id' => post_value('id'),
            'tenant_id' => $tenantId,
        ]);
        redirect('leads');
    }

    private static function deleteLead(): never
    {
        $stmt = Database::connection()->prepare('DELETE FROM leads WHERE id = :id AND tenant_id = :tenant_id');
        $stmt->execute(['id' => post_value('id'), 'tenant_id' => Auth::tenantId()]);
        flash('Lead eliminado.');
        redirect('leads');
    }

    private static function createTask(): never
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO tasks (id, tenant_id, assigned_user_id, title, description, type, status, due_at, created_at, updated_at)
             VALUES (:id, :tenant_id, :assigned_user_id, :title, :description, :type, "PENDING", :due_at, NOW(), NOW())'
        );
        $stmt->execute([
            'id' => cuid(),
            'tenant_id' => Auth::tenantId(),
            'assigned_user_id' => post_value('assigned_user_id') ?: null,
            'title' => post_value('title', ''),
            'description' => post_value('description') ?: null,
            'type' => post_value('type', 'OTHER'),
            'due_at' => post_value('due_at') ?: null,
        ]);
        flash('Tarea creada correctamente.');
        redirect('tasks');
    }

    private static function updateTaskStatus(): never
    {
        $status = post_value('status', 'PENDING');
        $stmt = Database::connection()->prepare(
            'UPDATE tasks SET status = :status, completed_at = IF(:status_done = "COMPLETED", NOW(), completed_at), updated_at = NOW()
             WHERE id = :id AND tenant_id = :tenant_id'
        );
        $stmt->execute([
            'status' => $status,
            'status_done' => $status,
            'id' => post_value('id'),
            'tenant_id' => Auth::tenantId(),
        ]);
        redirect('tasks');
    }

    private static function deleteTask(): never
    {
        $pdo = Database::connection();
        $taskId = post_value('id');
        $tenantId = Auth::tenantId();
        $alerts = $pdo->prepare('DELETE FROM risk_alerts WHERE task_id = :id AND tenant_id = :tenant_id');
        $alerts->execute(['id' => $taskId, 'tenant_id' => $tenantId]);
        $stmt = $pdo->prepare('DELETE FROM tasks WHERE id = :id AND tenant_id = :tenant_id');
        $stmt->execute(['id' => $taskId, 'tenant_id' => $tenantId]);
        redirect('tasks');
    }
}

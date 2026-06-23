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
            'update_lead' => self::updateLead(),
            'add_lead_note' => self::addLeadNote(),
            'update_lead_note' => self::updateLeadNote(),
            'delete_lead_note' => self::deleteLeadNote(),
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

        try {
            if (Auth::attempt($email, $password)) {
                redirect('dashboard');
            }
        } catch (Throwable $exception) {
            flash('No se pudo conectar con la base de datos. Revisa php-app/.env.', 'error');
            redirect('login');
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
        $firstName = post_value('first_name', '');

        if (!$stageId || $firstName === '') {
            flash('Indica al menos nombre y etapa.', 'error');
            redirect('leads');
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO leads (id, tenant_id, pipeline_stage_id, assigned_user_id, first_name, last_name, email, phone, source, interest, status, created_at, updated_at)
             VALUES (:id, :tenant_id, :stage_id, :assigned_user_id, :first_name, :last_name, :email, :phone, :source, :interest, "OPEN", NOW(), NOW())'
        );
        $stmt->execute([
            'id' => cuid(),
            'tenant_id' => $tenantId,
            'stage_id' => $stageId,
            'assigned_user_id' => Auth::user()['id'] ?? null,
            'first_name' => $firstName,
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

    private static function updateLead(): never
    {
        $stmt = Database::connection()->prepare(
            'UPDATE leads
             SET first_name = :first_name,
                 last_name = :last_name,
                 phone = :phone,
                 email = :email,
                 source = :source,
                 interest = :interest,
                 pipeline_stage_id = :pipeline_stage_id,
                 next_action_at = :next_action_at,
                 updated_at = NOW()
             WHERE id = :id AND tenant_id = :tenant_id'
        );
        $stmt->execute([
            'first_name' => post_value('first_name', ''),
            'last_name' => post_value('last_name') ?: null,
            'phone' => post_value('phone') ?: null,
            'email' => post_value('email') ?: null,
            'source' => post_value('source', 'OTHER'),
            'interest' => post_value('interest') ?: null,
            'pipeline_stage_id' => post_value('pipeline_stage_id'),
            'next_action_at' => post_value('next_action_at') ?: null,
            'id' => post_value('id'),
            'tenant_id' => Auth::tenantId(),
        ]);

        flash('Lead actualizado correctamente.');
        redirect('leads');
    }

    private static function addLeadNote(): never
    {
        $note = post_value('note', '');
        if ($note === '') {
            flash('La nota no puede estar vacia.', 'error');
            redirect('leads');
        }

        LeadRepository::ensureNotesTable();
        $stmt = Database::connection()->prepare(
            'INSERT INTO lead_notes (id, tenant_id, lead_id, user_id, note, created_at)
             VALUES (:id, :tenant_id, :lead_id, :user_id, :note, NOW())'
        );
        $stmt->execute([
            'id' => cuid(),
            'tenant_id' => Auth::tenantId(),
            'lead_id' => post_value('id'),
            'user_id' => Auth::user()['id'] ?? null,
            'note' => $note,
        ]);

        flash('Nota anadida correctamente.');
        redirect('leads');
    }

    private static function updateLeadNote(): never
    {
        $note = post_value('note', '');
        if ($note === '') {
            flash('La nota no puede quedar vacia.', 'error');
            redirect('leads');
        }

        LeadRepository::ensureNotesTable();
        $stmt = Database::connection()->prepare(
            'UPDATE lead_notes
             SET note = :note
             WHERE id = :id AND tenant_id = :tenant_id'
        );
        $stmt->execute([
            'note' => $note,
            'id' => post_value('note_id'),
            'tenant_id' => Auth::tenantId(),
        ]);

        flash('Nota actualizada correctamente.');
        redirect('leads');
    }

    private static function deleteLeadNote(): never
    {
        LeadRepository::ensureNotesTable();
        $stmt = Database::connection()->prepare(
            'DELETE FROM lead_notes WHERE id = :id AND tenant_id = :tenant_id'
        );
        $stmt->execute([
            'id' => post_value('note_id'),
            'tenant_id' => Auth::tenantId(),
        ]);

        flash('Nota eliminada correctamente.');
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

        if ($lead && $lead['status'] !== 'CONVERTED') {
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
        } else {
            flash('El lead ya estaba convertido o no existe.', 'error');
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
        $pdo = Database::connection();
        $leadId = post_value('id');
        $tenantId = Auth::tenantId();

        $pdo->beginTransaction();
        try {
            $deleteAlerts = $pdo->prepare('DELETE FROM risk_alerts WHERE lead_id = :id AND tenant_id = :tenant_id');
            $deleteAlerts->execute(['id' => $leadId, 'tenant_id' => $tenantId]);

            $deleteTasks = $pdo->prepare('DELETE FROM tasks WHERE lead_id = :id AND tenant_id = :tenant_id');
            $deleteTasks->execute(['id' => $leadId, 'tenant_id' => $tenantId]);

            LeadRepository::ensureNotesTable();
            $deleteNotes = $pdo->prepare('DELETE FROM lead_notes WHERE lead_id = :id AND tenant_id = :tenant_id');
            $deleteNotes->execute(['id' => $leadId, 'tenant_id' => $tenantId]);

            $unlinkMembers = $pdo->prepare('UPDATE members SET lead_id = NULL, updated_at = NOW() WHERE lead_id = :id AND tenant_id = :tenant_id');
            $unlinkMembers->execute(['id' => $leadId, 'tenant_id' => $tenantId]);

            $stmt = $pdo->prepare('DELETE FROM leads WHERE id = :id AND tenant_id = :tenant_id');
            $stmt->execute(['id' => $leadId, 'tenant_id' => $tenantId]);
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            flash('No se pudo eliminar el lead porque tiene datos relacionados.', 'error');
            redirect('leads');
        }

        flash('Lead eliminado.');
        redirect('leads');
    }

    private static function createTask(): never
    {
        $title = post_value('title', '');
        if ($title === '') {
            flash('Indica un titulo para la tarea.', 'error');
            redirect('tasks');
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO tasks (id, tenant_id, assigned_user_id, title, description, type, status, due_at, created_at, updated_at)
             VALUES (:id, :tenant_id, :assigned_user_id, :title, :description, :type, "PENDING", :due_at, NOW(), NOW())'
        );
        $stmt->execute([
            'id' => cuid(),
            'tenant_id' => Auth::tenantId(),
            'assigned_user_id' => post_value('assigned_user_id') ?: null,
            'title' => $title,
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

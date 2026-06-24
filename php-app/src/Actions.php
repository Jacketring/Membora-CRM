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
            'create_member' => self::createMember(),
            'update_member' => self::updateMember(),
            'delete_member' => self::deleteMember(),
            'create_task' => self::createTask(),
            'update_task' => self::updateTask(),
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
        $stage = $stageId ? PipelineRepository::find($tenantId, $stageId) : null;
        $status = PipelineRepository::statusForStage($stage);
        $firstName = post_value('first_name', '');

        if (!$stageId || $firstName === '') {
            flash('Indica al menos nombre y etapa.', 'error');
            redirect('leads');
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO leads (id, tenant_id, pipeline_stage_id, assigned_user_id, first_name, last_name, email, phone, source, interest, status, created_at, updated_at)
             VALUES (:id, :tenant_id, :stage_id, :assigned_user_id, :first_name, :last_name, :email, :phone, :source, :interest, :status, NOW(), NOW())'
        );
        $stmt->execute([
            'id' => cuid(),
            'tenant_id' => $tenantId,
            'stage_id' => $stageId,
            'assigned_user_id' => Auth::user()['id'] ?? null,
            'first_name' => $firstName,
            'last_name' => post_value('last_name') ?: null,
            'email' => post_value('email') ?: null,
            'phone' => phone_from_post(),
            'source' => post_value('source', 'OTHER'),
            'interest' => post_value('interest') ?: null,
            'status' => $status,
        ]);

        flash('Lead creado correctamente.');
        redirect('leads');
    }

    private static function updateLeadStage(): never
    {
        $tenantId = Auth::tenantId();
        $stageId = post_value('pipeline_stage_id');
        $stage = $stageId ? PipelineRepository::find($tenantId, $stageId) : null;
        $status = PipelineRepository::statusForStage($stage);

        $stmt = Database::connection()->prepare(
            'UPDATE leads SET pipeline_stage_id = :stage_id, status = :status, updated_at = NOW()
             WHERE id = :id AND tenant_id = :tenant_id'
        );
        $stmt->execute([
            'stage_id' => $stageId,
            'status' => $status,
            'id' => post_value('id'),
            'tenant_id' => $tenantId,
        ]);
        redirect('leads');
    }

    private static function updateLead(): never
    {
        $tenantId = Auth::tenantId();
        $stageId = post_value('pipeline_stage_id');
        $stage = $stageId ? PipelineRepository::find($tenantId, $stageId) : null;
        $status = PipelineRepository::statusForStage($stage);

        $stmt = Database::connection()->prepare(
            'UPDATE leads
             SET first_name = :first_name,
                 last_name = :last_name,
                 phone = :phone,
                 email = :email,
                 source = :source,
                 interest = :interest,
                 pipeline_stage_id = :pipeline_stage_id,
                 status = :status,
                 next_action_at = :next_action_at,
                 updated_at = NOW()
             WHERE id = :id AND tenant_id = :tenant_id'
        );
        $stmt->execute([
            'first_name' => post_value('first_name', ''),
            'last_name' => post_value('last_name') ?: null,
            'phone' => phone_from_post(),
            'email' => post_value('email') ?: null,
            'source' => post_value('source', 'OTHER'),
            'interest' => post_value('interest') ?: null,
            'pipeline_stage_id' => $stageId,
            'status' => $status,
            'next_action_at' => post_value('next_action_at') ?: null,
            'id' => post_value('id'),
            'tenant_id' => $tenantId,
        ]);

        flash('Lead actualizado correctamente.');
        redirect('leads');
    }

    private static function addLeadNote(): never
    {
        $leadId = post_value('id');
        $note = post_value('note', '');
        if ($note === '') {
            flash('La nota no puede estar vacia.', 'error');
            self::redirectLeadModal($leadId);
        }

        LeadRepository::ensureNotesTable();
        $stmt = Database::connection()->prepare(
            'INSERT INTO lead_notes (id, tenant_id, lead_id, user_id, note, created_at)
             VALUES (:id, :tenant_id, :lead_id, :user_id, :note, NOW())'
        );
        $stmt->execute([
            'id' => cuid(),
            'tenant_id' => Auth::tenantId(),
            'lead_id' => $leadId,
            'user_id' => Auth::user()['id'] ?? null,
            'note' => $note,
        ]);

        flash('Nota anadida correctamente.');
        self::redirectLeadModal($leadId);
    }

    private static function updateLeadNote(): never
    {
        $leadId = post_value('id');
        $note = post_value('note', '');
        if ($note === '') {
            flash('La nota no puede quedar vacia.', 'error');
            self::redirectLeadModal($leadId);
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
        self::redirectLeadModal($leadId);
    }

    private static function deleteLeadNote(): never
    {
        $leadId = post_value('id');
        LeadRepository::ensureNotesTable();
        $stmt = Database::connection()->prepare(
            'DELETE FROM lead_notes WHERE id = :id AND tenant_id = :tenant_id'
        );
        $stmt->execute([
            'id' => post_value('note_id'),
            'tenant_id' => Auth::tenantId(),
        ]);

        flash('Nota eliminada correctamente.');
        self::redirectLeadModal($leadId);
    }

    private static function redirectLeadModal(?string $leadId): never
    {
        if (!$leadId) {
            redirect('leads');
        }

        header('Location: index.php?route=leads&modal=' . urlencode('lead-detail-' . $leadId));
        exit;
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
            $convertedStageId = PipelineRepository::convertedId($tenantId);
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
            $update = $pdo->prepare(
                'UPDATE leads
                 SET status = "CONVERTED",
                     pipeline_stage_id = COALESCE(:stage_id, pipeline_stage_id),
                     updated_at = NOW()
                 WHERE id = :id AND tenant_id = :tenant_id'
            );
            $update->execute([
                'stage_id' => $convertedStageId,
                'id' => $leadId,
                'tenant_id' => $tenantId,
            ]);
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

        LeadRepository::ensureNotesTable();
        $pdo->beginTransaction();
        try {
            $deleteAlerts = $pdo->prepare('DELETE FROM risk_alerts WHERE lead_id = :id AND tenant_id = :tenant_id');
            $deleteAlerts->execute(['id' => $leadId, 'tenant_id' => $tenantId]);

            $deleteTasks = $pdo->prepare('DELETE FROM tasks WHERE lead_id = :id AND tenant_id = :tenant_id');
            $deleteTasks->execute(['id' => $leadId, 'tenant_id' => $tenantId]);

            $deleteNotes = $pdo->prepare('DELETE FROM lead_notes WHERE lead_id = :id AND tenant_id = :tenant_id');
            $deleteNotes->execute(['id' => $leadId, 'tenant_id' => $tenantId]);

            $unlinkMembers = $pdo->prepare('UPDATE members SET lead_id = NULL, updated_at = NOW() WHERE lead_id = :id AND tenant_id = :tenant_id');
            $unlinkMembers->execute(['id' => $leadId, 'tenant_id' => $tenantId]);

            $stmt = $pdo->prepare('DELETE FROM leads WHERE id = :id AND tenant_id = :tenant_id');
            $stmt->execute(['id' => $leadId, 'tenant_id' => $tenantId]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('No se pudo eliminar el lead porque tiene datos relacionados.', 'error');
            redirect('leads');
        }

        flash('Lead eliminado.');
        redirect('leads');
    }

    private static function createMember(): never
    {
        MemberRepository::ensurePhotoColumn();

        $firstName = post_value('first_name', '');
        if ($firstName === '') {
            flash('Indica al menos el nombre del socio.', 'error');
            redirect('members');
        }

        $status = post_value('status', 'ACTIVE');
        if (!in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
            $status = 'ACTIVE';
        }

        $photoPath = self::uploadedMemberPhotoPath();

        $stmt = Database::connection()->prepare(
            'INSERT INTO members (id, tenant_id, lead_id, first_name, last_name, email, phone, photo_path, status, joined_at, created_at, updated_at)
             VALUES (:id, :tenant_id, NULL, :first_name, :last_name, :email, :phone, :photo_path, :status, :joined_at, NOW(), NOW())'
        );
        $stmt->execute([
            'id' => cuid(),
            'tenant_id' => Auth::tenantId(),
            'first_name' => $firstName,
            'last_name' => post_value('last_name') ?: null,
            'email' => post_value('email') ?: null,
            'phone' => phone_from_post(),
            'photo_path' => $photoPath,
            'status' => $status,
            'joined_at' => post_value('joined_at') ?: date('Y-m-d'),
        ]);

        flash('Socio creado correctamente.');
        redirect('members');
    }

    private static function updateMember(): never
    {
        MemberRepository::ensurePhotoColumn();

        $firstName = post_value('first_name', '');
        if ($firstName === '') {
            flash('Indica al menos el nombre del socio.', 'error');
            redirect('members');
        }

        $status = post_value('status', 'ACTIVE');
        if (!in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
            $status = 'ACTIVE';
        }

        $tenantId = Auth::tenantId();
        $memberId = post_value('id');
        $currentStmt = Database::connection()->prepare('SELECT photo_path FROM members WHERE id = :id AND tenant_id = :tenant_id LIMIT 1');
        $currentStmt->execute(['id' => $memberId, 'tenant_id' => $tenantId]);
        $currentPhoto = (string) (($currentStmt->fetch()['photo_path'] ?? '') ?: '');
        $uploadedPhoto = self::uploadedMemberPhotoPath();
        $removePhoto = post_value('remove_photo') === '1';
        $photoPath = $uploadedPhoto ?: ($removePhoto ? null : ($currentPhoto ?: null));

        if (($uploadedPhoto || $removePhoto) && $currentPhoto !== '') {
            self::deleteLocalUpload($currentPhoto);
        }

        $stmt = Database::connection()->prepare(
            'UPDATE members
             SET first_name = :first_name,
                 last_name = :last_name,
                 email = :email,
                 phone = :phone,
                 photo_path = :photo_path,
                 status = :status,
                 joined_at = :joined_at,
                 updated_at = NOW()
             WHERE id = :id AND tenant_id = :tenant_id'
        );
        $stmt->execute([
            'first_name' => $firstName,
            'last_name' => post_value('last_name') ?: null,
            'email' => post_value('email') ?: null,
            'phone' => phone_from_post(),
            'photo_path' => $photoPath,
            'status' => $status,
            'joined_at' => post_value('joined_at') ?: null,
            'id' => $memberId,
            'tenant_id' => $tenantId,
        ]);

        flash('Socio actualizado correctamente.');
        redirect('members');
    }

    private static function deleteMember(): never
    {
        $pdo = Database::connection();
        $memberId = post_value('id');
        $tenantId = Auth::tenantId();

        MemberRepository::ensurePhotoColumn();
        TaskRepository::ensureMemberLinksTable();
        $pdo->beginTransaction();
        try {
            $memberStmt = $pdo->prepare('SELECT lead_id, photo_path FROM members WHERE id = :id AND tenant_id = :tenant_id LIMIT 1');
            $memberStmt->execute(['id' => $memberId, 'tenant_id' => $tenantId]);
            $member = $memberStmt->fetch();
            $leadId = $member['lead_id'] ?? null;
            $photoPath = (string) (($member['photo_path'] ?? '') ?: '');

            $deleteLinks = $pdo->prepare('DELETE FROM task_members WHERE member_id = :id AND tenant_id = :tenant_id');
            $deleteLinks->execute(['id' => $memberId, 'tenant_id' => $tenantId]);

            $unlinkTasks = $pdo->prepare('UPDATE tasks SET member_id = NULL, updated_at = NOW() WHERE member_id = :id AND tenant_id = :tenant_id');
            $unlinkTasks->execute(['id' => $memberId, 'tenant_id' => $tenantId]);

            $deleteMember = $pdo->prepare('DELETE FROM members WHERE id = :id AND tenant_id = :tenant_id');
            $deleteMember->execute(['id' => $memberId, 'tenant_id' => $tenantId]);

            if ($leadId) {
                $reactivationStageId = PipelineRepository::contactedId($tenantId);
                $reactivateLead = $pdo->prepare(
                    'UPDATE leads
                     SET status = "OPEN",
                         pipeline_stage_id = COALESCE(:stage_id, pipeline_stage_id),
                         updated_at = NOW()
                     WHERE id = :id AND tenant_id = :tenant_id'
                );
                $reactivateLead->execute([
                    'stage_id' => $reactivationStageId,
                    'id' => $leadId,
                    'tenant_id' => $tenantId,
                ]);
            }

            $pdo->commit();

            if ($photoPath !== '') {
                self::deleteLocalUpload($photoPath);
            }
        } catch (Throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            flash('No se pudo eliminar el socio porque tiene datos relacionados.', 'error');
            redirect('members');
        }

        flash('Socio eliminado correctamente. Si venia de un lead, se ha reactivado en Leads.');
        redirect('members');
    }

    private static function uploadedMemberPhotoPath(): ?string
    {
        $file = $_FILES['photo'] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            flash('No se pudo subir la foto del socio.', 'error');
            redirect('members');
        }

        if ((int) ($file['size'] ?? 0) > 2 * 1024 * 1024) {
            flash('La foto no puede superar 2 MB.', 'error');
            redirect('members');
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        $imageInfo = @getimagesize($tmpPath);
        $allowedMimeTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        $mimeType = is_array($imageInfo) ? (string) ($imageInfo['mime'] ?? '') : '';
        if (!isset($allowedMimeTypes[$mimeType])) {
            flash('La foto debe ser JPG, PNG o WEBP.', 'error');
            redirect('members');
        }

        $uploadDir = self::memberUploadDirectory();
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            flash('No se pudo preparar la carpeta de fotos.', 'error');
            redirect('members');
        }

        $filename = cuid() . '.' . $allowedMimeTypes[$mimeType];
        $target = $uploadDir . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file($tmpPath, $target)) {
            flash('No se pudo guardar la foto del socio.', 'error');
            redirect('members');
        }

        return 'uploads/members/' . $filename;
    }

    private static function memberUploadDirectory(): string
    {
        return dirname(__DIR__) . '/public/uploads/members';
    }

    private static function deleteLocalUpload(string $path): void
    {
        if (!str_starts_with($path, 'uploads/members/')) {
            return;
        }

        $fullPath = dirname(__DIR__) . '/public/' . $path;
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

    private static function createTask(): never
    {
        if (!consume_form_token('create_task', post_value('form_token'))) {
            flash('La tarea ya se ha creado. Evita reenviar el formulario.', 'error');
            redirect('tasks');
        }

        $title = post_value('title', '');
        if ($title === '') {
            flash('Indica un titulo para la tarea.', 'error');
            redirect('tasks');
        }

        TaskRepository::ensureMemberLinksTable();
        $tenantId = Auth::tenantId();
        $memberIds = self::taskMemberIdsFromPost();
        $taskId = cuid();
        $pdo = Database::connection();

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO tasks (id, tenant_id, assigned_user_id, member_id, title, description, type, status, due_at, created_at, updated_at)
                 VALUES (:id, :tenant_id, :assigned_user_id, :member_id, :title, :description, :type, "PENDING", :due_at, NOW(), NOW())'
            );
            $stmt->execute([
                'id' => $taskId,
                'tenant_id' => $tenantId,
                'assigned_user_id' => post_value('assigned_user_id') ?: null,
                'member_id' => $memberIds[0] ?? null,
                'title' => $title,
                'description' => post_value('description') ?: null,
                'type' => post_value('type', 'OTHER'),
                'due_at' => post_value('due_at') ?: null,
            ]);

            self::syncTaskMembers($pdo, $tenantId, $taskId, $memberIds);

            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            flash('No se pudo crear la tarea.', 'error');
            redirect('tasks');
        }

        flash('Tarea creada correctamente.');
        redirect('tasks');
    }

    private static function updateTask(): never
    {
        $title = post_value('title', '');
        if ($title === '') {
            flash('Indica un titulo para la tarea.', 'error');
            redirect('tasks');
        }

        TaskRepository::ensureMemberLinksTable();
        $tenantId = Auth::tenantId();
        $taskId = post_value('id');
        $memberIds = self::taskMemberIdsFromPost();
        $status = post_value('status', 'PENDING');
        $allowedStatuses = ['PENDING', 'COMPLETED', 'CANCELLED'];
        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'PENDING';
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'UPDATE tasks
                 SET assigned_user_id = :assigned_user_id,
                     member_id = :member_id,
                     title = :title,
                     description = :description,
                     type = :type,
                     status = :status,
                     due_at = :due_at,
                     completed_at = IF(:status_done = "COMPLETED", COALESCE(completed_at, NOW()), IF(:status_pending = "PENDING", NULL, completed_at)),
                     updated_at = NOW()
                 WHERE id = :id AND tenant_id = :tenant_id'
            );
            $stmt->execute([
                'assigned_user_id' => post_value('assigned_user_id') ?: null,
                'member_id' => $memberIds[0] ?? null,
                'title' => $title,
                'description' => post_value('description') ?: null,
                'type' => post_value('type', 'OTHER'),
                'status' => $status,
                'status_done' => $status,
                'status_pending' => $status,
                'due_at' => post_value('due_at') ?: null,
                'id' => $taskId,
                'tenant_id' => $tenantId,
            ]);

            self::syncTaskMembers($pdo, $tenantId, (string) $taskId, $memberIds);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            flash('No se pudo actualizar la tarea.', 'error');
            redirect('tasks');
        }

        flash('Tarea actualizada correctamente.');
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
        TaskRepository::ensureMemberLinksTable();
        $deleteLinks = $pdo->prepare('DELETE FROM task_members WHERE task_id = :id AND tenant_id = :tenant_id');
        $deleteLinks->execute(['id' => $taskId, 'tenant_id' => $tenantId]);
        $alerts = $pdo->prepare('DELETE FROM risk_alerts WHERE task_id = :id AND tenant_id = :tenant_id');
        $alerts->execute(['id' => $taskId, 'tenant_id' => $tenantId]);
        $stmt = $pdo->prepare('DELETE FROM tasks WHERE id = :id AND tenant_id = :tenant_id');
        $stmt->execute(['id' => $taskId, 'tenant_id' => $tenantId]);
        redirect('tasks');
    }

    private static function taskMemberIdsFromPost(): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn ($value): string => trim((string) $value),
            is_array($_POST['member_ids'] ?? null) ? $_POST['member_ids'] : []
        ))));
    }

    private static function syncTaskMembers(PDO $pdo, string $tenantId, string $taskId, array $memberIds): void
    {
        $delete = $pdo->prepare('DELETE FROM task_members WHERE task_id = :task_id AND tenant_id = :tenant_id');
        $delete->execute(['task_id' => $taskId, 'tenant_id' => $tenantId]);

        if (!$memberIds) {
            return;
        }

        $insert = $pdo->prepare(
            'INSERT IGNORE INTO task_members (id, tenant_id, task_id, member_id, created_at)
             VALUES (:id, :tenant_id, :task_id, :member_id, NOW())'
        );

        foreach ($memberIds as $memberId) {
            $insert->execute([
                'id' => cuid(),
                'tenant_id' => $tenantId,
                'task_id' => $taskId,
                'member_id' => $memberId,
            ]);
        }
    }
}

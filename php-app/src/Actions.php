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
            'update_profile' => self::updateProfile(),
            'create_platform_client' => self::createPlatformClient(),
            'update_platform_client' => self::updatePlatformClient(),
            'create_empresa' => self::createEmpresa(),
            'update_empresa' => self::updateEmpresa(),
            'create_platform_payment' => self::createPlatformPayment(),
            'update_platform_payment' => self::updatePlatformPayment(),
            'create_platform_plan' => self::createPlatformPlan(),
            'update_platform_plan' => self::updatePlatformPlan(),
            'enter_empresa_crm' => self::enterEmpresaCrm(),
            'exit_empresa_crm' => self::exitEmpresaCrm(),
            'create_user' => self::createUser(),
            'update_user' => self::updateUser(),
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
            'create_membership_plan' => self::createMembershipPlan(),
            'update_membership_plan' => self::updateMembershipPlan(),
            'delete_membership_plan' => self::deleteMembershipPlan(),
            'create_class_type' => self::createClassType(),
            'create_class_session' => self::createClassSession(),
            'update_class_session' => self::updateClassSession(),
            'delete_class_session' => self::deleteClassSession(),
            'create_reservation' => self::createReservation(),
            'update_reservation_status' => self::updateReservationStatus(),
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
                redirect(is_platform_admin(Auth::user()) ? 'platform-dashboard' : 'dashboard');
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

    private static function updateProfile(): never
    {
        UserRepository::ensureAvatarColumn();
        $tenantId = Auth::tenantId();
        $user = Auth::requireUser();
        $userId = $user['id'];
        $name = post_value('name', '');
        $email = strtolower(post_value('email', ''));
        $password = post_value('password', '');
        $uploadedAvatar = self::uploadedImagePath('avatar', 'users', 'No se pudo subir la imagen de perfil.');
        $removeAvatar = post_value('remove_avatar') === '1';
        $currentAvatar = (string) (($user['avatar_path'] ?? '') ?: '');
        $avatarPath = $uploadedAvatar ?: ($removeAvatar ? null : ($currentAvatar ?: null));

        if ($name === '' || $email === '') {
            flash('Indica nombre y email para actualizar tu perfil.', 'error');
            redirect($_GET['return'] ?? 'dashboard');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('El email del perfil no es valido.', 'error');
            redirect($_GET['return'] ?? 'dashboard');
        }

        if ($password !== '' && strlen($password) < 8) {
            flash('La nueva contrasena debe tener al menos 8 caracteres.', 'error');
            redirect($_GET['return'] ?? 'dashboard');
        }

        if (UserRepository::emailExists($tenantId, $email, $userId)) {
            flash('Ya existe otro usuario con ese email.', 'error');
            redirect($_GET['return'] ?? 'dashboard');
        }

        $params = [
            'name' => $name,
            'email' => $email,
            'avatar_path' => $avatarPath,
            'id' => $userId,
            'tenant_id' => $tenantId,
        ];
        $passwordSql = '';

        if ($password !== '') {
            $passwordSql = ', password_hash = :password_hash';
            $params['password_hash'] = password_hash($password, PASSWORD_BCRYPT);
        }

        $stmt = Database::connection()->prepare(
            'UPDATE users
             SET name = :name,
                 email = :email' . $passwordSql . ',
                 avatar_path = :avatar_path,
                 updated_at = NOW()
             WHERE id = :id AND tenant_id = :tenant_id'
        );
        $stmt->execute($params);

        if (($uploadedAvatar || $removeAvatar) && $currentAvatar !== '') {
            self::deleteLocalUpload($currentAvatar);
        }

        $_SESSION['user']['name'] = $name;
        $_SESSION['user']['email'] = $email;
        $_SESSION['user']['avatar_path'] = $avatarPath;

        flash('Perfil actualizado correctamente.');
        redirect($_GET['return'] ?? 'dashboard');
    }

    private static function createEmpresa(): never
    {
        self::requirePlatformAdmin();

        if (post_value('name', '') === '') {
            flash('Indica el nombre de la empresa.', 'error');
            redirect('platform-companies');
        }

        try {
            EmpresaRepository::create($_POST);
        } catch (Throwable $exception) {
            flash($exception->getMessage() ?: 'No se pudo crear la empresa.', 'error');
            redirect('platform-companies');
        }

        flash('Empresa creada correctamente.');
        redirect('platform-companies');
    }

    private static function updateEmpresa(): never
    {
        self::requirePlatformAdmin();
        $id = post_value('id', '');

        if ($id === '' || post_value('name', '') === '') {
            flash('Indica la empresa que quieres actualizar.', 'error');
            redirect('platform-companies');
        }

        EmpresaRepository::update($id, $_POST);
        flash('Empresa actualizada correctamente.');
        redirect('platform-companies');
    }

    private static function createPlatformClient(): never
    {
        self::requirePlatformAdmin();

        if (post_value('company_name', '') === '') {
            flash('Indica el nombre de la empresa cliente.', 'error');
            redirect('platform-clients');
        }

        PlatformClientRepository::create($_POST);
        flash('Cliente creado correctamente.');
        redirect('platform-clients');
    }

    private static function updatePlatformClient(): never
    {
        self::requirePlatformAdmin();
        $id = post_value('id', '');

        if ($id === '' || post_value('company_name', '') === '') {
            flash('Indica el cliente que quieres actualizar.', 'error');
            redirect('platform-clients');
        }

        PlatformClientRepository::update($id, $_POST);
        flash('Cliente actualizado correctamente.');
        redirect('platform-clients');
    }

    private static function createPlatformPayment(): never
    {
        self::requirePlatformAdmin();

        if (post_value('empresa_id', '') === '' || post_value('concept', '') === '') {
            flash('Indica empresa y concepto del pago.', 'error');
            redirect('platform-payments');
        }

        PlatformPaymentRepository::create($_POST);
        flash('Pago registrado correctamente.');
        redirect('platform-payments');
    }

    private static function updatePlatformPayment(): never
    {
        self::requirePlatformAdmin();
        $id = post_value('id', '');

        if ($id === '' || post_value('empresa_id', '') === '' || post_value('concept', '') === '') {
            flash('Indica el pago que quieres actualizar.', 'error');
            redirect('platform-payments');
        }

        PlatformPaymentRepository::update($id, $_POST);
        flash('Pago actualizado correctamente.');
        redirect('platform-payments');
    }

    private static function createPlatformPlan(): never
    {
        self::requirePlatformAdmin();

        if (post_value('name', '') === '' || post_value('code', '') === '') {
            flash('Indica nombre y codigo del plan.', 'error');
            redirect('platform-plans');
        }

        PlatformPlanRepository::create($_POST);
        flash('Plan creado correctamente.');
        redirect('platform-plans');
    }

    private static function updatePlatformPlan(): never
    {
        self::requirePlatformAdmin();
        $id = post_value('id', '');

        if ($id === '' || post_value('name', '') === '' || post_value('code', '') === '') {
            flash('Indica el plan que quieres actualizar.', 'error');
            redirect('platform-plans');
        }

        PlatformPlanRepository::update($id, $_POST);
        flash('Plan actualizado correctamente.');
        redirect('platform-plans');
    }

    private static function enterEmpresaCrm(): never
    {
        self::requirePlatformAdmin();
        $empresa = EmpresaRepository::find(post_value('id', ''));
        if (!$empresa) {
            flash('No se encontro la empresa.', 'error');
            redirect('platform-dashboard');
        }

        Auth::enterTenantContext($empresa);
        flash('Estas viendo el CRM de ' . $empresa['name'] . '.');
        redirect('dashboard');
    }

    private static function exitEmpresaCrm(): never
    {
        Auth::exitTenantContext();
        redirect('platform-dashboard');
    }

    private static function requirePlatformAdmin(): void
    {
        if (!is_platform_admin(Auth::requireUser())) {
            flash('Solo un superadmin de Membora puede acceder a esta seccion.', 'error');
            redirect('dashboard');
        }
    }

    private static function createUser(): never
    {
        $tenantId = Auth::tenantId();
        $name = post_value('name', '');
        $email = strtolower(post_value('email', ''));
        $password = post_value('password', '');
        $roleId = post_value('role_id', '');

        if ($name === '' || $email === '' || $password === '' || $roleId === '') {
            flash('Indica nombre, email, contrasena y rol.', 'error');
            redirect('users');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('El email del usuario no es valido.', 'error');
            redirect('users');
        }

        if (strlen($password) < 8) {
            flash('La contrasena debe tener al menos 8 caracteres.', 'error');
            redirect('users');
        }

        if (!UserRepository::roleExists($roleId)) {
            flash('Selecciona un rol valido.', 'error');
            redirect('users');
        }

        if (UserRepository::emailExists($tenantId, $email)) {
            flash('Ya existe un usuario interno con ese email.', 'error');
            redirect('users');
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO users (id, tenant_id, role_id, name, email, password_hash, status, created_at, updated_at)
             VALUES (:id, :tenant_id, :role_id, :name, :email, :password_hash, :status, NOW(), NOW())'
        );
        $stmt->execute([
            'id' => cuid(),
            'tenant_id' => $tenantId,
            'role_id' => $roleId,
            'name' => $name,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            'status' => self::userStatusFromPost(),
        ]);

        flash('Usuario interno creado correctamente.');
        redirect('users');
    }

    private static function updateUser(): never
    {
        $tenantId = Auth::tenantId();
        $userId = post_value('id', '');
        $name = post_value('name', '');
        $email = strtolower(post_value('email', ''));
        $roleId = post_value('role_id', '');
        $status = self::userStatusFromPost();
        $password = post_value('password', '');

        if ($name === '' || $email === '' || $roleId === '' || $userId === '') {
            flash('Indica nombre, email y rol.', 'error');
            redirect('users');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('El email del usuario no es valido.', 'error');
            redirect('users');
        }

        if ($password !== '' && strlen($password) < 8) {
            flash('La nueva contrasena debe tener al menos 8 caracteres.', 'error');
            redirect('users');
        }

        if (!UserRepository::roleExists($roleId)) {
            flash('Selecciona un rol valido.', 'error');
            redirect('users');
        }

        if (($userId === (Auth::user()['id'] ?? null)) && $status !== 'ACTIVE') {
            flash('No puedes desactivar tu propio usuario mientras estas dentro.', 'error');
            redirect('users');
        }

        if (UserRepository::emailExists($tenantId, $email, $userId)) {
            flash('Ya existe otro usuario interno con ese email.', 'error');
            redirect('users');
        }

        $params = [
            'name' => $name,
            'email' => $email,
            'role_id' => $roleId,
            'status' => $status,
            'id' => $userId,
            'tenant_id' => $tenantId,
        ];
        $passwordSql = '';

        if ($password !== '') {
            $passwordSql = ', password_hash = :password_hash';
            $params['password_hash'] = password_hash($password, PASSWORD_BCRYPT);
        }

        $stmt = Database::connection()->prepare(
            'UPDATE users
             SET name = :name,
                 email = :email,
                 role_id = :role_id,
                 status = :status' . $passwordSql . ',
                 updated_at = NOW()
             WHERE id = :id AND tenant_id = :tenant_id'
        );
        $stmt->execute($params);

        if ($userId === (Auth::user()['id'] ?? null)) {
            $_SESSION['user']['name'] = $name;
            $_SESSION['user']['email'] = $email;
            foreach (UserRepository::roles() as $role) {
                if ($role['id'] === $roleId) {
                    $_SESSION['user']['role'] = $role['role_key'];
                    break;
                }
            }
        }

        flash('Usuario interno actualizado correctamente.');
        redirect('users');
    }

    private static function userStatusFromPost(): string
    {
        return post_value('status') === 'INACTIVE' ? 'INACTIVE' : 'ACTIVE';
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
        $tenantId = Auth::tenantId();
        $memberId = cuid();

        $stmt->execute([
            'id' => $memberId,
            'tenant_id' => $tenantId,
            'first_name' => $firstName,
            'last_name' => post_value('last_name') ?: null,
            'email' => post_value('email') ?: null,
            'phone' => phone_from_post(),
            'photo_path' => $photoPath,
            'status' => $status,
            'joined_at' => post_value('joined_at') ?: date('Y-m-d'),
        ]);

        MembershipRepository::assignToMember(
            $tenantId,
            $memberId,
            post_value('membership_plan_id') ?: null,
            post_value('membership_starts_at') ?: null,
            post_value('membership_ends_at') ?: null
        );

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

        MembershipRepository::assignToMember(
            $tenantId,
            $memberId,
            post_value('membership_plan_id') ?: null,
            post_value('membership_starts_at') ?: null,
            post_value('membership_ends_at') ?: null
        );

        flash('Socio actualizado correctamente.');
        redirect('members');
    }

    private static function deleteMember(): never
    {
        $pdo = Database::connection();
        $memberId = post_value('id');
        $tenantId = Auth::tenantId();

        MemberRepository::ensurePhotoColumn();
        MembershipRepository::ensureTables();
        TaskRepository::ensureMemberLinksTable();
        ReservationRepository::ensureTable();
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

            $cancelSubscriptions = $pdo->prepare('UPDATE subscriptions SET status = "CANCELLED", updated_at = NOW() WHERE member_id = :id AND tenant_id = :tenant_id');
            $cancelSubscriptions->execute(['id' => $memberId, 'tenant_id' => $tenantId]);

            $deleteReservations = $pdo->prepare('DELETE FROM reservations WHERE member_id = :id AND tenant_id = :tenant_id');
            $deleteReservations->execute(['id' => $memberId, 'tenant_id' => $tenantId]);

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
        return self::uploadedImagePath('photo', 'members', 'No se pudo subir la foto del socio.', 'members');
    }

    private static function uploadedImagePath(string $inputName, string $folder, string $errorMessage, string $redirectRoute = ''): ?string
    {
        $file = $_FILES[$inputName] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            flash($errorMessage, 'error');
            redirect($redirectRoute ?: ($_GET['return'] ?? 'dashboard'));
        }

        if ((int) ($file['size'] ?? 0) > 2 * 1024 * 1024) {
            flash('La imagen no puede superar 2 MB.', 'error');
            redirect($redirectRoute ?: ($_GET['return'] ?? 'dashboard'));
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
            flash('La imagen debe ser JPG, PNG o WEBP.', 'error');
            redirect($redirectRoute ?: ($_GET['return'] ?? 'dashboard'));
        }

        $uploadDir = self::uploadDirectory($folder);
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            flash('No se pudo preparar la carpeta de imagenes.', 'error');
            redirect($redirectRoute ?: ($_GET['return'] ?? 'dashboard'));
        }

        $filename = cuid() . '.' . $allowedMimeTypes[$mimeType];
        $target = $uploadDir . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file($tmpPath, $target)) {
            flash('No se pudo guardar la imagen.', 'error');
            redirect($redirectRoute ?: ($_GET['return'] ?? 'dashboard'));
        }

        return 'uploads/' . $folder . '/' . $filename;
    }

    private static function memberUploadDirectory(): string
    {
        return self::uploadDirectory('members');
    }

    private static function uploadDirectory(string $folder): string
    {
        return dirname(__DIR__) . '/public/uploads/' . $folder;
    }

    private static function deleteLocalUpload(string $path): void
    {
        if (!str_starts_with($path, 'uploads/members/') && !str_starts_with($path, 'uploads/users/')) {
            return;
        }

        $fullPath = dirname(__DIR__) . '/public/' . $path;
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

    private static function createMembershipPlan(): never
    {
        MembershipRepository::ensureTables();
        $name = post_value('name', '');

        if ($name === '') {
            flash('Indica el nombre de la membresia.', 'error');
            redirect('memberships');
        }

        $period = self::membershipPeriodFromPost();
        $stmt = Database::connection()->prepare(
            'INSERT INTO membership_plans (id, tenant_id, name, description, price, billing_period, duration_days, status, created_at, updated_at)
             VALUES (:id, :tenant_id, :name, :description, :price, :billing_period, :duration_days, :status, NOW(), NOW())'
        );
        $stmt->execute([
            'id' => cuid(),
            'tenant_id' => Auth::tenantId(),
            'name' => $name,
            'description' => post_value('description') ?: null,
            'price' => self::priceFromPost(),
            'billing_period' => $period,
            'duration_days' => membership_duration_days($period),
            'status' => post_value('status') === 'INACTIVE' ? 'INACTIVE' : 'ACTIVE',
        ]);

        flash('Membresia creada correctamente.');
        redirect('memberships');
    }

    private static function updateMembershipPlan(): never
    {
        MembershipRepository::ensureTables();
        $name = post_value('name', '');

        if ($name === '') {
            flash('Indica el nombre de la membresia.', 'error');
            redirect('memberships');
        }

        $period = self::membershipPeriodFromPost();
        $stmt = Database::connection()->prepare(
            'UPDATE membership_plans
             SET name = :name,
                 description = :description,
                 price = :price,
                 billing_period = :billing_period,
                 duration_days = :duration_days,
                 status = :status,
                 updated_at = NOW()
             WHERE id = :id AND tenant_id = :tenant_id'
        );
        $stmt->execute([
            'name' => $name,
            'description' => post_value('description') ?: null,
            'price' => self::priceFromPost(),
            'billing_period' => $period,
            'duration_days' => membership_duration_days($period),
            'status' => post_value('status') === 'INACTIVE' ? 'INACTIVE' : 'ACTIVE',
            'id' => post_value('id'),
            'tenant_id' => Auth::tenantId(),
        ]);

        flash('Membresia actualizada correctamente.');
        redirect('memberships');
    }

    private static function deleteMembershipPlan(): never
    {
        MembershipRepository::ensureTables();
        $tenantId = Auth::tenantId();
        $planId = post_value('id');
        $pdo = Database::connection();

        $activeStmt = $pdo->prepare('SELECT COUNT(*) FROM subscriptions WHERE tenant_id = :tenant_id AND membership_plan_id = :id AND status = "ACTIVE"');
        $activeStmt->execute(['tenant_id' => $tenantId, 'id' => $planId]);

        if ((int) $activeStmt->fetchColumn() > 0) {
            flash('No se puede eliminar una membresia asignada a socios. Desactivala si ya no se vende.', 'error');
            redirect('memberships');
        }

        $stmt = $pdo->prepare('DELETE FROM membership_plans WHERE id = :id AND tenant_id = :tenant_id');
        $stmt->execute(['id' => $planId, 'tenant_id' => $tenantId]);

        flash('Membresia eliminada correctamente.');
        redirect('memberships');
    }

    private static function membershipPeriodFromPost(): string
    {
        $period = post_value('billing_period', 'MONTHLY');
        return in_array($period, ['WEEKLY', 'MONTHLY', 'YEARLY'], true) ? $period : 'MONTHLY';
    }

    private static function priceFromPost(): string
    {
        $price = str_replace(',', '.', post_value('price', '0') ?? '0');
        return number_format(max(0, (float) $price), 2, '.', '');
    }

    private static function createClassType(): never
    {
        ClassRepository::ensureTables();
        $name = post_value('name', '');

        if ($name === '') {
            flash('Indica el nombre de la clase.', 'error');
            redirect('classes');
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO class_types (id, tenant_id, name, description, capacity, duration_minutes, status, created_at, updated_at)
             VALUES (:id, :tenant_id, :name, :description, :capacity, :duration_minutes, "ACTIVE", NOW(), NOW())'
        );
        $stmt->execute([
            'id' => cuid(),
            'tenant_id' => Auth::tenantId(),
            'name' => $name,
            'description' => post_value('description') ?: null,
            'capacity' => max(1, (int) post_value('capacity', '12')),
            'duration_minutes' => max(15, (int) post_value('duration_minutes', '60')),
        ]);

        flash('Tipo de clase creado correctamente.');
        redirect('classes');
    }

    private static function createClassSession(): never
    {
        ClassRepository::ensureTables();
        $tenantId = Auth::tenantId();
        $classTypeId = post_value('class_type_id', '');
        [$startsAt, $endsAt] = self::classDateTimesFromPost();

        if ($classTypeId === '' || !$startsAt || !$endsAt) {
            flash('Indica clase, fecha, hora de inicio y hora de finalizacion.', 'error');
            self::redirectAfterClassAction();
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO class_sessions (id, tenant_id, class_type_id, instructor_user_id, starts_at, ends_at, capacity, status, created_at, updated_at)
             VALUES (:id, :tenant_id, :class_type_id, :instructor_user_id, :starts_at, :ends_at, :capacity, "SCHEDULED", NOW(), NOW())'
        );
        $stmt->execute([
            'id' => cuid(),
            'tenant_id' => $tenantId,
            'class_type_id' => $classTypeId,
            'instructor_user_id' => post_value('instructor_user_id') ?: null,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'capacity' => max(1, (int) post_value('capacity', '12')),
        ]);

        flash('Clase programada correctamente.');
        self::redirectAfterClassAction();
    }

    private static function updateClassSession(): never
    {
        ClassRepository::ensureTables();
        $tenantId = Auth::tenantId();
        $classTypeId = post_value('class_type_id', '');
        [$startsAt, $endsAt] = self::classDateTimesFromPost();

        if ($classTypeId === '' || !$startsAt || !$endsAt) {
            flash('Indica clase, fecha, hora de inicio y hora de finalizacion.', 'error');
            self::redirectAfterClassAction();
        }

        $status = in_array(post_value('status'), ['SCHEDULED', 'CANCELLED', 'COMPLETED'], true) ? post_value('status') : 'SCHEDULED';
        $stmt = Database::connection()->prepare(
            'UPDATE class_sessions
             SET class_type_id = :class_type_id,
                 instructor_user_id = :instructor_user_id,
                 starts_at = :starts_at,
                 ends_at = :ends_at,
                 capacity = :capacity,
                 status = :status,
                 updated_at = NOW()
             WHERE id = :id AND tenant_id = :tenant_id'
        );
        $stmt->execute([
            'class_type_id' => $classTypeId,
            'instructor_user_id' => post_value('instructor_user_id') ?: null,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'capacity' => max(1, (int) post_value('capacity', '12')),
            'status' => $status,
            'id' => post_value('id'),
            'tenant_id' => $tenantId,
        ]);

        flash('Clase actualizada correctamente.');
        self::redirectAfterClassAction();
    }

    private static function deleteClassSession(): never
    {
        ClassRepository::ensureTables();
        ReservationRepository::ensureTable();
        $tenantId = Auth::tenantId();
        $sessionId = post_value('id');
        $pdo = Database::connection();

        $pdo->beginTransaction();
        try {
            $deleteReservations = $pdo->prepare('DELETE FROM reservations WHERE class_session_id = :id AND tenant_id = :tenant_id');
            $deleteReservations->execute(['id' => $sessionId, 'tenant_id' => $tenantId]);

            $stmt = $pdo->prepare('DELETE FROM class_sessions WHERE id = :id AND tenant_id = :tenant_id');
            $stmt->execute(['id' => $sessionId, 'tenant_id' => $tenantId]);
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            flash('No se pudo eliminar la clase.', 'error');
            self::redirectAfterClassAction();
        }

        flash('Clase eliminada correctamente.');
        self::redirectAfterClassAction();
    }

    private static function createReservation(): never
    {
        $tenantId = Auth::tenantId();
        $sessionId = post_value('class_session_id', '');
        $memberId = post_value('member_id', '');

        if ($sessionId === '' || $memberId === '') {
            flash('Selecciona una clase y un socio para crear la reserva.', 'error');
            self::redirectAfterReservationAction($sessionId);
        }

        try {
            ReservationRepository::create($tenantId, $memberId, $sessionId);
            flash('Reserva creada correctamente.');
        } catch (Throwable $exception) {
            flash($exception->getMessage() ?: 'No se pudo crear la reserva.', 'error');
        }

        self::redirectAfterReservationAction($sessionId);
    }

    private static function updateReservationStatus(): never
    {
        $tenantId = Auth::tenantId();
        $sessionId = post_value('class_session_id', '');
        $reservationId = post_value('reservation_id', '');
        $status = post_value('status', '');

        if ($reservationId === '' || $sessionId === '') {
            flash('No se encontro la reserva seleccionada.', 'error');
            self::redirectAfterReservationAction($sessionId);
        }

        try {
            ReservationRepository::updateStatus($tenantId, $reservationId, $status);
            flash(match ($status) {
                'cancelled' => 'Reserva cancelada correctamente.',
                'attended' => 'Asistencia marcada correctamente.',
                'no_show' => 'No-show marcado correctamente.',
                default => 'Reserva actualizada correctamente.',
            });
        } catch (Throwable $exception) {
            flash($exception->getMessage() ?: 'No se pudo actualizar la reserva.', 'error');
        }

        self::redirectAfterReservationAction($sessionId);
    }

    private static function classDateTimesFromPost(): array
    {
        $date = post_value('class_date', '');
        $startTime = post_value('class_start_time', '');
        $endTime = post_value('class_end_time', '');

        if ($date === '' || $startTime === '' || $endTime === '') {
            return [null, null];
        }

        $startsAt = $date . ' ' . $startTime . ':00';
        $endsAt = $date . ' ' . $endTime . ':00';

        if (strtotime($endsAt) <= strtotime($startsAt)) {
            flash('La hora de finalizacion debe ser posterior a la hora de inicio.', 'error');
            self::redirectAfterClassAction();
        }

        return [$startsAt, $endsAt];
    }

    private static function redirectAfterClassAction(): never
    {
        if (post_value('return_to_calendar') === '1') {
            $month = post_value('calendar_month', date('Y-m')) ?: date('Y-m');
            if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
                $month = date('Y-m');
            }

            $dateFrom = $month . '-01';
            $dateTo = date('Y-m-t', strtotime($dateFrom));
            header('Location: index.php?route=classes&month=' . urlencode($month) . '&date_from=' . urlencode($dateFrom) . '&date_to=' . urlencode($dateTo) . '&modal=classes-calendar-modal');
            exit;
        }

        redirect('classes');
    }

    private static function redirectAfterReservationAction(string $sessionId): never
    {
        if ($sessionId === '') {
            redirect('classes');
        }

        $stmt = Database::connection()->prepare('SELECT DATE(starts_at) FROM class_sessions WHERE id = :id AND tenant_id = :tenant_id LIMIT 1');
        $stmt->execute(['id' => $sessionId, 'tenant_id' => Auth::tenantId()]);
        $date = (string) ($stmt->fetchColumn() ?: date('Y-m-d'));

        header('Location: index.php?route=classes&date_from=' . urlencode($date) . '&date_to=' . urlencode($date) . '&month=' . urlencode(substr($date, 0, 7)) . '&modal=class-detail-' . urlencode($sessionId));
        exit;
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

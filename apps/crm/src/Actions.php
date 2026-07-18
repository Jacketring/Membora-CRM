<?php

final class Actions
{
    public static function handle(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        enforce_internal_post_security();

        $action = post_value('action', '');
        if ($action !== 'demo_login' && !verify_csrf()) {
            flash('Solicitud bloqueada por seguridad. Recarga la página e inténtalo de nuevo.', 'error');
            redirect($_GET['return'] ?? ($_GET['route'] ?? 'dashboard'));
        }
        if (!can_perform_action($action)) {
            flash('No tienes permisos para realizar esta acción.', 'error');
            redirect($_GET['return'] ?? ($_GET['route'] ?? 'dashboard'));
        }

        $user = Auth::user();
        if ($action !== 'logout'
            && $action !== 'reveal_trial_credentials'
            && $action !== 'create_tenant_stripe_checkout'
            && $action !== 'open_tenant_simulated_checkout'
            && $action !== 'complete_tenant_simulated_checkout'
            && $user
            && !is_platform_admin($user)
            && !is_platform_support_context()
        ) {
            $accessState = EmpresaRepository::accessStateForTenant((string) ($user['tenant_id'] ?? ''));
            if (!empty($accessState['blocked'])) {
                flash('Tu demo o suscripción no permite realizar esta acción.', 'error');
                redirect('dashboard');
            }
        }

        self::auditPostAction($action);

        match ($action) {
            'login' => self::login(),
            'demo_login' => self::demoLogin(),
            'keep_demo_session' => self::keepDemoSession(),
            'schedule_demo_cleanup' => self::scheduleDemoCleanup(),
            'request_password_reset' => self::requestPasswordReset(),
            'reset_password' => self::resetPassword(),
            'confirm_trial_activation' => self::confirmTrialActivation(),
            'reveal_trial_credentials' => self::revealTrialCredentials(),
            'logout' => self::logout(),
            'update_profile' => self::updateProfile(),
            'update_platform_lead' => self::updatePlatformLead(),
            'convert_platform_lead' => self::convertPlatformLead(),
            'delete_platform_lead' => self::deletePlatformLead(),
            'send_platform_test_email' => self::sendPlatformTestEmail(),
            'reset_platform_trial_attempts' => self::resetTrialAttempts(),
            'create_platform_client' => self::createPlatformClient(),
            'update_platform_client' => self::updatePlatformClient(),
            'delete_platform_client' => self::deletePlatformClient(),
            'create_empresa' => self::createEmpresa(),
            'update_empresa' => self::updateEmpresa(),
            'delete_empresa' => self::deleteEmpresa(),
            'update_empresa_subscription' => self::updateEmpresaSubscription(),
            'renew_empresa_subscription' => self::renewEmpresaSubscription(),
            'cancel_empresa_subscription' => self::cancelEmpresaSubscription(),
            'resume_empresa_subscription' => self::resumeEmpresaSubscription(),
            'create_empresa_stripe_checkout' => self::createEmpresaStripeCheckout(),
            'create_tenant_stripe_checkout' => self::createTenantStripeCheckout(),
            'open_tenant_simulated_checkout' => self::openTenantSimulatedCheckout(),
            'complete_tenant_simulated_checkout' => self::completeTenantSimulatedCheckout(),
            'cancel_empresa_stripe_subscription' => self::cancelEmpresaStripeSubscription(),
            'create_platform_payment' => self::createPlatformPayment(),
            'update_platform_payment' => self::updatePlatformPayment(),
            'create_platform_invoice' => self::createPlatformInvoice(),
            'update_platform_invoice' => self::updatePlatformInvoice(),
            'issue_platform_invoice' => self::issuePlatformInvoice(),
            'add_platform_invoice_payment' => self::addPlatformInvoicePayment(),
            'create_client_invoice' => self::createClientInvoice(),
            'update_client_invoice' => self::updateClientInvoice(),
            'issue_client_invoice' => self::issueClientInvoice(),
            'add_client_invoice_payment' => self::addClientInvoicePayment(),
            'create_platform_plan' => self::createPlatformPlan(),
            'update_platform_plan' => self::updatePlatformPlan(),
            'enter_empresa_crm' => self::enterEmpresaCrm(),
            'exit_empresa_crm' => self::exitEmpresaCrm(),
            'create_platform_user' => self::createPlatformUser(),
            'update_platform_user' => self::updatePlatformUser(),
            'delete_platform_user' => self::deletePlatformUser(),
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
            'renew_member_subscription' => self::renewMemberSubscription(),
            'create_membership_plan' => self::createMembershipPlan(),
            'update_membership_plan' => self::updateMembershipPlan(),
            'delete_membership_plan' => self::deleteMembershipPlan(),
            'create_payment' => self::createPayment(),
            'update_payment' => self::updatePayment(),
            'mark_payment_paid' => self::markPaymentPaid(),
            'generate_recurring_payments' => self::generateRecurringPayments(),
            'delete_payment' => self::deletePayment(),
            'create_checkin' => self::createCheckin(),
            'delete_checkin' => self::deleteCheckin(),
            'update_risk_alert_status' => self::updateRiskAlertStatus(),
            'save_billing_integration' => self::saveBillingIntegration(),
            'sync_billing_integration' => self::syncBillingIntegration(),
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

    private static function auditPostAction(string $action): void
    {
        if ($action === '') {
            return;
        }

        try {
            AuditLogRepository::record($action, $_POST);
        } catch (Throwable $exception) {
            $_SESSION['audit_log_error'] = $exception->getMessage();
            // La auditoría no debe bloquear la operación principal del usuario.
        }
    }

    private static function login(): never
    {
        $email = post_value('email', '');
        $password = post_value('password', '');
        $remember = post_value('remember') === '1';

        try {
            if (Auth::attempt($email, $password, $remember)) {
                redirect(is_platform_admin(Auth::user()) ? 'platform-dashboard' : 'dashboard');
            }
        } catch (Throwable $exception) {
            flash('No se pudo conectar con la base de datos. Revisa apps/crm/.env.', 'error');
            redirect('login');
        }

        flash(Auth::lastAttemptWasRateLimited() ? 'Demasiados intentos, prueba mas tarde.' : 'Credenciales incorrectas.', 'error');
        redirect('login');
    }

    private static function requestPasswordReset(): never
    {
        $email = strtolower(trim((string) post_value('email', '')));
        $genericMessage = 'Si existe una cuenta activa con ese email, recibirás un enlace para cambiar la contraseña.';

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            try {
                $stmt = Database::connection()->prepare('SELECT id, name, email FROM users WHERE LOWER(email) = :email AND status = "ACTIVE" LIMIT 1');
                $stmt->execute(['email' => $email]);
                $user = $stmt->fetch();

                if ($user) {
                    $token = AuthTokenRepository::issuePasswordReset((string) $user['id']);
                    if ($token !== null) {
                        $resetUrl = app_base_url() . '/index.php?route=reset-password&token=' . urlencode($token);
                        if (!Mailer::sendPasswordReset((string) $user['email'], (string) $user['name'], $resetUrl)) {
                            AuthTokenRepository::deleteSelector(AuthTokenRepository::selector($token));
                            error_log('Password reset email failed: ' . Mailer::lastError());
                        }
                    }
                }
            } catch (Throwable $exception) {
                log_server_error($exception, 'password_reset_request');
            }
        }

        flash($genericMessage);
        redirect('forgot-password');
    }

    private static function resetPassword(): never
    {
        $token = trim((string) post_value('token', ''));
        $password = (string) post_value('password', '');
        $confirmation = (string) post_value('password_confirmation', '');
        if ($token === '' || AuthTokenRepository::validUserId($token, AuthTokenRepository::PASSWORD_RESET_PURPOSE) === null) {
            flash('El enlace de recuperación no es válido o ha caducado.', 'error');
            redirect('forgot-password');
        }

        if (strlen($password) < 8) {
            flash('La nueva contraseña debe tener al menos 8 caracteres.', 'error');
            self::redirectToPasswordReset($token);
        }

        if (!hash_equals($password, $confirmation)) {
            flash('Las contraseñas no coinciden.', 'error');
            self::redirectToPasswordReset($token);
        }

        try {
            if (!AuthTokenRepository::resetPassword($token, password_hash($password, PASSWORD_BCRYPT))) {
                flash('El enlace de recuperación no es válido o ha caducado.', 'error');
                redirect('forgot-password');
            }
        } catch (Throwable $exception) {
            log_server_error($exception, 'password_reset');
            flash('No se pudo cambiar la contraseña. Inténtalo de nuevo.', 'error');
            self::redirectToPasswordReset($token);
        }

        flash('Contraseña actualizada. Ya puedes iniciar sesión.');
        redirect('login');
    }

    private static function confirmTrialActivation(): never
    {
        $token = trim((string) post_value('token', ''));

        try {
            TrialRegistrationRepository::activate($token);
            flash('Cuenta activada. Te hemos enviado otro correo con el enlace para ver tu contraseña una sola vez.');
            redirect('login');
        } catch (Throwable $exception) {
            log_server_error($exception, 'trial_activation');
            if ($exception->getMessage() === TrialRegistrationRepository::CREDENTIAL_EMAIL_FAILED) {
                flash('La cuenta está creada, pero el correo de acceso no pudo enviarse. Vuelve a pulsar el botón para reintentarlo.', 'error');
                header('Location: index.php?route=activate-trial&token=' . urlencode($token));
                exit;
            }
            $safeMessages = [
                'El enlace de activación no es válido.',
                'El enlace de activación ha caducado o ya se ha utilizado.',
                'Esta prueba ya se está activando.',
            ];
            if (in_array($exception->getMessage(), $safeMessages, true)) {
                flash($exception->getMessage(), 'error');
                redirect('login');
            }

            flash('No se pudo completar el alta. No se han duplicado los datos; vuelve a pulsar el botón para continuar.', 'error');
            header('Location: index.php?route=activate-trial&token=' . urlencode($token));
            exit;
        }
    }

    private static function revealTrialCredentials(): never
    {
        $token = trim((string) post_value('token', ''));
        try {
            $credentials = TrialCredentialRepository::consume($token);
        } catch (Throwable $exception) {
            log_server_error($exception, 'trial_credentials');
            $credentials = null;
        }

        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        render('trial-credentials', [
            'token' => '',
            'tokenValid' => false,
            'credentials' => $credentials,
        ]);
        exit;
    }

    private static function redirectToPasswordReset(string $token): never
    {
        header('Location: index.php?route=reset-password&token=' . urlencode($token));
        exit;
    }

    private static function demoLogin(): never
    {
        $type = post_value('demo_type', 'client') === 'admin' ? 'admin' : 'client';
        if (!DemoAccessPolicy::isTypeEnabled((string) getenv('APP_ENV'), $type)) {
            redirect('login');
        }

        try {
            if (Auth::attemptDemo($type)) {
                redirect($type === 'admin' ? 'platform-dashboard' : 'dashboard');
            }
        } catch (Throwable $exception) {
            flash('No se pudo preparar la demo. Revisa la base de datos.', 'error');
            redirect('login');
        }

        flash('No se pudo iniciar la demo.', 'error');
        redirect('login');
    }

    private static function keepDemoSession(): never
    {
        if (Auth::isDemoSession()) {
            Auth::refreshDemoSession();
        }
        http_response_code(204);
        exit;
    }

    private static function scheduleDemoCleanup(): never
    {
        if (Auth::isDemoSession()) {
            Auth::scheduleDemoCleanup();
        }
        http_response_code(204);
        exit;
    }

    private static function logout(): never
    {
        Auth::logout();
        redirect('login');
    }

    private static function updateProfile(): never
    {
        UserRepository::ensureAvatarColumn();
        $user = Auth::requireUser();
        $tenantId = is_platform_admin($user) ? null : Auth::tenantId();
        $userId = $user['id'];
        $name = post_value('name', '');
        $email = strtolower(post_value('email', ''));
        $password = post_value('password', '');
        $currentPassword = post_value('current_password', '');
        $uploadedAvatar = self::uploadedImagePath('avatar', 'users', 'No se pudo subir la imagen de perfil.');
        $removeAvatar = post_value('remove_avatar') === '1';
        $currentAvatar = (string) (($user['avatar_path'] ?? '') ?: '');
        $avatarPath = $uploadedAvatar ?: ($removeAvatar ? null : ($currentAvatar ?: null));

        if ($name === '' || $email === '') {
            flash('Indica nombre y email para actualizar tu perfil.', 'error');
            redirect($_GET['return'] ?? 'dashboard');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('El email del perfil no es válido.', 'error');
            redirect($_GET['return'] ?? 'dashboard');
        }

        if ($password !== '' && strlen($password) < 8) {
            flash('La nueva contraseña debe tener al menos 8 caracteres.', 'error');
            redirect($_GET['return'] ?? 'dashboard');
        }

        if ($password !== '') {
            $passwordStmt = Database::connection()->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
            $passwordStmt->execute(['id' => $userId]);
            $currentHash = $passwordStmt->fetchColumn();
            if (!is_string($currentHash) || $currentPassword === '' || !password_verify($currentPassword, $currentHash)) {
                flash('La contraseña actual no es correcta.', 'error');
                redirect($_GET['return'] ?? 'dashboard');
            }
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
        ];
        $tenantScopeSql = '';
        if ($tenantId !== null) {
            $tenantScopeSql = ' AND tenant_id = :tenant_id';
            $params['tenant_id'] = $tenantId;
        }
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
             WHERE id = :id' . $tenantScopeSql
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

        if (strlen((string) post_value('admin_password', '')) < 8) {
            flash('Indica una contraseña de al menos 8 caracteres para el administrador.', 'error');
            redirect('platform-companies');
        }

        try {
            EmpresaRepository::create($_POST);
        } catch (Throwable $exception) {
            log_server_error($exception, 'create_empresa');
            flash('No se pudo crear la empresa.', 'error');
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

    private static function deleteEmpresa(): never
    {
        self::requirePlatformAdmin();
        $id = post_value('id', '');

        if ($id === '') {
            flash('No se encontró la empresa que quieres eliminar.', 'error');
            redirect('platform-companies');
        }

        try {
            EmpresaRepository::delete($id);
        } catch (Throwable $exception) {
            log_server_error($exception, 'delete_empresa');
            $error = $exception->getMessage();
            if (str_contains($error, 'superadministrador')) {
                $message = 'No puedes eliminar la única empresa vinculada al superadministrador. Crea otra empresa y vuelve a intentarlo.';
            } elseif (preg_match('/foreign key constraint fails \(`[^`]+`\.`([^`]+)`.*CONSTRAINT `([^`]+)`/i', $error, $matches)) {
                $table = preg_replace('/[^a-zA-Z0-9_]/', '', $matches[1]) ?: 'desconocida';
                $constraint = preg_replace('/[^a-zA-Z0-9_]/', '', $matches[2]) ?: 'desconocida';
                $message = 'No se pudo eliminar la empresa: la tabla relacionada ' . $table
                    . ' lo impide (restricción ' . $constraint . ').';
            } else {
                $message = 'No se pudo eliminar la empresa. Consulta el registro del servidor con la referencia delete_empresa.';
            }
            flash($message, 'error');
            redirect('platform-companies');
        }

        flash('Empresa y datos de su plataforma eliminados correctamente. El contacto comercial se ha conservado.');
        redirect('platform-companies');
    }

    private static function updateEmpresaSubscription(): never
    {
        self::requirePlatformAdmin();
        $id = post_value('id', '');

        if ($id === '') {
            flash('No se encontró la suscripción que quieres actualizar.', 'error');
            redirect('platform-contacts');
        }

        try {
            EmpresaRepository::updateSubscription($id, $_POST);
        } catch (Throwable $exception) {
            log_server_error($exception, 'subscription');
            flash('No se pudo actualizar la suscripción.', 'error');
            redirect('platform-contacts');
        }

        flash('Suscripción actualizada correctamente.');
        redirect('platform-contacts');
    }

    private static function renewEmpresaSubscription(): never
    {
        self::requirePlatformAdmin();
        $id = post_value('id', '');
        $returnRoute = post_value('return', 'platform-companies');

        if ($id === '') {
            flash('No se encontró la empresa que quieres renovar.', 'error');
            redirect($returnRoute);
        }

        try {
            EmpresaRepository::renewSubscription($id);
        } catch (Throwable $exception) {
            log_server_error($exception, 'subscription');
            flash('No se pudo renovar la suscripción.', 'error');
            redirect($returnRoute);
        }

        flash('Renovación registrada y próximo pago actualizado.');
        redirect($returnRoute);
    }

    private static function cancelEmpresaSubscription(): never
    {
        self::requirePlatformAdmin();
        $id = post_value('id', '');
        $returnRoute = post_value('return', 'platform-companies');

        if ($id === '') {
            flash('No se encontró la empresa que quieres cancelar.', 'error');
            redirect($returnRoute);
        }

        try {
            EmpresaRepository::cancelSubscription($id);
        } catch (Throwable $exception) {
            log_server_error($exception, 'subscription');
            flash('No se pudo cancelar la suscripción.', 'error');
            redirect($returnRoute);
        }

        flash('Suscripción marcada para cancelar al final del periodo.');
        redirect($returnRoute);
    }

    private static function resumeEmpresaSubscription(): never
    {
        self::requirePlatformAdmin();
        $id = post_value('id', '');
        $returnRoute = post_value('return', 'platform-companies');

        if ($id === '') {
            flash('No se encontró la empresa que quieres reactivar.', 'error');
            redirect($returnRoute);
        }

        try {
            EmpresaRepository::resumeSubscription($id);
        } catch (Throwable $exception) {
            log_server_error($exception, 'subscription');
            flash('No se pudo reactivar la suscripción.', 'error');
            redirect($returnRoute);
        }

        flash('Suscripción reactivada correctamente.');
        redirect($returnRoute);
    }

    private static function createEmpresaStripeCheckout(): never
    {
        self::requirePlatformAdmin();
        $id = post_value('id', '');
        $returnRoute = post_value('return', 'platform-contacts');

        if ($id === '') {
            flash('No se encontró la empresa para iniciar Stripe Checkout.', 'error');
            redirect($returnRoute);
        }

        try {
            $url = StripeBillingService::createCheckoutSession($id);
        } catch (Throwable $exception) {
            StripeBillingRepository::recordEmpresaError($id, $exception->getMessage());
            log_server_error($exception, 'stripe');
            flash('No se pudo crear la sesión de Stripe Checkout.', 'error');
            redirect($returnRoute);
        }

        header('Location: ' . $url);
        exit;
    }

    private static function createTenantStripeCheckout(): never
    {
        $user = Auth::requireUser();
        if (!is_gym_admin($user) || is_platform_admin($user) || is_platform_support_context()) {
            flash('Solo el administrador del gimnasio puede mejorar el plan.', 'error');
            redirect('dashboard');
        }

        $empresa = EmpresaRepository::findByTenant((string) ($user['tenant_id'] ?? ''));
        if (!$empresa) {
            flash('No se encontro la empresa vinculada a tu cuenta.', 'error');
            redirect('dashboard');
        }
        $planCode = strtoupper(post_value('plan_code', ''));
        $renewalPeriod = strtoupper(post_value('renewal_period', 'MONTHLY'));
        if (!PlatformPlanRepository::canUpgrade((string) ($empresa['plan'] ?? ''), $planCode)) {
            flash('Selecciona un plan superior al que tienes actualmente.', 'error');
            redirect('upgrade-plan');
        }
        if (trim((string) ($empresa['stripe_subscription_id'] ?? '')) !== '') {
            flash('La suscripcion ya esta vinculada a Stripe. Contacta con Membora para cambiar el plan sin crear una suscripcion duplicada.', 'error');
            redirect('upgrade-plan');
        }

        try {
            $url = StripeBillingService::createCheckoutSession((string) $empresa['id'], $planCode, $renewalPeriod, true);
        } catch (Throwable $exception) {
            StripeBillingRepository::recordEmpresaError((string) $empresa['id'], $exception->getMessage());
            log_server_error($exception, 'tenant_stripe_checkout');
            flash('No se pudo iniciar el pago: ' . $exception->getMessage(), 'error');
            redirect('upgrade-plan');
        }

        header('Location: ' . $url);
        exit;
    }

    private static function openTenantSimulatedCheckout(): never
    {
        $user = Auth::requireUser();
        if (!is_gym_admin($user) || is_platform_admin($user) || is_platform_support_context()) {
            flash('Solo el administrador del gimnasio puede abrir el checkout de prueba.', 'error');
            redirect('dashboard');
        }
        if (!StripeBillingConfig::simulatedCheckoutEnabled()) {
            flash('El checkout interno de demostracion no esta habilitado.', 'error');
            redirect('upgrade-plan');
        }

        $planCode = strtoupper(post_value('plan_code', ''));
        $renewalPeriod = strtoupper(post_value('renewal_period', 'MONTHLY'));
        if (!in_array($renewalPeriod, ['MONTHLY', 'ANNUAL'], true)) {
            flash('Selecciona una periodicidad valida.', 'error');
            redirect('upgrade-plan');
        }

        $plan = StripeBillingRepository::planByCode($planCode);
        if (!$plan || $planCode === 'TRIAL' || (string) ($plan['status'] ?? '') !== 'ACTIVE') {
            flash('Selecciona un plan de pago activo.', 'error');
            redirect('upgrade-plan');
        }

        $empresa = EmpresaRepository::findByTenant((string) ($user['tenant_id'] ?? ''));
        if (!$empresa || !PlatformPlanRepository::canUpgrade((string) ($empresa['plan'] ?? ''), $planCode)) {
            flash('Selecciona un plan superior al que tienes actualmente.', 'error');
            redirect('upgrade-plan');
        }

        header('Location: index.php?route=simulated-checkout&plan=' . rawurlencode($planCode) . '&period=' . rawurlencode($renewalPeriod));
        exit;
    }

    private static function completeTenantSimulatedCheckout(): never
    {
        $user = Auth::requireUser();
        if (!is_gym_admin($user) || is_platform_admin($user) || is_platform_support_context()) {
            flash('Solo el administrador del gimnasio puede completar el checkout de prueba.', 'error');
            redirect('dashboard');
        }

        $empresa = EmpresaRepository::findByTenant((string) ($user['tenant_id'] ?? ''));
        if (!$empresa) {
            flash('No se encontro la empresa vinculada a tu cuenta.', 'error');
            redirect('dashboard');
        }

        $planCode = strtoupper(post_value('plan_code', ''));
        $renewalPeriod = strtoupper(post_value('renewal_period', 'MONTHLY'));
        try {
            SimulatedCheckoutService::validateCard(
                post_value('card_number', ''),
                post_value('card_expiry', ''),
                post_value('card_cvc', '')
            );
            $result = SimulatedCheckoutService::complete((string) $empresa['id'], $planCode, $renewalPeriod);
        } catch (Throwable $exception) {
            log_server_error($exception, 'simulated_checkout');
            flash('No se pudo completar el pago de prueba: ' . $exception->getMessage(), 'error');
            header('Location: index.php?route=simulated-checkout&plan=' . rawurlencode($planCode) . '&period=' . rawurlencode($renewalPeriod));
            exit;
        }

        flash('Pago simulado completado por ' . money_amount($result['amount']) . '. El plan ya esta activo y el movimiento aparece en la administracion.');
        redirect('dashboard');
    }

    private static function cancelEmpresaStripeSubscription(): never
    {
        self::requirePlatformAdmin();
        $id = post_value('id', '');
        $returnRoute = post_value('return', 'platform-contacts');

        if ($id === '') {
            flash('No se encontró la empresa para cancelar Stripe.', 'error');
            redirect($returnRoute);
        }

        try {
            StripeBillingService::cancelAtPeriodEnd($id);
        } catch (Throwable $exception) {
            StripeBillingRepository::recordEmpresaError($id, $exception->getMessage());
            log_server_error($exception, 'stripe');
            flash('No se pudo cancelar la suscripción en Stripe.', 'error');
            redirect($returnRoute);
        }

        flash('Cancelacion enviada a Stripe. El acceso se conserva hasta el final del periodo.');
        redirect($returnRoute);
    }

    private static function updatePlatformLead(): never
    {
        self::requirePlatformAdmin();
        $id = post_value('id', '');

        if ($id === '') {
            flash('No se encontró el contacto seleccionado.', 'error');
            redirect('platform-contacts');
        }

        PlatformLeadRepository::update($id, $_POST);
        if (post_value('contact_type') === 'client') {
            try {
                PlatformLeadRepository::convertToClient($id);
            } catch (Throwable $exception) {
                log_server_error($exception, 'platform_client');
                flash('No se pudo convertir el contacto en cliente.', 'error');
                redirect('platform-contacts');
            }

            flash('Lead convertido en cliente correctamente.');
            redirect('platform-contacts');
        }

        flash('Contacto actualizado correctamente.');
        redirect('platform-contacts');
    }

    private static function convertPlatformLead(): never
    {
        self::requirePlatformAdmin();
        $id = post_value('id', '');

        if ($id === '') {
            flash('No se encontró el contacto seleccionado.', 'error');
            redirect('platform-contacts');
        }

        try {
            $clientId = PlatformLeadRepository::convertToClient($id);
        } catch (Throwable $exception) {
            log_server_error($exception, 'platform_client');
            flash('No se pudo convertir el contacto en cliente.', 'error');
            redirect('platform-contacts');
        }

        flash('Contacto convertido en cliente correctamente.');
        redirect('platform-contacts');
    }

    private static function deletePlatformLead(): never
    {
        self::requirePlatformAdmin();
        $id = post_value('id', '');

        if ($id === '') {
            flash('No se encontró el contacto seleccionado.', 'error');
            redirect('platform-contacts');
        }

        try {
            PlatformLeadRepository::delete($id);
        } catch (Throwable $exception) {
            log_server_error($exception, 'delete_platform_lead');
            flash('No se pudo eliminar el lead.', 'error');
            redirect('platform-contacts');
        }
        flash('Contacto eliminado correctamente.');
        redirect('platform-contacts');
    }

    private static function sendPlatformTestEmail(): never
    {
        self::requirePlatformAdmin();
        $email = strtolower(post_value('email', ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('Indica un email válido para la prueba.', 'error');
            redirect('platform-web');
        }

        if (Mailer::sendDebugEmail($email)) {
            WebhookIntegrationRepository::logPlatformEmailDiagnostic('email_test', 'Correo de prueba aceptado por el transporte de envio.', $email);
            flash('Correo de prueba enviado a ' . $email . '. Revisa también spam/promociones.');
            redirect('platform-web');
        }

        WebhookIntegrationRepository::logPlatformEmailDiagnostic('email_error', 'Prueba de correo fallida. ' . Mailer::lastError(), $email);
        flash('No se pudo enviar el correo de prueba: ' . Mailer::lastError(), 'error');
        redirect('platform-web');
    }

    private static function resetTrialAttempts(): never
    {
        self::requirePlatformAdmin();
        $email = strtolower(trim((string) post_value('email', '')));

        try {
            $deleted = TrialRegistrationRepository::resetAttempts($email);
        } catch (InvalidArgumentException $exception) {
            flash($exception->getMessage(), 'error');
            redirect('platform-web');
        } catch (Throwable $exception) {
            log_server_error($exception, 'reset_trial_attempts');
            flash('No se pudieron reiniciar los intentos de la prueba.', 'error');
            redirect('platform-web');
        }

        $message = $deleted > 0
            ? 'Intentos reiniciados. Ya puedes solicitar un enlace nuevo para ' . $email . '.'
            : 'No había solicitudes pendientes o fallidas para ' . $email . '.';
        flash($message);
        redirect('platform-web');
    }

    private static function createPlatformClient(): never
    {
        self::requirePlatformAdmin();

        if (post_value('company_name', '') === '') {
            flash('Indica el nombre de la empresa del contacto.', 'error');
            redirect('platform-contacts');
        }

        PlatformClientRepository::create($_POST);
        flash('Contacto creado correctamente.');
        redirect('platform-contacts');
    }

    private static function updatePlatformClient(): never
    {
        self::requirePlatformAdmin();
        $id = post_value('id', '');

        if ($id === '' || post_value('company_name', '') === '') {
            flash('Indica el contacto que quieres actualizar.', 'error');
            redirect('platform-contacts');
        }

        PlatformClientRepository::update($id, $_POST);
        if (post_value('contact_type') === 'lead' || post_value('status') === 'LEAD') {
            flash('Contacto devuelto a lead correctamente.');
        } else {
            flash('Contacto actualizado correctamente.');
        }
        redirect('platform-contacts');
    }

    private static function deletePlatformClient(): never
    {
        self::requirePlatformAdmin();
        $id = post_value('id', '');

        if ($id === '') {
            flash('No se encontró el contacto que quieres eliminar.', 'error');
            redirect('platform-contacts');
        }

        try {
            PlatformClientRepository::delete($id);
        } catch (Throwable $exception) {
            log_server_error($exception, 'delete_platform_client');
            flash('No se pudo eliminar el contacto comercial.', 'error');
            redirect('platform-contacts');
        }
        flash('Contacto eliminado correctamente.');
        redirect('platform-contacts');
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

    private static function createPlatformInvoice(): never
    {
        self::requirePlatformAdmin();

        if (post_value('empresa_id', '') === '') {
            flash('Indica la empresa de la factura.', 'error');
            redirect('platform-invoices');
        }

        try {
            PlatformInvoiceRepository::create($_POST);
        } catch (Throwable $exception) {
            log_server_error($exception, 'invoice');
            flash('No se pudo crear la factura.', 'error');
            redirect('platform-invoices');
        }

        flash('Factura creada correctamente.');
        redirect('platform-invoices');
    }

    private static function updatePlatformInvoice(): never
    {
        self::requirePlatformAdmin();
        $id = post_value('id', '');

        if ($id === '' || post_value('empresa_id', '') === '') {
            flash('Indica la factura que quieres actualizar.', 'error');
            redirect('platform-invoices');
        }

        try {
            PlatformInvoiceRepository::update($id, $_POST);
        } catch (Throwable $exception) {
            log_server_error($exception, 'invoice');
            flash('No se pudo actualizar la factura.', 'error');
            redirect('platform-invoices');
        }

        flash('Factura actualizada correctamente.');
        redirect('platform-invoices');
    }

    private static function issuePlatformInvoice(): never
    {
        self::requirePlatformAdmin();
        $id = post_value('id', '');

        if ($id === '') {
            flash('No se encontró la factura que quieres emitir.', 'error');
            redirect('platform-invoices');
        }

        try {
            PlatformInvoiceRepository::issue($id);
        } catch (Throwable $exception) {
            log_server_error($exception, 'invoice');
            flash('No se pudo emitir la factura.', 'error');
            redirect('platform-invoices');
        }

        flash('Factura emitida correctamente.');
        redirect('platform-invoices');
    }

    private static function addPlatformInvoicePayment(): never
    {
        self::requirePlatformAdmin();
        $id = post_value('invoice_id', '');

        if ($id === '') {
            flash('No se encontró la factura para registrar el pago.', 'error');
            redirect('platform-invoices');
        }

        try {
            PlatformInvoiceRepository::addPayment($id, $_POST);
        } catch (Throwable $exception) {
            log_server_error($exception, 'payment');
            flash('No se pudo registrar el pago.', 'error');
            redirect('platform-invoices');
        }

        flash('Pago registrado en la factura.');
        redirect('platform-invoices');
    }

    private static function createClientInvoice(): never
    {
        $empresa = EmpresaRepository::findByTenant(Auth::tenantId());
        if (!$empresa) {
            flash('No se encontró la empresa emisora.', 'error');
            redirect('billing');
        }
        try {
            PlatformInvoiceRepository::create(array_merge($_POST, ['empresa_id' => $empresa['id'], 'invoice_scope' => 'CLIENT']));
            flash('Factura creada correctamente.');
        } catch (Throwable $exception) {
            log_server_error($exception, 'invoice');
            flash('No se pudo crear la factura.', 'error');
        }
        redirect('billing');
    }

    private static function updateClientInvoice(): never
    {
        $invoice = self::clientInvoiceFromPost('id');
        try {
            PlatformInvoiceRepository::update($invoice['id'], array_merge($_POST, ['empresa_id' => $invoice['empresa_id']]));
            flash('Factura actualizada correctamente.');
        } catch (Throwable $exception) {
            log_server_error($exception, 'invoice');
            flash('No se pudo actualizar la factura.', 'error');
        }
        redirect('billing');
    }

    private static function issueClientInvoice(): never
    {
        $invoice = self::clientInvoiceFromPost('id');
        try {
            PlatformInvoiceRepository::issue($invoice['id']);
            flash('Factura emitida correctamente.');
        } catch (Throwable $exception) {
            log_server_error($exception, 'invoice');
            flash('No se pudo emitir la factura.', 'error');
        }
        redirect('billing');
    }

    private static function addClientInvoicePayment(): never
    {
        $invoice = self::clientInvoiceFromPost('invoice_id');
        try {
            PlatformInvoiceRepository::addPayment($invoice['id'], $_POST);
            flash('Pago registrado en la factura.');
        } catch (Throwable $exception) {
            log_server_error($exception, 'payment');
            flash('No se pudo registrar el pago.', 'error');
        }
        redirect('billing');
    }

    private static function clientInvoiceFromPost(string $field): array
    {
        $empresa = EmpresaRepository::findByTenant(Auth::tenantId());
        $invoice = $empresa ? PlatformInvoiceRepository::findWithEmpresa(post_value($field, ''), 'CLIENT', (string) $empresa['id']) : null;
        if (!$invoice) {
            flash('No se encontró la factura.', 'error');
            redirect('billing');
        }
        return $invoice;
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
            flash('No se encontró la empresa.', 'error');
            redirect('platform-dashboard');
        }

        Auth::enterTenantContext($empresa);
        flash('Estas viendo la plataforma de ' . $empresa['name'] . '.');
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
            flash('Solo un superadmin de Membora puede acceder a esta sección.', 'error');
            redirect('dashboard');
        }
    }

    private static function createPlatformUser(): never
    {
        self::requirePlatformAdmin();

        $name = trim(post_value('name', ''));
        $email = strtolower(trim(post_value('email', '')));
        $password = post_value('password', '');
        $status = self::userStatusFromPost();

        if ($name === '' || $email === '' || $password === '') {
            flash('Indica nombre, email y contraseña.', 'error');
            redirect('platform-users');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('El email del administrador no es válido.', 'error');
            redirect('platform-users');
        }
        if (strlen($password) < 12) {
            flash('La contraseña debe tener al menos 12 caracteres.', 'error');
            redirect('platform-users');
        }
        if (UserRepository::emailExists(null, $email)) {
            flash('Ya existe un usuario con ese email.', 'error');
            redirect('platform-users');
        }

        $roleId = UserRepository::platformRoleId();
        if ($roleId === null) {
            flash('No existe el rol de superadministrador en la base de datos.', 'error');
            redirect('platform-users');
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO users (id, tenant_id, role_id, name, email, password_hash, status, created_at, updated_at)
             VALUES (:id, NULL, :role_id, :name, :email, :password_hash, :status, NOW(), NOW())'
        );
        $stmt->execute([
            'id' => cuid(),
            'role_id' => $roleId,
            'name' => $name,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            'status' => $status,
        ]);

        flash('Administrador creado correctamente.');
        redirect('platform-users');
    }

    private static function updatePlatformUser(): never
    {
        self::requirePlatformAdmin();

        $userId = post_value('id', '');
        $name = trim(post_value('name', ''));
        $email = strtolower(trim(post_value('email', '')));
        $password = post_value('password', '');
        $status = self::userStatusFromPost();
        $target = UserRepository::platformFind($userId);

        if (!$target) {
            flash('No se encontró el administrador.', 'error');
            redirect('platform-users');
        }
        if ($name === '' || $email === '') {
            flash('Indica nombre y email.', 'error');
            redirect('platform-users');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('El email del administrador no es válido.', 'error');
            redirect('platform-users');
        }
        if ($password !== '' && strlen($password) < 12) {
            flash('La nueva contraseña debe tener al menos 12 caracteres.', 'error');
            redirect('platform-users');
        }
        if (UserRepository::emailExists(null, $email, $userId)) {
            flash('Ya existe otro usuario con ese email.', 'error');
            redirect('platform-users');
        }

        $currentUserId = (string) (Auth::user()['id'] ?? '');
        if ($userId === $currentUserId && $status !== 'ACTIVE') {
            flash('No puedes desactivar tu propia cuenta.', 'error');
            redirect('platform-users');
        }

        $metrics = UserRepository::platformMetrics();
        if ($target['status'] === 'ACTIVE' && $status === 'INACTIVE' && $metrics['active'] <= 1) {
            flash('Debe quedar al menos un superadministrador activo.', 'error');
            redirect('platform-users');
        }

        UserRepository::updatePlatform(
            $userId,
            $name,
            $email,
            $status,
            $password !== '' ? password_hash($password, PASSWORD_BCRYPT) : null
        );

        if ($userId === $currentUserId) {
            $_SESSION['user']['name'] = $name;
            $_SESSION['user']['email'] = $email;
        }

        flash('Administrador actualizado correctamente.');
        redirect('platform-users');
    }

    private static function deletePlatformUser(): never
    {
        self::requirePlatformAdmin();

        $userId = post_value('id', '');
        $target = UserRepository::platformFind($userId);
        if (!$target) {
            flash('No se encontró el administrador.', 'error');
            redirect('platform-users');
        }
        if ($userId === (string) (Auth::user()['id'] ?? '')) {
            flash('No puedes eliminar tu propia cuenta.', 'error');
            redirect('platform-users');
        }

        $metrics = UserRepository::platformMetrics();
        if ($metrics['total'] <= 1 || ($target['status'] === 'ACTIVE' && $metrics['active'] <= 1)) {
            flash('Debe quedar al menos un superadministrador activo.', 'error');
            redirect('platform-users');
        }

        try {
            UserRepository::deletePlatform($userId);
        } catch (Throwable $exception) {
            log_server_error($exception, 'delete_platform_user');
            flash('No se pudo eliminar el administrador porque tiene actividad relacionada.', 'error');
            redirect('platform-users');
        }

        flash('Administrador eliminado correctamente.');
        redirect('platform-users');
    }

    private static function createUser(): never
    {
        $tenantId = Auth::tenantId();
        $name = post_value('name', '');
        $email = strtolower(post_value('email', ''));
        $password = post_value('password', '');
        $roleId = post_value('role_id', '');

        if ($name === '' || $email === '' || $password === '' || $roleId === '') {
            flash('Indica nombre, email, contraseña y rol.', 'error');
            redirect('users');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('El email del usuario no es válido.', 'error');
            redirect('users');
        }

        if (strlen($password) < 8) {
            flash('La contraseña debe tener al menos 8 caracteres.', 'error');
            redirect('users');
        }

        $roleKey = UserRepository::roleKey($roleId);
        if ($roleKey === null || !UserMutationPolicy::mayAssignRole(Auth::user() ?? [], $roleKey)) {
            flash('Selecciona un rol válido.', 'error');
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
            flash('El email del usuario no es válido.', 'error');
            redirect('users');
        }

        if ($password !== '' && strlen($password) < 8) {
            flash('La nueva contraseña debe tener al menos 8 caracteres.', 'error');
            redirect('users');
        }

        $roleKey = UserRepository::roleKey($roleId);
        if ($roleKey === null || !UserMutationPolicy::mayAssignRole(Auth::user() ?? [], $roleKey)) {
            flash('Selecciona un rol válido.', 'error');
            redirect('users');
        }

        if (!UserRepository::belongsToTenant($userId, $tenantId)) {
            flash('No se encontró el usuario en este centro.', 'error');
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
            flash('Lead convertido a cliente.');
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
        RiskAlertRepository::ensureTable();
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
        PaymentRepository::ensureTable();
        CheckinRepository::ensureTable();
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

            $deleteCheckins = $pdo->prepare('DELETE FROM checkins WHERE member_id = :id AND tenant_id = :tenant_id');
            $deleteCheckins->execute(['id' => $memberId, 'tenant_id' => $tenantId]);

            $deletePayments = $pdo->prepare('DELETE FROM payments WHERE member_id = :id AND tenant_id = :tenant_id');
            $deletePayments->execute(['id' => $memberId, 'tenant_id' => $tenantId]);

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

    private static function renewMemberSubscription(): never
    {
        $memberId = post_value('id', '');
        if ($memberId === '') {
            flash('No se encontró el socio seleccionado.', 'error');
            redirect('members');
        }

        try {
            MembershipRepository::renewMemberSubscription(Auth::tenantId(), $memberId);
        } catch (Throwable $exception) {
            log_server_error($exception, 'membership');
            flash('No se pudo renovar la membresía.', 'error');
            redirect('members');
        }

        flash('Membresía renovada y pago registrado correctamente.');
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
        $mimeType = is_array($imageInfo) ? (string) $imageInfo['mime'] : '';
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
            flash('Indica el nombre de la membresía.', 'error');
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

        flash('Membresía creada correctamente.');
        redirect('memberships');
    }

    private static function updateMembershipPlan(): never
    {
        MembershipRepository::ensureTables();
        $name = post_value('name', '');

        if ($name === '') {
            flash('Indica el nombre de la membresía.', 'error');
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

        flash('Membresía actualizada correctamente.');
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
            flash('No se puede eliminar una membresía asignada a socios. Desactivala si ya no se vende.', 'error');
            redirect('memberships');
        }

        $stmt = $pdo->prepare('DELETE FROM membership_plans WHERE id = :id AND tenant_id = :tenant_id');
        $stmt->execute(['id' => $planId, 'tenant_id' => $tenantId]);

        flash('Membresía eliminada correctamente.');
        redirect('memberships');
    }

    private static function membershipPeriodFromPost(): string
    {
        $period = post_value('billing_period', 'MONTHLY');
        return in_array($period, ['WEEKLY', 'MONTHLY', 'BIMONTHLY', 'QUARTERLY', 'YEARLY'], true) ? $period : 'MONTHLY';
    }

    private static function priceFromPost(): string
    {
        $price = str_replace(',', '.', post_value('price', '0') ?? '0');
        return number_format(max(0, (float) $price), 2, '.', '');
    }

    private static function createPayment(): never
    {
        try {
            PaymentRepository::create(Auth::tenantId(), $_POST);
        } catch (Throwable $exception) {
            log_server_error($exception, 'payment');
            flash('No se pudo registrar el pago.', 'error');
            redirect('payments');
        }

        flash('Pago registrado correctamente.');
        redirect('payments');
    }

    private static function updatePayment(): never
    {
        $id = post_value('id', '');
        if ($id === '') {
            flash('No se encontró el pago seleccionado.', 'error');
            redirect('payments');
        }

        try {
            PaymentRepository::update(Auth::tenantId(), $id, $_POST);
        } catch (Throwable $exception) {
            log_server_error($exception, 'payment');
            flash('No se pudo actualizar el pago.', 'error');
            redirect('payments');
        }

        flash('Pago actualizado correctamente.');
        redirect('payments');
    }

    private static function markPaymentPaid(): never
    {
        $id = post_value('id', '');
        if ($id === '') {
            flash('No se encontró el pago seleccionado.', 'error');
            redirect('payments');
        }

        try {
            PaymentRepository::markPaid(Auth::tenantId(), $id, $_POST);
        } catch (Throwable $exception) {
            log_server_error($exception, 'payment');
            flash('No se pudo marcar el pago como cobrado.', 'error');
            redirect('payments');
        }

        flash('Pago marcado como cobrado correctamente.');
        redirect('payments');
    }

    private static function generateRecurringPayments(): never
    {
        try {
            $untilDate = post_value('until_date', '') ?: date('Y-m-d');
            $created = PaymentRepository::generateRecurringDrafts(Auth::tenantId(), $untilDate);
        } catch (Throwable $exception) {
            log_server_error($exception, 'payment');
            flash('No se pudieron generar los borradores recurrentes.', 'error');
            redirect('payments');
        }

        flash('Borradores recurrentes generados: ' . $created . '.');
        redirect('payments');
    }

    private static function deletePayment(): never
    {
        $id = post_value('id', '');
        if ($id === '') {
            flash('No se encontró el pago seleccionado.', 'error');
            redirect('payments');
        }

        PaymentRepository::delete(Auth::tenantId(), $id);
        flash('Pago eliminado correctamente.');
        redirect('payments');
    }

    private static function createCheckin(): never
    {
        try {
            CheckinRepository::create(Auth::tenantId(), $_POST);
        } catch (Throwable $exception) {
            log_server_error($exception, 'checkin');
            flash('No se pudo registrar el check-in.', 'error');
            redirect('checkins');
        }

        flash('Check-in registrado correctamente.');
        redirect('checkins');
    }

    private static function deleteCheckin(): never
    {
        $id = post_value('id', '');
        if ($id === '') {
            flash('No se encontró el check-in seleccionado.', 'error');
            redirect('checkins');
        }

        CheckinRepository::delete(Auth::tenantId(), $id);
        flash('Check-in eliminado correctamente.');
        redirect('checkins');
    }

    private static function updateRiskAlertStatus(): never
    {
        $id = post_value('id', '');
        $status = post_value('status', 'OPEN');
        if ($id === '') {
            flash('No se encontró la alerta seleccionada.', 'error');
            redirect('alerts');
        }

        RiskAlertRepository::updateStatus(Auth::tenantId(), $id, $status);
        flash(match ($status) {
            'RESOLVED' => 'Alerta resuelta correctamente.',
            'DISMISSED' => 'Alerta descartada correctamente.',
            default => 'Alerta reabierta correctamente.',
        });
        redirect('alerts');
    }

    private static function saveBillingIntegration(): never
    {
        BillingIntegrationRepository::saveSettings(Auth::tenantId(), $_POST);
        flash('Integración de facturación guardada.');
        redirect('billing');
    }

    private static function syncBillingIntegration(): never
    {
        try {
            $result = BillingIntegrationRepository::sync(Auth::tenantId());
            flash('Sincronizacion completada: ' . (int) $result['count'] . ' pagos enviados por ' . money_amount($result['total']) . '.');
        } catch (Throwable $exception) {
            log_server_error($exception, 'class_session');
            flash('No se pudo completar la operación.', 'error');
        }

        redirect('billing');
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
            flash('Indica clase, fecha, hora de inicio y hora de finalización.', 'error');
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
            flash('Indica clase, fecha, hora de inicio y hora de finalización.', 'error');
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
        CheckinRepository::ensureTable();
        $tenantId = Auth::tenantId();
        $sessionId = post_value('id');
        $pdo = Database::connection();

        $pdo->beginTransaction();
        try {
            $deleteReservations = $pdo->prepare('DELETE FROM reservations WHERE class_session_id = :id AND tenant_id = :tenant_id');
            $deleteReservations->execute(['id' => $sessionId, 'tenant_id' => $tenantId]);

            $deleteCheckins = $pdo->prepare('DELETE FROM checkins WHERE class_session_id = :id AND tenant_id = :tenant_id');
            $deleteCheckins->execute(['id' => $sessionId, 'tenant_id' => $tenantId]);

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

        ReservationRepository::ensureTable();
        CheckinRepository::ensureTable();
        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();
            $reservationId = ReservationRepository::create($tenantId, $memberId, $sessionId, false);
            CheckinRepository::create($tenantId, [
                'member_id' => $memberId,
                'class_session_id' => $sessionId,
                'reservation_id' => $reservationId,
                'method' => 'AUTOMATIC',
                'notes' => 'Creado automáticamente al reservar la clase.',
            ], false);
            $pdo->commit();

            try {
                AuditLogRepository::record('create_checkin', [
                    'member_id' => $memberId,
                    'class_session_id' => $sessionId,
                    'reservation_id' => $reservationId,
                    'method' => 'AUTOMATIC',
                ]);
            } catch (Throwable $auditException) {
                $_SESSION['audit_log_error'] = $auditException->getMessage();
            }

            flash('Reserva y check-in creados correctamente.');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            log_server_error($exception, 'reservation');
            flash('No se pudo crear la reserva y su check-in.', 'error');
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
            flash('No se encontró la reserva seleccionada.', 'error');
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
            log_server_error($exception, 'reservation');
            flash('No se pudo actualizar la reserva.', 'error');
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
            flash('La hora de finalización debe ser posterior a la hora de inicio.', 'error');
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
            flash('Indica un título para la tarea.', 'error');
            redirect('tasks');
        }

        TaskRepository::ensureMemberLinksTable();
        $tenantId = Auth::tenantId();
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
                'member_id' => null,
                'title' => $title,
                'description' => post_value('description') ?: null,
                'type' => post_value('type', 'OTHER'),
                'due_at' => post_value('due_at') ?: null,
            ]);

            self::syncTaskMembers($pdo, $tenantId, $taskId, []);

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
            flash('Indica un título para la tarea.', 'error');
            redirect('tasks');
        }

        TaskRepository::ensureMemberLinksTable();
        $tenantId = Auth::tenantId();
        $taskId = post_value('id');
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
                'member_id' => null,
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

            self::syncTaskMembers($pdo, $tenantId, (string) $taskId, []);
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
        RiskAlertRepository::ensureTable();
        $deleteLinks = $pdo->prepare('DELETE FROM task_members WHERE task_id = :id AND tenant_id = :tenant_id');
        $deleteLinks->execute(['id' => $taskId, 'tenant_id' => $tenantId]);
        $alerts = $pdo->prepare('DELETE FROM risk_alerts WHERE task_id = :id AND tenant_id = :tenant_id');
        $alerts->execute(['id' => $taskId, 'tenant_id' => $tenantId]);
        $stmt = $pdo->prepare('DELETE FROM tasks WHERE id = :id AND tenant_id = :tenant_id');
        $stmt->execute(['id' => $taskId, 'tenant_id' => $tenantId]);
        redirect('tasks');
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

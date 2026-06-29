<?php

final class Mailer
{
    private static string $lastError = '';

    public static function sendWebLeadConfirmation(array $payload, string $leadId): bool
    {
        self::$lastError = '';

        if (strtolower((string) (getenv('MAIL_ENABLED') ?: 'true')) === 'false') {
            return true;
        }

        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            self::$lastError = 'El lead no tiene un email valido.';
            return false;
        }

        $name = trim((string) (($payload['nombre'] ?? '') . ' ' . ($payload['apellidos'] ?? '')));
        if ($name === '') {
            $name = 'Hola';
        }

        $company = trim((string) ($payload['empresa'] ?? $payload['company'] ?? $payload['company_name'] ?? ''));
        $subject = 'Hemos recibido tu solicitud en Membora CRM';
        $html = self::webLeadTemplate($name, $company, $leadId);

        if (self::usesSmtp()) {
            return self::sendSmtp($email, $subject, $html);
        }

        return self::sendNativeMail($email, $subject, $html);
    }

    public static function lastError(): string
    {
        return self::$lastError;
    }

    public static function diagnostics(): array
    {
        $password = (string) (getenv('SMTP_PASSWORD') ?: '');

        return [
            'enabled' => strtolower((string) (getenv('MAIL_ENABLED') ?: 'true')) !== 'false' ? 'Si' : 'No',
            'transport' => self::usesSmtp() ? 'SMTP' : 'PHP mail()',
            'from_email' => self::fromEmail(),
            'reply_to' => trim((string) (getenv('MAIL_REPLY_TO') ?: self::fromEmail())),
            'smtp_host' => trim((string) (getenv('SMTP_HOST') ?: 'Sin configurar')),
            'smtp_port' => (string) (getenv('SMTP_PORT') ?: '587'),
            'smtp_encryption' => trim((string) (getenv('SMTP_ENCRYPTION') ?: 'tls')),
            'smtp_username' => trim((string) (getenv('SMTP_USERNAME') ?: 'Sin configurar')),
            'smtp_password' => $password === '' ? 'Sin configurar' : str_repeat('*', min(12, max(6, strlen($password)))),
        ];
    }

    public static function sendDebugEmail(string $email): bool
    {
        self::$lastError = '';
        $email = strtolower(trim($email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            self::$lastError = 'Email de prueba no valido.';
            return false;
        }

        $html = self::debugTemplate();
        if (self::usesSmtp()) {
            return self::sendSmtp($email, 'Prueba de correo - Membora CRM', $html);
        }

        return self::sendNativeMail($email, 'Prueba de correo - Membora CRM', $html);
    }

    private static function sendNativeMail(string $to, string $subject, string $html): bool
    {
        $fromEmail = self::fromEmail();
        $fromName = self::headerText((string) (getenv('MAIL_FROM_NAME') ?: 'Membora CRM'));
        $replyTo = trim((string) (getenv('MAIL_REPLY_TO') ?: $fromEmail));
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $fromName . ' <' . $fromEmail . '>',
            'Reply-To: ' . $replyTo,
            'X-Mailer: Membora CRM',
        ];

        $sent = @mail($to, self::encodedSubject($subject), $html, implode("\r\n", $headers), '-f' . $fromEmail);
        if (!$sent) {
            self::$lastError = 'PHP mail() ha devuelto false. Revisa si Plesk permite correo saliente o configura SMTP.';
        }

        return $sent;
    }

    private static function sendSmtp(string $to, string $subject, string $html): bool
    {
        $host = trim((string) (getenv('SMTP_HOST') ?: ''));
        $port = (int) (getenv('SMTP_PORT') ?: 587);
        $encryption = strtolower(trim((string) (getenv('SMTP_ENCRYPTION') ?: 'tls')));
        $username = trim((string) (getenv('SMTP_USERNAME') ?: ''));
        $password = (string) (getenv('SMTP_PASSWORD') ?: '');
        $fromEmail = self::fromEmail();

        if ($host === '') {
            self::$lastError = 'SMTP_HOST no esta configurado.';
            return false;
        }

        $target = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client($target, $errno, $errstr, 20, STREAM_CLIENT_CONNECT);
        if (!$socket) {
            self::$lastError = 'No se pudo conectar con SMTP: ' . ($errstr ?: ('error ' . $errno));
            return false;
        }

        stream_set_timeout($socket, 20);

        try {
            self::smtpExpect($socket, [220]);
            self::smtpCommand($socket, 'EHLO ' . self::smtpDomain(), [250]);

            if ($encryption === 'tls') {
                self::smtpCommand($socket, 'STARTTLS', [220]);
                if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('No se pudo activar TLS en SMTP.');
                }
                self::smtpCommand($socket, 'EHLO ' . self::smtpDomain(), [250]);
            }

            if ($username !== '') {
                self::smtpCommand($socket, 'AUTH LOGIN', [334]);
                self::smtpCommand($socket, base64_encode($username), [334]);
                self::smtpCommand($socket, base64_encode($password), [235]);
            }

            self::smtpCommand($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]);
            self::smtpCommand($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
            self::smtpCommand($socket, 'DATA', [354]);
            fwrite($socket, self::smtpMessage($to, $subject, $html) . "\r\n.\r\n");
            self::smtpExpect($socket, [250]);
            self::smtpCommand($socket, 'QUIT', [221]);
            fclose($socket);

            return true;
        } catch (Throwable $exception) {
            if (is_resource($socket)) {
                @fwrite($socket, "QUIT\r\n");
                @fclose($socket);
            }
            self::$lastError = $exception->getMessage();
            return false;
        }
    }

    private static function smtpMessage(string $to, string $subject, string $html): string
    {
        $fromEmail = self::fromEmail();
        $fromName = self::headerText((string) (getenv('MAIL_FROM_NAME') ?: 'Membora CRM'));
        $replyTo = trim((string) (getenv('MAIL_REPLY_TO') ?: $fromEmail));
        $messageIdHost = parse_url(app_base_url(), PHP_URL_HOST) ?: 'membora.local';
        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'To: <' . $to . '>',
            'From: ' . $fromName . ' <' . $fromEmail . '>',
            'Reply-To: ' . $replyTo,
            'Subject: ' . self::encodedSubject($subject),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $messageIdHost . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'X-Mailer: Membora CRM',
        ];

        $body = str_replace(["\r\n", "\r"], "\n", $html);
        $body = preg_replace('/^\./m', '..', $body);

        return implode("\r\n", $headers) . "\r\n\r\n" . str_replace("\n", "\r\n", $body);
    }

    private static function smtpCommand(mixed $socket, string $command, array $expectedCodes): string
    {
        fwrite($socket, $command . "\r\n");
        return self::smtpExpect($socket, $expectedCodes);
    }

    private static function smtpExpect(mixed $socket, array $expectedCodes): string
    {
        $response = '';
        do {
            $line = fgets($socket, 515);
            if ($line === false) {
                throw new RuntimeException('SMTP no respondio.');
            }
            $response .= $line;
            $done = strlen($line) >= 4 && $line[3] === ' ';
        } while (!$done);

        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $expectedCodes, true)) {
            throw new RuntimeException('Respuesta SMTP inesperada: ' . trim($response));
        }

        return $response;
    }

    private static function usesSmtp(): bool
    {
        return strtolower((string) (getenv('MAIL_MAILER') ?: '')) === 'smtp' || trim((string) (getenv('SMTP_HOST') ?: '')) !== '';
    }

    private static function webLeadTemplate(string $name, string $company, string $leadId): string
    {
        $safeName = e($name);
        $safeCompany = $company !== '' ? e($company) : 'tu centro';
        $webUrl = e((string) (getenv('WEB_APP_URL') ?: 'https://app.web.josehurtado.dev'));
        $emailLogo = self::emailLogoHtml(48);

        return <<<HTML
<!doctype html>
<html lang="es">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Solicitud recibida</title>
  </head>
  <body style="margin:0;background:#f4f7fb;font-family:Arial,Helvetica,sans-serif;color:#0b172a;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f7fb;padding:32px 14px;">
      <tr>
        <td align="center">
          <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border-radius:22px;overflow:hidden;border:1px solid #dce6f5;box-shadow:0 20px 50px rgba(15,23,42,.10);">
            <tr>
              <td style="background:#0754d6;padding:28px 32px;color:#ffffff;">
                {$emailLogo}
                <span style="font-size:23px;font-weight:900;vertical-align:middle;">Membora CRM</span>
              </td>
            </tr>
            <tr>
              <td style="padding:34px 32px 18px;">
                <p style="margin:0 0 10px;color:#0754d6;font-weight:800;text-transform:uppercase;font-size:12px;letter-spacing:.08em;">Solicitud recibida</p>
                <h1 style="margin:0 0 16px;font-size:30px;line-height:1.18;color:#071327;">Gracias, {$safeName}</h1>
                <p style="margin:0 0 18px;font-size:16px;line-height:1.65;color:#334155;">
                  Hemos recibido correctamente tu solicitud para <strong>{$safeCompany}</strong>. Una persona del equipo de Membora CRM revisara la informacion y contactara contigo en un plazo aproximado de <strong>24 a 48 horas</strong>.
                </p>
                <div style="margin:26px 0;padding:20px;border-radius:16px;background:#eef4ff;border:1px solid #cfe0ff;">
                  <p style="margin:0 0 8px;font-size:14px;color:#0754d6;font-weight:800;">Que ocurre ahora</p>
                  <ul style="margin:0;padding-left:20px;color:#1f3657;font-size:15px;line-height:1.7;">
                    <li>Revisaremos las necesidades de tu centro.</li>
                    <li>Te propondremos una demo o una llamada breve.</li>
                    <li>Resolveremos dudas sobre leads, socios, clases y membresias.</li>
                  </ul>
                </div>
              </td>
            </tr>
            <tr>
              <td style="padding:0 32px 34px;">
                <a href="{$webUrl}" style="display:inline-block;background:#0754d6;color:#ffffff;text-decoration:none;font-weight:800;padding:14px 20px;border-radius:12px;">Volver a Membora CRM</a>
              </td>
            </tr>
            <tr>
              <td style="padding:22px 32px;background:#f8fafc;color:#64748b;font-size:13px;line-height:1.5;">Este correo confirma que el formulario se ha enviado correctamente. Si no has solicitado informacion sobre Membora CRM, puedes ignorarlo.</td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </body>
</html>
HTML;
    }

    private static function debugTemplate(): string
    {
        $emailLogo = self::emailLogoHtml(44);

        return <<<HTML
<!doctype html>
<html lang="es">
  <body style="margin:0;background:#f4f7fb;font-family:Arial,Helvetica,sans-serif;color:#0b172a;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f7fb;padding:30px 14px;">
      <tr>
        <td align="center">
          <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;background:#ffffff;border-radius:20px;border:1px solid #dce6f5;overflow:hidden;">
            <tr>
              <td style="background:#0754d6;padding:24px 28px;color:#ffffff;">
                {$emailLogo}
                <span style="font-size:22px;font-weight:900;vertical-align:middle;">Membora CRM</span>
              </td>
            </tr>
            <tr>
              <td style="padding:30px 28px;">
                <p style="margin:0 0 10px;color:#0754d6;font-weight:800;text-transform:uppercase;font-size:12px;letter-spacing:.08em;">Prueba tecnica</p>
                <h1 style="margin:0 0 14px;font-size:26px;color:#071327;">El correo de Membora CRM funciona</h1>
                <p style="margin:0;color:#334155;font-size:16px;line-height:1.6;">Si estas viendo este mensaje, el SMTP configurado en Plesk esta enviando correctamente correos desde el CRM.</p>
              </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </body>
</html>
HTML;
    }

    private static function fromEmail(): string
    {
        $configured = trim((string) (getenv('MAIL_FROM_EMAIL') ?: ''));
        if ($configured !== '' && filter_var($configured, FILTER_VALIDATE_EMAIL)) {
            return $configured;
        }

        $host = parse_url(app_base_url(), PHP_URL_HOST) ?: 'josehurtado.dev';
        return 'no-reply@' . $host;
    }

    private static function emailLogoHtml(int $size): string
    {
        $innerSize = max(36, $size);
        $fontSize = $size >= 48 ? 24 : 22;
        $radius = $size >= 48 ? 14 : 12;
        $margin = $size >= 48 ? 12 : 10;

        return '<span aria-label="Membora CRM" style="display:inline-block;width:' . $innerSize . 'px;height:' . $innerSize . 'px;line-height:' . $innerSize . 'px;border-radius:' . $radius . 'px;background:#ffffff;color:#0754d6;text-align:center;font-size:' . $fontSize . 'px;font-weight:900;vertical-align:middle;margin-right:' . $margin . 'px;">M</span>';
    }

    private static function encodedSubject(string $subject): string
    {
        return '=?UTF-8?B?' . base64_encode($subject) . '?=';
    }

    private static function headerText(string $text): string
    {
        return str_replace(["\r", "\n"], '', $text);
    }

    private static function smtpDomain(): string
    {
        return parse_url(app_base_url(), PHP_URL_HOST) ?: 'localhost';
    }
}

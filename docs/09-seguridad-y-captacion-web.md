# Seguridad y captacion web

## Objetivo

Este documento resume las medidas de seguridad aplicadas en Membora CRM y deja documentadas las dos estrategias posibles para recibir solicitudes desde la web publica:

- Captacion mediante webhook HTTP.
- Captacion mediante insercion directa en base de datos desde la web PHP.

La version actual mantiene el webhook como flujo operativo y deja preparada conceptualmente la alternativa por base de datos para un despliegue mas simple en Plesk si se decide migrar.

## Relación con la metodología

La seguridad se trata como criterio transversal en cada incremento, no como una revisión añadida al final. Las especificaciones definen validación, permisos, aislamiento y criterios de aceptación; PHPUnit comprueba las reglas aislables; Playwright valida recorridos completos en un entorno preparado; y GitHub Actions ejecuta las puertas de calidad antes del despliegue. El proceso completo está descrito en `docs/19-metodologia-desarrollo.md`.

## Principios de seguridad aplicados

### 1. Aislamiento por empresa

Los datos del CRM de gimnasios se separan mediante:

```text
tenant_id
```

Las consultas de modulos operativos deben filtrar siempre por `tenant_id`.

Esto aplica a:

- Leads.
- Socios.
- Membresias.
- Clases.
- Reservas.
- Tareas.
- Usuarios internos.
- Pagos, check-ins, alertas y auditoria.

La administracion SaaS de Membora CRM usa tablas de plataforma como:

- `platform_leads`.
- `platform_clients`.
- `empresas`.
- `empresa_payments`.
- `saas_plans`.
- `platform_invoices`, `platform_invoice_items` y `platform_invoice_payments`.

### 2. Consultas preparadas

La app usa PDO y consultas preparadas para operaciones de lectura/escritura sensibles.

Objetivo:

- Reducir riesgo de SQL injection.
- Evitar concatenar valores de usuario en SQL.

### 3. Escape de salida HTML

La salida visible en vistas PHP debe pasar por:

```php
e($value)
```

Objetivo:

- Reducir riesgo de XSS almacenado o reflejado.

### 4. Sesiones endurecidas

La app configura la sesion PHP con:

- `session.use_strict_mode`.
- Cookies solo por cookie, no por URL.
- `HttpOnly`.
- `SameSite=Lax`.
- `Secure` cuando la peticion es HTTPS.
- Regeneracion de ID de sesion tras login.
- Limpieza de cookie al cerrar sesion.

Objetivo:

- Reducir fijacion de sesion.
- Reducir robo/uso de cookie por scripts.
- Reducir CSRF basico en navegadores modernos.

### 5. Autenticacion, recuerdo y recuperacion

- Los fallos de login se limitan por IP y hash SHA-256 del email; no se almacena el email en claro en `login_attempts`.
- La opcion `Recordarme` usa `auth_tokens`, cookie segura, selector/verificador y rotacion al restaurar la sesion.
- Cerrar sesion revoca el selector y elimina la cookie de recuerdo.
- La recuperacion responde de forma neutra para no confirmar si existe una cuenta.
- Los enlaces de restablecimiento caducan, son de un solo uso y nunca almacenan el token completo en claro.
- Cambiar la contrasena revoca los tokens anteriores del usuario.
- El alta `TRIAL` genera una contrasena aleatoria y envia por correo un enlace de entrega, nunca la contrasena. La credencial temporal se cifra con AES-256-GCM y una clave derivada de `APP_KEY` (o de `DB_PASSWORD` en instalaciones antiguas); el enlace exige un POST con CSRF, caduca en una hora y se marca como visto antes de mostrar la contrasena. Una recarga o segundo acceso ya no puede recuperarla.

### 6. Validacion de origen y CSRF en formularios internos

Las acciones POST internas del CRM validan el origen usando:

- `Origin`.
- `Referer`.
- `APP_URL`.

Si una solicitud POST viene desde un origen externo al CRM, se bloquea.

Ademas, los formularios incluyen un token CSRF de 256 bits asociado a la sesion. `demo_login` es la unica excepcion al CSRF interno porque se inicia desde la web publica y aplica su politica de origen especifica.

Variable opcional:

```env
APP_STRICT_POST_ORIGIN="false"
```

Por defecto se permite la solicitud si el navegador no envia ni `Origin` ni `Referer`, para evitar problemas con navegadores/proxies. Si se quiere endurecer mas:

```env
APP_STRICT_POST_ORIGIN="true"
```

Con `true`, una solicitud POST sin origen/referer tambien se bloquea.

### 7. Cabeceras de seguridad

La app y la web publica aplican cabeceras:

```text
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()
Strict-Transport-Security: max-age=31536000; includeSubDomains
```

Nota:

- HSTS se envia desde PHP cuando la peticion es HTTPS.
- En Apache/Plesk se refuerzan cabeceras desde `.htaccess` cuando `mod_headers` esta disponible.

### 8. Subidas de imagenes

Las subidas de foto de socio y perfil validan:

- Error de subida.
- Tamano maximo 2 MB.
- MIME real mediante `getimagesize`.
- Tipos permitidos: JPG, PNG, WEBP.
- Carpeta limitada a `public/uploads/members` y `public/uploads/users`.

Objetivo:

- Evitar subir archivos arbitrarios.
- Reducir riesgo de ejecucion de contenido no esperado.

### 9. Captacion web anti-abuso

Durante la depuracion del alta gratuita, su limite especifico por IP y email queda desactivado por defecto con `TRIAL_RATE_LIMIT_ENABLED=false`. La validacion de origen y el honeypot siguen activos. El webhook general conserva su propio rate limit.

El formulario publico incluye:

- Honeypot invisible.
- Validacion de origen.
- Rate limit por IP.
- Validacion de email/telefono.
- Registro de logs tecnicos.
- Creacion del lead aunque falle el email de confirmacion.

### 10. Stripe y eventos externos

- La integracion solo se habilita con `PAYMENTS_MODE=stripe_test` y rechaza claves que no empiecen por `sk_test_`.
- `/stripe/webhook` exige `Stripe-Signature` y el secreto configurado en `STRIPE_WEBHOOK_SECRET`.
- `stripe_events` mantiene un identificador unico por evento para evitar efectos duplicados.
- La URL de exito de checkout nunca activa acceso ni marca un pago; solo lo hace el webhook verificado.
- Payloads, errores y diagnosticos no deben exponer claves secretas en vistas ni auditoria.

## Flujo actual: webhook HTTP

Flujo operativo actual:

```text
membora.es
  -> POST https://membora.es/app/webhook/lead
  -> validacion de origen WEB_APP_URL
  -> honeypot/rate limit
  -> insercion en platform_leads
  -> intento de email de confirmacion
  -> logs en la ruta interna ?route=platform-web
```

La ruta de diagnostico solo admite superadministradores y esta oculta del menu. Se conserva para depurar SMTP y captacion, no como modulo funcional de la demo.

Ventajas:

- Web y CRM pueden estar separados.
- Permite integraciones futuras externas.
- No expone credenciales de base de datos en la web publica.
- Centraliza validacion en el CRM.

Puntos a vigilar:

- `WEB_APP_URL` debe estar bien configurado.
- El endpoint debe estar disponible.
- CORS/origen debe coincidir.
- La web publica depende de que `app.crm` responda.

Configuracion esperada:

```env
APP_URL="https://membora.es/app"
WEB_APP_URL="https://membora.es,https://www.membora.es"
```

## Alternativa documentada: insercion directa en base de datos

Esta alternativa se recomienda si se quiere simplificar Plesk y evitar CORS/webhook para la web publica.

Flujo propuesto:

```text
membora.es/contact.php
  -> valida formulario en servidor
  -> conecta a la misma MariaDB
  -> inserta en platform_leads
  -> envia email de confirmacion
  -> devuelve JSON o pagina de exito
```

Ventajas:

- Menos dependencia de CORS.
- Menos piezas entre formulario y base de datos.
- Mas facil de explicar como despliegue monolitico PHP en Plesk.
- Funciona aunque se reduzca JavaScript.

Riesgos:

- La web publica necesita credenciales de base de datos.
- Hay que proteger bien `contact.php`.
- Si la web se mueve a otro servidor, el acceso directo a base de datos deja de ser ideal.

Requisitos minimos si se implementa:

- Usar PDO con consultas preparadas.
- No exponer credenciales en JavaScript.
- Guardar credenciales en `shared/config/.env` o config fuera de `public` si Plesk lo permite.
- Mantener honeypot.
- Mantener rate limit por IP.
- Validar email/telefono.
- Sanitizar longitudes.
- Insertar solo en `platform_leads`, no en tablas de gimnasio.
- Registrar errores tecnicos.
- Mantener confirmacion por email.

Tablas afectadas:

```text
platform_leads
webhook_logs o tabla equivalente de logs
```

## Decision actual

Estado actual recomendado:

```text
Mantener webhook como flujo activo.
Dejar documentada la alternativa directa por base de datos.
```

Motivo:

- El webhook ya funciona y crea leads en el panel de administracion.
- Es mejor para integraciones futuras.
- La alternativa por base de datos queda como mejora si el despliegue de Plesk necesita simplificacion.

## Checklist de seguridad antes de entrega

- [ ] Confirmar `APP_URL` correcto en `apps/crm/.env`.
- [ ] Confirmar `WEB_APP_URL` correcto en `apps/crm/.env`.
- [ ] Confirmar HTTPS activo en app y web.
- [ ] Confirmar que no hay listado de directorios.
- [ ] Probar login y comprobar que no rompe por origen.
- [ ] Probar formulario web real.
- [ ] Probar envio de correo real.
- [ ] Probar subida de imagen valida.
- [ ] Probar rechazo de imagen no permitida.
- [ ] Probar que un usuario de gimnasio no entra en Admin CRM.
- [ ] Probar que Admin CRM puede entrar/salir de modo soporte.
- [ ] Revisar que los datos de una empresa no aparecen en otra.

## Notas para el TFM

La seguridad del MVP se basa en defensa por capas:

- Separacion logica por `tenant_id`.
- Consultas preparadas.
- Escape de salida.
- Sesiones endurecidas.
- Validacion de origen en formularios.
- Cabeceras de seguridad.
- Validacion de uploads.
- Honeypot y rate limit en captacion web.
- Logs tecnicos para trazabilidad.
- Auditoria de acciones internas.
- Permisos por rol en rutas y acciones POST.

No se implementan todavia:

- 2FA.
- Cifrado campo a campo de datos personales.
- WAF dedicado.
- Backups automatizados desde la propia app.

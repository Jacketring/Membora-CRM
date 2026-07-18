# Endurecimiento de seguridad — 11 de julio de 2026

Este documento resume las correcciones aplicadas a la plataforma PHP para reforzar el aislamiento multi-tenant, la autenticacion, las credenciales y los endpoints publicos.

## 1. Control de roles y aislamiento multi-tenant

- Los roles `SUPER_ADMIN` y `SUPERADMIN` ya no aparecen entre los roles asignables desde la plataforma de un gimnasio.
- Las altas y ediciones de usuarios validan el rol en el servidor mediante `assignableRoleExists()`.
- Un POST manipulado con un rol de plataforma se rechaza incluso si el identificador existe en la tabla `roles`.
- Los usuarios no-plataforma sin `tenant_id` ya no reciben automaticamente el gimnasio mas antiguo. El acceso falla de forma segura.
- La actualizacion del perfil del administrador de plataforma se trata sin inventar un contexto de tenant.

## 2. Credenciales iniciales

- Se elimino la contraseña fija del administrador de plataforma del codigo, formularios y documentacion.
- La contraseña inicial se toma de `PLATFORM_ADMIN_PASSWORD`.
- Si no se configura al crear la cuenta por primera vez, se genera una contraseña aleatoria de 24 caracteres hexadecimales y se escribe una unica vez en el log del servidor.
- El formulario de login ya no incluye email ni contraseña pre-rellenados.
- Al crear una empresa, la contraseña del administrador es obligatoria, debe tener al menos ocho caracteres y nunca se pre-rellena.
- Las claves Stripe del archivo de ejemplo son placeholders; las claves funcionales deben existir solo en `.env`.

## 3. CSRF y seguridad de peticiones

- Se genera un token CSRF aleatorio de 256 bits por sesion.
- El renderizado añade el campo `csrf_token` a todos los formularios POST, incluidos los parciales.
- `Actions::handle()` valida el token antes de ejecutar acciones con estado.
- El login normal tambien queda protegido. `demo_login` conserva su excepcion porque se inicia desde la web publica y mantiene la validacion de origen dedicada.
- `APP_STRICT_POST_ORIGIN` queda activado por defecto en `.env.example`.

## 4. Autenticacion y contraseñas

- Cambiar la contraseña del perfil exige introducir y verificar la contraseña actual.
- Se crea automaticamente la tabla `login_attempts`.
- Los fallos se contabilizan por IP y por hash SHA-256 del email, sin guardar el email en claro.
- Tras cinco fallos durante quince minutos, los intentos posteriores se bloquean temporalmente con un mensaje neutro.
- Un login correcto elimina los intentos asociados a esa IP o email.

## 5. Webhook de leads

- El limite por IP se comprueba al principio de la peticion, antes de validar el token.
- `webhook_settings` incorpora `token_lookup`, un SHA-256 indexado del token.
- La busqueda localiza una unica fila por `token_lookup` y aplica `password_verify()` solo a esa fila.
- Los tokens nuevos y regenerados guardan el indice automaticamente.
- Los registros anteriores completan el indice al cargar su configuracion, siempre que puedan descifrar su token.
- El cifrado exige `APP_KEY`; se elimino la clave local debil y se exige OpenSSL.

## 6. Defensa en profundidad

- Cabecera CSP: `default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'`.
- HSTS se configura en PHP y Apache para conexiones HTTPS.
- `public/uploads/.htaccess` desactiva PHP, CGI e indexado de directorios.
- Las excepciones internas se envian a `error_log`; la interfaz recibe mensajes genericos.
- Se mantienen PDO con placeholders, el escapado `e()` y los filtros por `tenant_id`.

## 7. Configuracion requerida antes del despliegue

Definir en `apps/crm/.env`:

```dotenv
APP_STRICT_POST_ORIGIN="true"
APP_KEY="valor_aleatorio_de_alta_entropia"
PLATFORM_ADMIN_PASSWORD="contraseña_inicial_unica_de_12_o_mas_caracteres"
STRIPE_PUBLISHABLE_KEY="clave_publicable_vigente"
STRIPE_SECRET_KEY="clave_secreta_vigente"
STRIPE_WEBHOOK_SECRET="secreto_webhook_vigente"
```

`APP_KEY` debe establecerse antes de crear o regenerar tokens webhook. Las claves Stripe que hayan aparecido en codigo, historial, capturas o conversaciones deben revocarse desde Stripe, aunque sean de prueba.

## 8. Verificacion realizada

- `php -l` en todos los PHP modificados.
- `git diff --check` sin errores de whitespace.
- Busqueda de la contraseña fija, la clave de cifrado debil y las claves Stripe expuestas sin coincidencias en el arbol de trabajo.
- El repositorio no incluye actualmente una suite automatizada ni una base de datos aislada para ejecutar pruebas de integracion.

## 9. Pruebas recomendadas en staging

1. Crear y editar usuarios como `GYM_ADMIN`, comprobando que no aparece ni se acepta un rol de plataforma.
2. Crear una empresa sin contraseña y confirmar el error; repetir con una contraseña valida.
3. Probar formularios representativos y confirmar el rechazo de un POST sin CSRF.
4. Cambiar contraseña con valor actual incorrecto y correcto.
5. Confirmar el bloqueo del sexto intento fallido y el desbloqueo después de quince minutos.
6. Probar tokens webhook validos e invalidos, incluida la migracion de tokens anteriores.
7. Revisar la consola del navegador con CSP activa y probar carga de imagenes y assets.
8. Intentar servir un archivo PHP de prueba desde `public/uploads` y confirmar que Apache no lo ejecuta.

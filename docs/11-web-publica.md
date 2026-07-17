# Web publica de Membora CRM

Web comercial y CRM desplegados bajo un unico dominio:

```text
https://membora.es/       -> web publica
https://membora.es/app/   -> CRM
```

## Despliegue en Plesk

Configura `membora.es` como hosting PHP normal y apunta la raiz del documento a:

```text
httpdocs
```

No necesita Node.js, npm ni build.

## Planes comerciales

La landing no mantiene un catalogo independiente. Consulta primero `api/plans.php`, proxy de `/app/api/plans`, y si ese proxy falla prueba la ruta directa del CRM. Solo cuando fallan ambas utiliza el fallback incluido en `assets/site.js`.

El catalogo visible contiene Basic 49 EUR, Pro 89 EUR, Business 149 EUR y Enterprise 299 EUR al mes, siempre con el texto `Precios sin IVA.`, limites de usuarios y socios y prestaciones. Basic, Pro y Business enlazan a la prueba gratuita; Enterprise usa el CTA `Contactar`. Los datos estructurados `schema.org` se generan en el navegador a partir de los mismos planes que se muestran. Los importes ilustrativos de membresias de gimnasio se retiraron de los mockups para no confundirlos con precios SaaS.

## Demo temporal

La web no mantiene una demo estatica separada. Los enlaces de demo envian al CRM real y abren una sesion temporal con datos de prueba:

```text
https://membora.es/demo.html
```

`demo.html` actua como puente de entrada. Envia al login demo del CRM, inicia una sesion de 20 minutos, muestra un contador dentro de la aplicacion y al finalizar cierra sesion y devuelve al usuario a la web publica.

## Prueba gratuita de 14 dias

El formulario `Empieza gratis` no comparte los datos de la demo. Envia la solicitud a `api/trial.php`, que actua como proxy hacia `/app/api/trial`. El CRM verifica el email con un enlace de un solo uso y solo despues crea o actualiza el contacto como `Cliente CRM`, vincula su empresa, crea el tenant y su administrador en estado `TRIAL` durante 14 dias. El sistema genera una contrasena inicial aleatoria y envia un segundo correo con un enlace temporal: la credencial no viaja en el mensaje, permanece cifrada, requiere confirmacion para revelarse y solo puede verse una vez durante una hora.

La validacion de origen y el honeypot permanecen activos. El limite adicional del alta por IP y email esta temporalmente desactivado por defecto mientras se depura el flujo y puede reactivarse con `TRIAL_RATE_LIMIT_ENABLED=true`.

El flujo requiere SMTP activo porque no se provisionan cuentas sin verificar el correo. El limite por IP y email permanece implementado como proteccion configurable, aunque no se aplica con el valor predeterminado actual.

## Conexion con el CRM

El formulario envia leads al webhook del CRM:

```text
https://membora.es/app/webhook/lead
```

No hay que configurar tokens en esta web. El CRM acepta envios desde el dominio definido en `WEB_APP_URL` y crea las solicitudes en `Admin CRM > Leads`, donde el administrador puede gestionarlas o convertirlas en clientes.
El correo de confirmacion al visitante no se configura en esta web, sino en el `.env` del CRM mediante `MAIL_MAILER`, `MAIL_FROM_EMAIL`, `MAIL_FROM_NAME`, `MAIL_REPLY_TO` y los datos `SMTP_*`.

En produccion revisa que el `.env` del CRM tenga:

```env
APP_URL="https://membora.es/app"
WEB_APP_URL="https://membora.es,https://www.membora.es"
```

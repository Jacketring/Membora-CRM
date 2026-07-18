# Especificacion: prueba gratuita self-service

## Objetivo

Permitir que una persona cree un espacio propio de Membora durante 14 dias desde la web publica, sin compartir los datos de la demo y sin intervencion del administrador de plataforma.

## Flujo

1. La persona indica nombre, gimnasio, email y acepta privacidad.
2. El backend valida origen, honeypot y formato. El rate limit especifico se aplica solo cuando `TRIAL_RATE_LIMIT_ENABLED=true`.
3. Se envia un enlace de verificacion valido durante una hora.
4. Al abrir el enlace se muestra una confirmacion y solo el `POST` protegido consume la activacion.
5. Se crea o actualiza el contacto como `Cliente`, se vincula una empresa `TRIAL`, se crea su tenant y un usuario `GYM_ADMIN` activo con el mismo `tenant_id`.
6. Se genera una contrasena inicial aleatoria y se envia un segundo correo con un token de entrega de un solo uso.
7. La persona confirma la revelacion; la credencial cifrada se marca como consumida antes de mostrarse y no puede recuperarse al recargar.
8. El acceso de prueba caduca a los 14 dias conforme a `EmpresaRepository::accessStateForTenant`.

## Reglas de seguridad

- No crear tenants antes de verificar el email.
- No incluir contrasenas en correos, URLs, logs ni auditoria. La unica salida en claro es la vista `no-store` posterior al consumo voluntario del enlace.
- Cifrar la credencial temporal con AES-256-GCM y una clave derivada de `APP_KEY` o, por compatibilidad, `DB_PASSWORD`.
- El limite de tres solicitudes por IP y dos por email cada hora queda disponible mediante `TRIAL_RATE_LIMIT_ENABLED=true`; por defecto esta desactivado durante la depuracion final.
- Token aleatorio de 256 bits almacenado como hash SHA-256.
- Tokens de activacion y credenciales de un solo uso y una hora de validez.
- SMTP obligatorio para completar la solicitud.
- Mensajes publicos sin errores SQL ni detalles internos.

## Criterios de aceptacion

- Un payload valido genera un correo de activacion.
- Un payload incompleto o sin consentimiento se rechaza.
- Un origen externo se rechaza.
- Un email ya ocupado no sobrescribe el usuario existente; el alta usa un identificador de cuenta disponible y mantiene el correo original como destino comercial.
- La activacion crea un `Cliente`, empresa vinculada `TRIAL` de 14 dias, tenant y administrador.
- El segundo correo permite revelar la contrasena inicial una sola vez y un segundo acceso no devuelve la credencial.

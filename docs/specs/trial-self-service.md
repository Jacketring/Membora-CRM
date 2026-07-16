# Especificacion: prueba gratuita self-service

## Objetivo

Permitir que una persona cree un espacio propio de Membora durante 14 dias desde la web publica, sin compartir los datos de la demo y sin intervencion del administrador de plataforma.

## Flujo

1. La persona indica nombre, gimnasio, email y acepta privacidad.
2. El backend valida origen, honeypot, formato y rate limit.
3. Se envia un enlace de verificacion valido durante una hora.
4. Al abrirlo se crea o actualiza el contacto como `Cliente CRM`, se vincula una empresa `TRIAL`, se crea su tenant y un usuario `GYM_ADMIN`.
5. Se emite un token de un solo uso para que la persona defina su contrasena.
6. El acceso de prueba caduca a los 14 dias conforme a `EmpresaRepository::accessStateForTenant`.

## Reglas de seguridad

- No crear tenants antes de verificar el email.
- No enviar ni devolver contrasenas en claro.
- Maximo tres solicitudes por IP y dos por email cada hora.
- Token aleatorio de 256 bits almacenado como hash SHA-256.
- Token de activacion de un solo uso y una hora de validez.
- SMTP obligatorio para completar la solicitud.
- Mensajes publicos sin errores SQL ni detalles internos.

## Criterios de aceptacion

- Un payload valido genera un correo de activacion.
- Un payload incompleto o sin consentimiento se rechaza.
- Un origen externo se rechaza.
- Un email ya registrado no crea otro usuario.
- La activacion crea un `Cliente CRM`, empresa vinculada `TRIAL` de 14 dias, tenant y administrador.
- La persona debe definir su contrasena antes de iniciar sesion.

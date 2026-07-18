# Changelog

Todos los cambios notables se documentan aquí siguiendo [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/) y versionado semántico.

## [Unreleased]

### Changed
- Ocultados temporalmente los accesos y el bloque publico de la demo de 20 minutos; la prueba gratuita de 14 dias permanece disponible.
- La activacion de prueba reinicia una sesion anonima despues de cerrar la cuenta anterior, evitando que la caducidad de la cookie invalide el formulario CSRF antes de crear el contacto, la empresa y el correo de credenciales.
- El formulario comercial solicita contacto con Membora y el aviso de activacion explica la llegada del correo de contraseña y los pasos para iniciar sesion.
- La empresa tecnica de la demo deja de sincronizarse como contacto comercial, se retira cualquier contacto demo previo y el dominio antiguo del login redirige a `membora.es/app`.
- La verificacion de una prueba cierra cualquier sesion anterior antes de volver al login; las empresas nuevas ya no reciben una nota automatica y el texto antiguo se retira de las existentes sin afectar notas manuales.
- La eliminacion de administradores de plataforma borra su actividad y sus credenciales relacionadas, desvincula de forma segura los datos operativos conservables y elimina la cuenta en una unica transaccion.
- Sincronizada la documentacion canonica con las rutas, acciones, tablas, autenticacion, facturacion SaaS y Stripe Test implementados.
- Ampliada la trazabilidad con requisitos, historias, flujos arquitectonicos y casos de prueba de las funciones actuales.
- El alta de prueba verificada crea y vincula automaticamente `Cliente`, empresa `TRIAL`, tenant y administrador durante 14 dias; los emails ya registrados reciben un aviso de acceso en lugar de una respuesta silenciosa.
- Unificado el catalogo SaaS en Basic 49 EUR, Pro 89 EUR, Business 149 EUR y Enterprise 299 EUR mensuales sin IVA para base de datos, panel, API, landing, `schema.org` y fallback.
- La landing obtiene planes desde la API, muestra limites y prestaciones y solo usa el fallback cuando fallan el proxy y el endpoint directo.
- `Mejorar el plan` identifica el plan actual y permite unicamente ascensos con el proveedor configurado; Basic, Pro y Business reciben el aviso de mejora y Enterprise queda excluido.
- Stripe Checkout es el proveedor predeterminado en `stripe_test`, admite altas y ascensos desde cuentas sin suscripcion Stripe previa y bloquea suscripciones duplicadas.
- El retorno de Stripe usa el dominio real autorizado, vuelve al dashboard y reconcilia de forma idempotente la sesion pagada como respaldo del webhook.
- La sincronizacion de facturas admite tanto el formato Stripe anterior como `invoice.parent.subscription_details` de las versiones actuales de la API.
- El alta de prueba y el correo de credenciales funcionan como fases persistentes y reintentables; los enlaces usan el reloj de MariaDB para evitar desfases del servidor.
- La eliminacion de empresas, clientes, leads y socios limpia o desvincula sus dependencias sin recrear contactos eliminados.
- Retirados del repositorio los materiales antiguos de presentacion para evitar referencias a una version desactualizada.

### Added
- PHPUnit, PHPStan, CaptainHook, CI y pruebas E2E de desarrollo.
- Specs, ADRs y diagramas como código.
- Integración opcional con Sentry mediante entorno.
- Checkout interno de demostracion que solo acepta la tarjeta ficticia documentada y registra pago, justificante y acceso como simulados.
- Compatibilidad con Price IDs y Product IDs de Stripe Test en los planes.

### Security
- Cobertura automatizada de permisos, CSRF y validación del webhook.

## [1.0.0] - 2026-07-11

### Added
- MVP multi-tenant de Membora sobre PHP 8.2 y MariaDB.

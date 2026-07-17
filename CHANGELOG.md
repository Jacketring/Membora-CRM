# Changelog

Todos los cambios notables se documentan aquí siguiendo [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/) y versionado semántico.

## [Unreleased]

### Changed
- Sincronizada la documentacion canonica con las rutas, acciones, tablas, autenticacion, facturacion SaaS y Stripe Test implementados.
- Ampliada la trazabilidad con requisitos, historias, flujos arquitectonicos y casos de prueba de las funciones actuales.
- El alta de prueba verificada crea y vincula automaticamente `Cliente CRM`, empresa `TRIAL`, tenant y administrador durante 14 dias; los emails ya registrados reciben un aviso de acceso en lugar de una respuesta silenciosa.
- Unificado el catalogo SaaS en Basic 49 EUR, Pro 89 EUR, Business 149 EUR y Enterprise 299 EUR mensuales sin IVA para base de datos, panel, API, landing, `schema.org` y fallback.
- La landing obtiene planes desde la API, muestra limites y prestaciones y solo usa el fallback cuando fallan el proxy y el endpoint directo.
- `Mejorar el plan` identifica el plan actual y permite unicamente ascensos en el checkout simulado; Basic, Pro y Business reciben el aviso de mejora y Enterprise queda excluido.
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
- MVP multi-tenant de Membora CRM sobre PHP 8.2 y MariaDB.

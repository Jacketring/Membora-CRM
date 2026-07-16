# Changelog

Todos los cambios notables se documentan aquí siguiendo [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/) y versionado semántico.

## [Unreleased]

### Changed
- Sincronizada la documentacion canonica con las rutas, acciones, tablas, autenticacion, facturacion SaaS y Stripe Test implementados.
- Ampliada la trazabilidad con requisitos, historias, flujos arquitectonicos y casos de prueba de las funciones actuales.
- El alta de prueba verificada crea y vincula automaticamente `Cliente CRM`, empresa `TRIAL`, tenant y administrador durante 14 dias; los emails ya registrados reciben un aviso de acceso en lugar de una respuesta silenciosa.

### Added
- PHPUnit, PHPStan, CaptainHook, CI y pruebas E2E de desarrollo.
- Specs, ADRs y diagramas como código.
- Integración opcional con Sentry mediante entorno.

### Security
- Cobertura automatizada de permisos, CSRF y validación del webhook.

## [1.0.0] - 2026-07-11

### Added
- MVP multi-tenant de Membora CRM sobre PHP 8.2 y MariaDB.

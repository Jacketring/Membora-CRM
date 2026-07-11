# ADR-0004: Creación de tablas en runtime

Estado: aceptada con deuda técnica. Fecha: 2026-07-11.

## Contexto
El despliegue incremental en Plesk debía funcionar sin un ejecutor de migraciones.

## Decisión
Mantener temporalmente métodos `ensureTable()` idempotentes y migraciones SQL manuales para cambios complejos.

## Consecuencias
Facilita instalaciones existentes, pero añade latencia, permisos DDL y deriva de esquema. Se migrará a versiones aplicadas una sola vez.

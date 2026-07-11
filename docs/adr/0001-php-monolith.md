# ADR-0001: Monolito PHP y MariaDB

Estado: aceptada. Fecha: 2026-07-11.

## Contexto
Plesk no ofrece un proceso Node.js estable para producción.

## Decisión
Usar PHP 8.2 monolítico, Apache y MariaDB mediante PDO. Node queda limitado a E2E/CI.

## Consecuencias
El despliegue es compatible con Plesk y más simple; se renuncia a NestJS/Prisma y las fronteras de dominio deben mantenerse dentro del monolito.

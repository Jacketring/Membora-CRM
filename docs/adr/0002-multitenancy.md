# ADR-0002: Multi-tenancy por `tenant_id`

Estado: aceptada. Fecha: 2026-07-11.

## Contexto
Los gimnasios comparten infraestructura, pero sus datos deben permanecer aislados.

## Decisión
Usar una base de datos compartida y añadir `tenant_id` a las entidades de negocio y a todas sus consultas.

## Consecuencias
Reduce costes operativos; cada consulta necesita filtro de tenant, índices compuestos y pruebas de aislamiento.

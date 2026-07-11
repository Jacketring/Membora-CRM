# Spec: pagos

## Objetivo

Registrar y conciliar cobros de socios dentro de su gimnasio.

## Entradas y salidas

Entrada: socio, importe no negativo, concepto, método, vencimiento y estado. Salida: pago persistido, estado actualizado y recibo/factura cuando proceda.

## Reglas y aceptación

- Pago y socio deben pertenecer al mismo `tenant_id`.
- Solo usuarios autorizados pueden crear, editar o marcar un pago como cobrado.
- Los estados internos no se traducen ni modifican (`PENDING`, `PAID`, `OVERDUE`, `CANCELLED`).
- Repetir una notificación externa no duplica el cobro.
- Se contemplan importes cero, decimales, vencimientos pasados y proveedor no disponible.

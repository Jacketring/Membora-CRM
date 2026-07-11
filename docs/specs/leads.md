# Spec: leads

## Objetivo

Registrar oportunidades comerciales por gimnasio, seguir su etapa y convertirlas en socios sin mezclar datos entre tenants.

## Entradas y salidas

Entrada: nombre, apellidos, email/teléfono, origen, estado y notas. Salida: lead persistido, historial auditable y, al convertir, socio vinculado.

## Reglas y aceptación

- Toda consulta y escritura usa el `tenant_id` de la sesión; un tenant nunca accede a otro.
- El estado pertenece al flujo `OPEN → CONVERTED|LOST`.
- La conversión conserva datos de contacto y no crea dos socios al repetir la operación.
- Email, si existe, debe ser válido; debe existir email o teléfono.
- Se aceptan nombres compuestos, campos opcionales vacíos y duplicados razonables, que deben resolverse sin pérdida de historial.

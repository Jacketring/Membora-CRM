# Spec: webhook de captación

## Objetivo

Recibir formularios de la web pública, validarlos y crear o actualizar un lead de la plataforma de forma segura.

## Entradas y salidas

Entrada JSON: nombre/apellidos, email o teléfono, mensaje, origen y UTM. Salida JSON genérica de éxito/error y registro interno del intento.

## Reglas y aceptación

- Email opcional válido; teléfono opcional con 6–30 caracteres permitidos; al menos uno es obligatorio.
- Se valida token/origen, honeypot y límite por IP antes de persistir.
- Los textos se recortan a los límites de BD y el origen se normaliza a una lista permitida.
- Un duplicado actualiza el lead existente; no expone detalles internos en la respuesta.
- Se registran éxito, duplicado, bloqueo y error; las credenciales nunca aparecen en logs visibles.

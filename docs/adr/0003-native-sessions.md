# ADR-0003: Sesiones PHP nativas

Estado: aceptada. Fecha: 2026-07-11.

## Contexto
La aplicación y el navegador comparten origen y no necesitan tokens portables.

## Decisión
Autenticar mediante sesión PHP, cookie `HttpOnly`, `SameSite=Lax`, `Secure` en HTTPS y protección CSRF.

## Consecuencias
Simplifica revocación y evita JWT en el cliente; requiere almacenamiento de sesión disponible y defensa CSRF.

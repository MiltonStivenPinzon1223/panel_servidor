# API del panel

Base: `https://<nombre-magicdns-de-tailscale>/api/v1`. Respuestas JSON; fechas UTC; mostrar en el cliente con zona `America/Bogota`. Los errores usan `{ "message": "...", "errors": { ... } }`. No hay registro público.

## Rutas

| Método | Ruta | Acceso |
|---|---|---|
| GET | `/ping` | Público; `{ "ok": true }` |
| POST | `/auth/login` | Público, 5 intentos por minuto e IP/usuario. JSON: `username`, `password`, `device_name`; retorna `token`, vencimiento y `user`. |
| POST | `/auth/logout` | Token Bearer; revoca solo el token actual. |
| GET | `/auth/me` | Token Bearer |
| GET | `/server/status` | Métricas actuales, servicios, salud total, `collected_at` y `is_stale` |
| GET | `/apps?status=&q=` | Listado activo no paginado (máximo esperado 10) |
| POST | `/apps` | Alta de app, URLs, tecnologías/versiones |
| GET/PATCH/DELETE | `/apps/{id-o-slug}` | Detalle, edición o archivado lógico |
| GET/POST | `/technologies` | Catálogo / agregar tecnología |
| GET | `/alerts?state=firing\|resolved` | Alertas activas y últimos 50 eventos |
| GET | `/alert-rules` | Reglas de alertas |
| PATCH | `/alert-rules/{id}` | Actualiza umbral, duración, histéresis, severidad, recordatorio o activación |
| POST | `/devices` | Registra/actualiza `name`, `platform=android`, `fcm_token` |
| DELETE | `/devices/{id}` | Elimina dispositivo del usuario |
| POST | `/devices/{id}/test` | Envía una notificación de prueba |

Todas las rutas salvo ping y login requieren `Authorization: Bearer <token>`. Los tokens vencen en 30 días; `php artisan panel:tokens:revoke-all` los revoca todos.

## Seguridad de campos

- `local_url` y chequeos HTTP solo admiten los hosts exactos de `PANEL_ALLOWED_CHECK_HOSTS`; se rechazan URLs remotas y no se siguen redirecciones.
- `public_url` y `repository_url` son solo datos; nunca se consultan desde el colector.
- Los campos de app no se convierten en comandos ni nombres de unidades systemd.
- `DELETE /apps/{id}` actualiza `lifecycle=archived`; no borra archivos ni ejecuta órdenes del sistema.

### Estado HTTP

`up` cuando responde con el código esperado dentro de 1500 ms; `degraded` si es lento o si aún no llega al umbral de fallos; `down` solo al alcanzar `failure_threshold`; `unknown` antes del primer resultado. Los servicios usan `up` / `down` / `unknown`. Una app con ciclo de vida distinto de `active` se presenta como `disabled`.

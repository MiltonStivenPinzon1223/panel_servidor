# Despliegue en Debian 13

Todos los comandos de esta guía son para ejecutar manualmente cuando el operador esté listo. No compiles Vue/JavaScript, no instales Node ni generes el APK en el servidor. Usa la versión de PHP 8.4 de Debian y el MariaDB local.

## 1. Base de datos y cuenta exclusiva

En MariaDB crea la base y usuario del panel con permiso exclusivo sobre esa base. Sustituye la contraseña por una generada localmente:

```sql
CREATE DATABASE panel_status CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'panel_status'@'localhost' IDENTIFIED BY '<contraseña-larga-única>';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, REFERENCES
  ON panel_status.* TO 'panel_status'@'localhost';
FLUSH PRIVILEGES;
```

No uses la cuenta MariaDB de Nextcloud.

## 2. Backend

Sube el código backend (y el `composer.lock` generado en laptop); no subas `.env`, los secretos FCM ni archivos Firebase. En el servidor:

```sh
cd /var/www/panel/backend
composer install --no-dev --optimize-autoloader --no-interaction
cp .env.example .env
chmod 600 .env
php artisan key:generate
# Edita .env con DB_PASSWORD y un usuario/contraseña de administrador únicos (mínimo 12 caracteres).
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache
php artisan route:cache
php artisan panel:selfcheck
```

Configura `PANEL_SERVER_HOSTNAME`, `PANEL_SERVICES`, `PANEL_MOUNTS` y `PANEL_ALLOWED_CHECK_HOSTS`. La URL `NEXTCLOUD_STATUS_URL` debe apuntar al `status.php` local por el puerto 80. No permitas hosts públicos como destinos de health checks. Ajusta permisos para que `www-data` lea la app, escriba solo `storage/` y `bootstrap/cache/`, y lea el archivo de credenciales FCM fuera del webroot.

## 3. Nginx y FPM del panel

Instala el pool y vhost desde `deploy/php-fpm/panel.conf` y `deploy/nginx/panel.conf` en los directorios de configuración de Debian. Ajusta `/var/www/panel` a la ruta real. Valida con `php-fpm8.4 -tt` y `nginx -t`, recarga los servicios por el procedimiento habitual. El pool dedicado es `ondemand`, máximo 2 workers, timeout de espera 10 s y límite PHP 64 MiB. El vhost escucha solo en loopback `127.0.0.1:8081` y responde 404 para rutas distintas de API.

## 4. Tailscale Serve privado

Verifica la ayuda y configura Serve:

```sh
tailscale serve --help
tailscale serve --bg http://127.0.0.1:8081
tailscale serve status
```

El cliente usa el nombre DNS HTTPS entregado por Tailscale Serve; se puede cambiar en Ajustes. No uses `tailscale funnel`. Como recomendación, desactiva la expiración de la clave del servidor desde la consola Tailscale si el modelo de seguridad de tu tailnet lo permite.

## 5. Colector en cron

Instala `deploy/cron/panel` como `/etc/cron.d/panel` y ajusta la ruta. Comprueba que se ejecute como `www-data`. El lock del scheduler evita ticks superpuestos. `panel:tick` actualiza las filas actuales, no hace históricos ni recorre árboles.

## 6. Vhost y ngrok para apps de prueba

Instala `deploy/nginx/test-apps.conf` en Nginx, configura solo ubicaciones de proyectos de prueba (ejemplo `/alpha/` y `/beta/`) bajo el listener `127.0.0.1:8080`. Cada app Laravel debe configurarse con `APP_URL=https://<dominio-ngrok>/<prefijo>`, proxies de confianza limitados al proxy real y rutas/assets compatibles con subruta. Prueba estáticos, enlaces, login y callbacks antes de compartir. No incluyas Nextcloud, el panel, `/api` ni el puerto 80 en este vhost.

La unidad `deploy/systemd/ngrok-test-apps.service` solo apunta a `http://127.0.0.1:8080`. Instala ngrok en una ruta verificada (la unidad supone `/usr/bin/ngrok`) y crea un usuario de sistema sin shell/sudo llamado `ngrok`. Guarda `NGROK_AUTHTOKEN=<token>` en `/etc/panel/ngrok.env` con permisos `0600`; el cliente ngrok toma el token de esa variable. Adapta la opción de dominio a la URL gratuita asignada por tu cuenta. Habilita la unidad con systemd si decides exponer las apps. No uses ngrok para el panel.

## 7. Prueba obligatoria de aislamiento

Desde una red externa, comprueba que la IP local/puerto 80 no sea accesible. Con Tailscale desconectado, la URL privada del panel debe fallar. Con Tailscale conectado, `GET /api/v1/ping` por HTTPS debe responder `{"ok":true}`.

Por el dominio ngrok de pruebas, comprueba:

```sh
curl -i https://<dominio-ngrok>/api/v1/ping
curl -i https://<dominio-ngrok>/status.php
curl -i https://<dominio-ngrok>/nextcloud/
```

Los tres deben responder 404 o no responder. Revisa también que `http://127.0.0.1:8081/api/v1/ping` no esté accesible desde interfaces de red. No abras puertos del router.

## 8. Respaldo mínimo

Mientras no exista automatización de respaldos, crea una copia manual y protegida de la base; almacénala fuera del disco del servidor:

```sh
umask 077
mysqldump --single-transaction --default-character-set=utf8mb4 panel_status \
  > "$HOME/panel_status-$(date -u +%F).sql"
```

Conserva por separado copias seguras de `.env`, llave `APP_KEY`, credenciales FCM y respaldos de datos de Nextcloud; ninguno debe subirse al repositorio.

## 9. Comprobaciones y recursos

Ejecuta `php artisan panel:selfcheck`, `php artisan schedule:list` y revisa `storage/logs/laravel-*.log`. Mide memoria y CPU con la máquina real; los presupuestos de ≤120 MiB y ciclo ≤5 s no se pueden certificar desde este repositorio. La alarma por temperatura requiere verificar la etiqueta del sensor y compararla con documentación del procesador.

# Panel personal del servidor

Aplicación Android de solo lectura y API Laravel para consultar el estado de un servidor propio. El backend escucha en loopback detrás de Tailscale Serve; las apps de prueba pueden exponerse por un vhost/ngrok aislado. No incluye acciones para controlar servicios.

## Requisitos verificados

- PHP 8.4 para Debian objetivo (desarrollo comprobado con PHP 8.5.4) y Composer.
- Node 24, npm 11, JDK 21 y Android SDK para compilar en laptop.
- MariaDB con una base exclusiva `panel_status`.

## Desarrollo local

```sh
cd backend
composer install
cp .env.example .env
php artisan key:generate
# Configura DB_* y PANEL_ADMIN_USERNAME/PANEL_ADMIN_PASSWORD.
php artisan migrate
php artisan db:seed
php artisan serve
```

En otra terminal, crea `mobile/.env` a partir de `mobile/.env.example`, configura una URL HTTPS accesible desde el dispositivo y luego:

```sh
cd mobile
npm ci
# Copia google-services.json a android/app/google-services.json.
npm run build
npx cap sync android
```

No configures el emulador para usar una URL HTTP de texto claro: la aplicación requiere HTTPS.

## Pruebas

```sh
cd backend && php artisan test
cd ../mobile && npm test && npm run build
```

Las pruebas PHP usan el entorno de PHPUnit/SQLite. La app móvil debe ejecutarse en Android para validar almacenamiento de Keystore, PIN/huella y FCM.

## Compilación APK

Revisa [docs/ANDROID_BUILD.md](docs/ANDROID_BUILD.md). Debug:

```sh
cd mobile && npm ci && npm test && npm run build && npx cap sync android
cd android && ./gradlew assembleDebug
```

APK: `mobile/android/app/build/outputs/apk/debug/app-debug.apk`. Firma release con tu propia keystore fuera del repositorio y respáldala; sin ella no podrás actualizar esa instalación.

## Despliegue

El despliegue no compila front-end ni ejecuta Node en el servidor. Consulta [docs/DESPLIEGUE.md](docs/DESPLIEGUE.md) para crear DB, instalar Composer, migrar/sembrar, configurar Nginx/FPM, Tailscale Serve, cron, aislamiento ngrok y respaldo. Usa [docs/FIREBASE.md](docs/FIREBASE.md) para FCM y [docs/API.md](docs/API.md) para las rutas.

## Documentación

- [Decisiones](docs/DECISIONES.md)
- [Despliegue](docs/DESPLIEGUE.md)
- [Firebase](docs/FIREBASE.md)
- [APK Android](docs/ANDROID_BUILD.md)
- [API](docs/API.md)

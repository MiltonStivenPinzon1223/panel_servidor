# Decisiones de implementación

| Decisión | Alternativas consideradas | Motivo |
|---|---|---|
| Laravel 13.40.0 y Sanctum 4.3.3 | Laravel 12, autenticación propia | La versión instalada comprobada de Laravel 13.40.0 declara compatibilidad con PHP 8.3+; Sanctum 4.3.3 admite Illuminate 13 y cubre tokens revocables sin implementar criptografía propia. |
| Vue 3, Ionic Vue 9.0.10 y Capacitor 8.6.0 | Ionic React, Capacitor 7 | Versiones publicadas y compatibles verificadas en npm el 7 de noviembre de 2026; Ionic Vue encaja con el stack solicitado. |
| `@aparajita/capacitor-secure-storage` 8.0.1 | Preferences, localStorage, plugin sin mantenimiento | En Android cifra con AES-GCM y clave creada en Android Keystore. Preferences/localStorage no guardan secretos. |
| `@aparajita/capacitor-biometric-auth` 10.0.0 | Plugin biométrico antiguo o prompt PIN propio | El plugin vigente para Capacitor 8 expone `deviceIsSecure` y permite credenciales del dispositivo (PIN, patrón o contraseña); no se implementa ni almacena un PIN propio. |
| Plugin oficial `@capacitor/push-notifications` 8.1.3 | Firebase Admin SDK y SDK móvil adicional | Usa FCM en Android y ya está integrado con Capacitor; el servidor envía HTTP v1 con cliente Laravel y OpenSSL para evitar un SDK servidor pesado. |
| Notificaciones HTTP v1 con OAuth JWT propio | `google/auth` | La firma RSA y el intercambio OAuth requieren solo OpenSSL y el cliente HTTP ya presente; no añade memoria/dependencias de servidor. Credenciales permanecen en una ruta externa. |
| Una migración actualizadora y tablas sin historial de métricas | Consultas o tablas históricas | El HDD mecánico y el requisito de hasta 15 filas/ciclo imponen conservar el valor actual mediante `upsert`. |
| `systemctl is-active` mediante Symfony Process con argumentos separados y lista blanca | Shell/`exec` con cadenas, sudo | No interpreta campos editables ni usa shell y no necesita privilegios de administración. |
| API del panel solo detrás de Tailscale Serve y vhost local 8081 | Publicarla directamente o por ngrok | El panel y Nextcloud son privados; ngrok se restringe al listener de pruebas 8080. |
| Host local con allowlist exacta; sin seguir redirecciones HTTP | Resolver cualquier URL aportada por la app | El backend no debe poder consultar destinos arbitrarios ni ser redirigido a otra red. Se permiten solo hosts locales configurados. |
| Borrado lógico de apps mediante `lifecycle=archived` | Borrado físico/soft delete al usar DELETE | Mantiene el registro y deja claro que la acción no detiene ni elimina procesos. |
| Fechas en UTC y presentación móvil en `es-CO` / `America/Bogota` | Cambiar zona del servidor | Conserva comparaciones de intervalos y evita depender de la zona local del host. |
| Incluir `ngrok` como `ngrok` en la lista de unidades | Suponer `ngrok.service` | `systemctl is-active ngrok` resuelve unidades con sufijo `.service`; el nombre configurable permite adaptarlo si el equipo usa otra unidad. |
| La plantilla de acceso móvil usa un nombre MagicDNS de ejemplo | Fijar la IP `100.109.93.93` como URL HTTPS | Tailscale Serve entrega certificado para el nombre DNS TLS de la tailnet. La dirección se puede cambiar en Ajustes o `VITE_API_BASE_URL`; no se presupone que la IP tenga certificado válido. |

Las versiones de paquetes son versiones publicadas consultadas en npm/Packagist. El `package-lock.json` y `composer.lock` deben conservarse al instalar en el laptop. No se genera el APK en el servidor.

## Límites aún dependientes del entorno

- La unidad ngrok, los nombres de interfaz/temperatura del Sempron y el nombre DNS de la tailnet deben confirmarse en el equipo real; el colector no inventa temperatura cuando no hay sensor legible.
- La generación final y firma del APK requiere Android SDK/JDK, archivo Firebase de la aplicación y una keystore que solo debe vivir fuera del repositorio.
- El pool FPM independiente y la memoria real del panel deben medirse en el servidor. El diseño evita workers persistentes de la app, pero las cifras de memoria y tiempo no se pueden certificar desde el laptop.

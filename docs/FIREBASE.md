# Firebase Cloud Messaging

1. Crea un proyecto Firebase privado y añade una app Android cuyo identificador sea `com.personal.panelservidor` (si cambias `appId` en `mobile/capacitor.config.ts`, registra el mismo identificador).
2. Descarga `google-services.json` y guárdalo localmente como `mobile/android/app/google-services.json`. Está excluido de git. Ejecuta `npx cap sync android`.
3. En Firebase Console, crea una cuenta de servicio dedicada con el permiso mínimo de administración de Firebase Cloud Messaging. Descarga el JSON y cópialo al servidor, por ejemplo `/etc/panel/fcm-service-account.json`, con propietario/grupo apropiado y permisos `0600`; no lo copies al proyecto ni a la carpeta web.
4. Configura `FCM_CREDENTIALS_PATH=/etc/panel/fcm-service-account.json` en el `.env` del backend. La API HTTP v1 debe estar habilitada para el proyecto.
5. Inicia sesión en Android y acepta notificaciones. El cliente registra el token actual en `POST /api/v1/devices`; si FCM devuelve un token `UNREGISTERED`, se elimina de la tabla de dispositivos.

La app crea el canal Android `alerts` con importancia alta. La prueba se inicia en Ajustes; el envío desde el backend requiere credenciales FCM, conectividad HTTPS saliente y un dispositivo con permisos habilitados. Nunca publiques la clave privada de servicio o el archivo Android en el repositorio.

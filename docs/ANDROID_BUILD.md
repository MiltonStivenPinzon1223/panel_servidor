# Compilar Android

La compilación se realiza en el laptop de desarrollo, nunca en el servidor. Se requiere Node compatible con Vite 8, **JDK 21** y Android SDK configurados para Capacitor 8. El Gradle Wrapper del proyecto no puede ejecutarse con JDK 25. Configura el JDK 21 en Android Studio o en `JAVA_HOME`; no instales el SDK en el servidor. Mantén fuera de git el archivo `google-services.json` y todas las keystores.

## Preparar el proyecto Android

```sh
cd mobile
cp .env.example .env
# Cambiar VITE_API_BASE_URL por la URL HTTPS de Tailscale Serve.
npm ci
# Copiar google-services.json a android/app/google-services.json (no subirlo a git).
npx cap sync android
```

La carpeta `android/` ya está generada e incluida en el proyecto; no ejecutes `npx cap add android` salvo que la plataforma se haya eliminado y necesites regenerarla.

También se puede introducir o cambiar la URL HTTPS desde el formulario de inicio de sesión antes de autenticar.

En `android/app/src/main/AndroidManifest.xml`, verifica que `<application>` incluya `android:usesCleartextTraffic="false"`. No habilites tráfico HTTP en claro. La plantilla incluye `android/` generado localmente, que no debe mantener secretos.

## APK debug

```sh
cd mobile
npm test
npm run build
npx cap sync android
cd android
./gradlew assembleDebug
```

El APK se genera en `mobile/android/app/build/outputs/apk/debug/app-debug.apk`. Instálalo en un dispositivo Android con bloqueo de pantalla y dentro de la tailnet.

## APK firmado de distribución privada

Crea tu propia llave fuera del proyecto y respáldala en un lugar seguro. Si pierdes la llave no podrás firmar actualizaciones que Android acepte sobre la app ya instalada.

```sh
keytool -genkeypair -v -keystore "$HOME/panel-release.jks" -alias panel \
  -keyalg RSA -keysize 3072 -validity 10000
```

No escribas contraseñas en el repositorio ni en el historial del shell. Crea `~/.gradle/gradle.properties` fuera del proyecto, con permisos `0600`, con estas propiedades y los valores de tu keystore:

```properties
PANEL_RELEASE_STORE_FILE=/ruta/absoluta/panel-release.jks
PANEL_RELEASE_KEY_ALIAS=panel
PANEL_RELEASE_STORE_PASSWORD=<contraseña-de-keystore>
PANEL_RELEASE_KEY_PASSWORD=<contraseña-de-clave>
```

El `build.gradle` del módulo Android lee estas propiedades del Gradle de usuario y solo activa la firma cuando están definidas. Después:

```sh
cd mobile/android
./gradlew assembleRelease
```

Comprueba firma con `apksigner verify --verbose app/build/outputs/apk/release/app-release.apk`. Conserva una copia cifrada de la keystore y credenciales, fuera de git.

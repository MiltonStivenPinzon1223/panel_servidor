<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { Capacitor } from '@capacitor/core'
import { PushNotifications } from '@capacitor/push-notifications'
import { useRouter } from 'vue-router'
import { IonPage, IonHeader, IonToolbar, IonTitle, IonContent, IonItem, IonInput, IonButton, IonText, IonToggle, alertController } from '@ionic/vue'
import { getPreference, setPreference } from '../services/storage'
import { DEFAULT_API_BASE_URL } from '../services/api'
import { api } from '../services/api'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const router = useRouter()
const baseUrl = ref(DEFAULT_API_BASE_URL)
const message = ref('')
const pushError = ref('')
const notificationPermission = ref('Comprobando…')
const darkMode = ref(document.documentElement.classList.contains('ion-palette-dark'))
onMounted(async () => {
  baseUrl.value = await getPreference('api_base_url') || DEFAULT_API_BASE_URL
  const savedTheme = await getPreference('dark_mode')
  pushError.value = (await getPreference('push_error')) || ''
  if (savedTheme !== null) {
    darkMode.value = savedTheme === 'true'
    document.documentElement.classList.toggle('ion-palette-dark', darkMode.value)
  }
  if (Capacitor.isNativePlatform()) {
    try {
      notificationPermission.value = (await PushNotifications.checkPermissions()).receive === 'granted' ? 'Permitidas' : 'No permitidas'
    } catch {
      notificationPermission.value = 'No disponible; revisa los permisos de Android'
    }
  } else {
    notificationPermission.value = 'Disponible solo en Android'
  }
})
async function saveUrl(): Promise<void> {
  if (!baseUrl.value.startsWith('https://')) { message.value = 'La URL del servidor debe usar HTTPS.'; return }
  await setPreference('api_base_url', baseUrl.value.trim().replace(/\/+$/, ''))
  message.value = 'Dirección guardada.'
}
async function testNotification(): Promise<void> {
  try {
    const deviceId = await getPreference('push_device_id')
    if (!deviceId) throw new Error('Aún no se ha registrado un token de notificaciones. Comprueba el permiso y vuelve a entrar.')
    await api(`/devices/${deviceId}/test`, { method: 'POST' })
    message.value = 'Notificación de prueba enviada.'
    pushError.value = ''
  } catch (reason) { message.value = reason instanceof Error ? reason.message : 'No se pudo enviar la prueba.' }
}
async function toggleTheme(): Promise<void> {
  darkMode.value = !darkMode.value
  document.documentElement.classList.toggle('ion-palette-dark', darkMode.value)
  await setPreference('dark_mode', String(darkMode.value))
}
async function logout(): Promise<void> {
  try { await auth.logout() } finally { await router.replace('/login') }
}
</script>

<template>
  <ion-page>
    <ion-header><ion-toolbar><ion-title>Ajustes</ion-title></ion-toolbar></ion-header>
    <ion-content class="ion-padding">
      <ion-item><ion-input v-model="baseUrl" label="URL base HTTPS del servidor" label-placement="stacked" type="url" /></ion-item>
      <ion-button expand="block" @click="saveUrl">Guardar dirección</ion-button>
      <ion-item><ion-toggle :checked="darkMode" @ionChange="toggleTheme">Modo oscuro</ion-toggle></ion-item>
      <ion-button expand="block" fill="outline" @click="testNotification">Probar notificación</ion-button>
      <p>Permiso de notificaciones: {{ notificationPermission }}</p>
      <ion-text color="danger"><p v-if="pushError" role="alert">{{ pushError }}</p></ion-text>
      <ion-text><p role="status">{{ message }}</p></ion-text>
      <ion-button expand="block" color="medium" @click="logout">Cerrar sesión</ion-button>
    </ion-content>
  </ion-page>
</template>

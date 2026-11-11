<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue'
import { App as CapacitorApp } from '@capacitor/app'
import { PushNotifications } from '@capacitor/push-notifications'
import { IonApp, IonContent, IonButton, IonCard, IonCardContent, IonIcon, IonText } from '@ionic/vue'
import { lockClosedOutline } from 'ionicons/icons'
import { useRouter } from 'vue-router'
import { useAuthStore } from './stores/auth'
import { setPreference } from './services/storage'
import { shouldLockForBackground } from './security/lock'

const auth = useAuthStore()
const router = useRouter()
const ready = ref(false)
const backgroundedAt = ref<number | null>(null)
const lockError = ref('')
let pendingRoute: string | null = null
let appStateHandle: { remove: () => Promise<void> } | undefined
let pushActionHandle: { remove: () => Promise<void> } | undefined

async function unlock(): Promise<void> {
  lockError.value = ''
  try {
    await auth.unlock()
    if (pendingRoute) {
      await router.push(pendingRoute)
      pendingRoute = null
    }
  } catch (error) {
    lockError.value = error instanceof Error ? error.message : 'No se pudo desbloquear el dispositivo.'
  }
}

function handleUnauthorized(): void {
  auth.tokenPresent = false
  auth.unlocked = false
  auth.user = null
  router.replace('/login')
}

onMounted(async () => {
  window.addEventListener('panel:unauthorized', handleUnauthorized)
  pushActionHandle = await PushNotifications.addListener('pushNotificationActionPerformed', ({ notification }) => {
    const route = notification.data?.route
    if (typeof route === 'string' && route.startsWith('/tabs/')) {
      if (auth.unlocked) void router.push(route)
      else pendingRoute = route
    }
  })
  try {
    await auth.restore()
  } catch {
    auth.tokenPresent = false
    auth.unlocked = false
  }
  ready.value = true
  if (auth.tokenPresent) {
    backgroundedAt.value = Date.now()
    await unlock()
    if (auth.unlocked) {
      try {
        await import('./services/push').then(({ configurePush }) => configurePush())
      } catch (error) {
        await setPreference('push_error', error instanceof Error ? error.message : 'No se pudo registrar el dispositivo.')
      }
    }
  } else {
    await router.replace('/login')
  }
  appStateHandle = await CapacitorApp.addListener('appStateChange', ({ isActive }) => {
    if (!isActive) {
      backgroundedAt.value = Date.now()
    } else if (shouldLockForBackground(backgroundedAt.value, Date.now())) {
      auth.lock()
    }
  })
  await CapacitorApp.addListener('appUrlOpen', ({ url }) => {
    const parsed = new URL(url)
    const route = parsed.searchParams.get('route')
    if (route?.startsWith('/tabs/')) void router.push(route)
  })
})

onUnmounted(() => {
  window.removeEventListener('panel:unauthorized', handleUnauthorized)
  void appStateHandle?.remove()
  void pushActionHandle?.remove()
})
</script>

<template>
  <ion-app>
    <ion-content v-if="!ready" class="ion-padding"><p>Cargando el panel…</p></ion-content>
    <ion-content v-else-if="auth.tokenPresent && !auth.unlocked" class="ion-padding lock-screen">
      <ion-card>
        <ion-card-content class="ion-text-center">
          <ion-icon :icon="lockClosedOutline" size="large" aria-hidden="true" />
          <h1>Panel bloqueado</h1>
          <p>Desbloquea con la huella o las credenciales de pantalla de tu dispositivo.</p>
          <ion-text color="danger"><p v-if="lockError" role="alert">{{ lockError }}</p></ion-text>
          <ion-button expand="block" @click="unlock">Desbloquear</ion-button>
          <ion-button expand="block" fill="clear" @click="auth.logout">Cerrar sesión</ion-button>
        </ion-card-content>
      </ion-card>
    </ion-content>
    <ion-router-outlet v-else />
  </ion-app>
</template>

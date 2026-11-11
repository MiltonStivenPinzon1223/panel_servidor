<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { IonPage, IonHeader, IonToolbar, IonTitle, IonContent, IonItem, IonInput, IonButton, IonText, IonCard, IonCardContent } from '@ionic/vue'
import { DEFAULT_API_BASE_URL } from '../services/api'
import { getPreference, setPreference } from '../services/storage'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const router = useRouter()
const username = ref('')
const password = ref('')
const apiBaseUrl = ref(DEFAULT_API_BASE_URL)
const error = ref('')
const busy = ref(false)

onMounted(async () => {
  apiBaseUrl.value = await getPreference('api_base_url') || DEFAULT_API_BASE_URL
})

async function submit(): Promise<void> {
  busy.value = true
  error.value = ''
  try {
    const url = apiBaseUrl.value.trim().replace(/\/+$/, '')
    if (!url.startsWith('https://')) {
      throw new Error('La URL del servidor debe usar HTTPS.')
    }
    await setPreference('api_base_url', url)
    await auth.login(username.value.trim(), password.value)
    await router.replace('/tabs/home')
  } catch (reason) {
    error.value = reason instanceof Error ? reason.message : 'No se pudo iniciar sesión.'
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <ion-page>
    <ion-header><ion-toolbar><ion-title>Panel del servidor</ion-title></ion-toolbar></ion-header>
    <ion-content class="ion-padding">
      <ion-card>
        <ion-card-content>
          <h1>Iniciar sesión</h1>
          <p>Usa tu cuenta privada para consultar el servidor.</p>
          <ion-item>
            <ion-input v-model="apiBaseUrl" label="URL base HTTPS del servidor" label-placement="stacked" type="url" autocomplete="url" required />
          </ion-item>
          <form @submit.prevent="submit">
            <ion-item>
              <ion-input v-model="username" label="Usuario" label-placement="stacked" autocomplete="username" required />
            </ion-item>
            <ion-item>
              <ion-input v-model="password" label="Contraseña" label-placement="stacked" type="password" autocomplete="current-password" required />
            </ion-item>
            <ion-text color="danger"><p v-if="error" role="alert">{{ error }}</p></ion-text>
            <ion-button expand="block" type="submit" :disabled="busy || !username || !password">
              {{ busy ? 'Conectando…' : 'Entrar' }}
            </ion-button>
          </form>
        </ion-card-content>
      </ion-card>
    </ion-content>
  </ion-page>
</template>

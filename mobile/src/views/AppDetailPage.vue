<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { IonPage, IonHeader, IonToolbar, IonTitle, IonButtons, IonBackButton, IonContent, IonCard, IonCardHeader, IonCardTitle, IonCardContent, IonButton, IonChip, IonText, alertController } from '@ionic/vue'
import { api } from '../services/api'

const route = useRoute()
const router = useRouter()
type AppDetail = {
  id: number
  name: string
  status: string
  response_ms: number | null
  description: string | null
  environment: string
  repository_url: string | null
  local_url: string | null
  public_url: string | null
  exposure: string
  port: number | null
  lifecycle: string
  technologies: Array<{ id: number; name: string; version: string | null }>
}
const app = ref<AppDetail | null>(null)
const error = ref('')
async function load(): Promise<void> {
  try { app.value = (await api<{ data: AppDetail }>(`/apps/${route.params.id}`)).data }
  catch (reason) { error.value = reason instanceof Error ? reason.message : 'No se pudo cargar la aplicación.' }
}
async function archive(): Promise<void> {
  const dialog = await alertController.create({ header: 'Archivar aplicación', message: 'Solo cambiará el dato de ciclo de vida; no se apagará ni se detendrá nada.', buttons: [{ text: 'Cancelar', role: 'cancel' }, { text: 'Archivar', role: 'confirm' }] })
  await dialog.present()
  const result = await dialog.onDidDismiss()
  if (result.role === 'confirm') {
    await api(`/apps/${route.params.id}`, { method: 'DELETE' })
    await router.replace('/tabs/apps')
  }
}
onMounted(load)
</script>

<template>
  <ion-page>
    <ion-header><ion-toolbar><ion-buttons slot="start"><ion-back-button default-href="/tabs/apps" /></ion-buttons><ion-title>{{ app?.name ?? 'Aplicación' }}</ion-title></ion-toolbar></ion-header>
    <ion-content class="ion-padding">
      <ion-text color="danger"><p v-if="error" role="alert">{{ error }}</p></ion-text>
      <template v-if="app">
        <ion-card><ion-card-header><ion-card-title>Estado</ion-card-title></ion-card-header><ion-card-content><ion-chip>{{ app.status }}</ion-chip><p>Respuesta: {{ app.response_ms === null ? 'No disponible' : `${app.response_ms} ms` }}</p></ion-card-content></ion-card>
        <ion-card><ion-card-header><ion-card-title>Información</ion-card-title></ion-card-header><ion-card-content><p>{{ app.description || 'Sin descripción' }}</p><p>Entorno: {{ app.environment }}</p><p>Repositorio: {{ app.repository_url || 'No configurado' }}</p></ion-card-content></ion-card>
        <ion-card><ion-card-header><ion-card-title>Stack</ion-card-title></ion-card-header><ion-card-content><p v-if="!app.technologies.length">Sin tecnologías</p><p v-for="tech in app.technologies" :key="tech.id">{{ tech.name }} {{ tech.version || '' }}</p></ion-card-content></ion-card>
        <ion-card><ion-card-header><ion-card-title>Acceso</ion-card-title></ion-card-header><ion-card-content><p>URL local: {{ app.local_url || 'No configurada' }}</p><p>URL pública: {{ app.public_url || 'No configurada' }}</p><p>Exposición: {{ app.exposure }}</p><p>Puerto: {{ app.port || 'No configurado' }}</p></ion-card-content></ion-card>
        <ion-card><ion-card-header><ion-card-title>Ciclo de vida</ion-card-title></ion-card-header><ion-card-content><p>{{ app.lifecycle }} (dato informativo; no apaga ni enciende la app)</p></ion-card-content></ion-card>
        <ion-button expand="block" @click="router.push(`/tabs/apps/${app.id}/edit`)">Editar</ion-button>
        <ion-button expand="block" fill="outline" color="medium" @click="archive">Archivar</ion-button>
      </template>
    </ion-content>
  </ion-page>
</template>

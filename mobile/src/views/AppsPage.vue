<script setup lang="ts">
import { ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { IonPage, IonHeader, IonToolbar, IonTitle, IonContent, IonSearchbar, IonChip, IonCard, IonCardContent, IonButton, IonText } from '@ionic/vue'
import { api } from '../services/api'

type AppSummary = { id: number; name: string; status: string; response_ms: number | null; description: string | null }
const router = useRouter()
const apps = ref<AppSummary[]>([])
const query = ref('')
const filter = ref('')
const error = ref('')

async function load(): Promise<void> {
  try {
    const params = new URLSearchParams()
    if (query.value) params.set('q', query.value)
    if (filter.value) params.set('status', filter.value)
    apps.value = (await api<{ data: AppSummary[] }>(`/apps?${params}`)).data
    error.value = ''
  } catch (reason) {
    error.value = reason instanceof Error ? reason.message : 'No se pudieron cargar las aplicaciones.'
  }
}
watch([query, filter], () => { void load() }, { immediate: true })
function statusLabel(status: string): string {
  return ({ up: 'En línea', degraded: 'Degradada', down: 'Caída', unknown: 'Desconocida', disabled: 'Desactivada' } as Record<string, string>)[status] ?? 'Desconocida'
}
</script>

<template>
  <ion-page>
    <ion-header><ion-toolbar><ion-title>Aplicaciones</ion-title></ion-toolbar></ion-header>
    <ion-content class="ion-padding">
      <ion-searchbar v-model="query" placeholder="Buscar por nombre" :debounce="300" />
      <div class="filter-row">
        <ion-chip v-for="entry in [['', 'Todas'], ['up', 'En línea'], ['degraded', 'Degradada'], ['down', 'Caída'], ['unknown', 'Desconocida'], ['disabled', 'Desactivada']]" :key="entry[0]" :outline="filter !== entry[0]" @click="filter = entry[0]">
          {{ entry[1] }}
        </ion-chip>
      </div>
      <ion-text color="danger"><p v-if="error" role="alert">{{ error }}</p></ion-text>
      <ion-card v-for="app in apps" :key="app.id" button @click="router.push(`/tabs/apps/${app.id}`)">
        <ion-card-content>
          <div class="app-card-head"><strong>{{ app.name }}</strong><ion-chip :color="app.status === 'up' ? 'success' : app.status === 'down' ? 'danger' : 'medium'">{{ statusLabel(app.status) }}</ion-chip></div>
          <p>{{ app.description || 'Sin descripción' }}</p>
          <p>Respuesta: {{ app.response_ms === null ? 'No disponible' : `${app.response_ms} ms` }}</p>
        </ion-card-content>
      </ion-card>
      <p v-if="apps.length === 0 && !error">No hay aplicaciones que coincidan.</p>
      <ion-button expand="block" @click="router.push('/tabs/apps/new')">Añadir aplicación</ion-button>
    </ion-content>
  </ion-page>
</template>

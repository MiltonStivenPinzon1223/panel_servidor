<script setup lang="ts">
import { ref } from 'vue'
import { onIonViewDidEnter } from '@ionic/vue'
import { useRouter } from 'vue-router'
import { IonPage, IonHeader, IonToolbar, IonTitle, IonContent, IonCard, IonCardContent, IonChip, IonText } from '@ionic/vue'
import { api } from '../services/api'

type AlertEvent = { id: number; state: string; severity: string; message: string; started_at: string; resolved_at: string | null; dimension: string }
const alerts = ref<AlertEvent[]>([])
const activeAlerts = ref<AlertEvent[]>([])
const error = ref('')
const router = useRouter()
async function load(): Promise<void> {
  try {
    const result = await api<{ active: AlertEvent[]; events: AlertEvent[] }>('/alerts')
    activeAlerts.value = result.active
    alerts.value = result.events
    error.value = ''
  } catch (reason) { error.value = reason instanceof Error ? reason.message : 'No se pudieron cargar las alertas.' }
}
onIonViewDidEnter(() => { void load() })
</script>

<template>
  <ion-page>
    <ion-header><ion-toolbar><ion-title>Alertas</ion-title></ion-toolbar></ion-header>
    <ion-content class="ion-padding">
      <ion-text color="danger"><p v-if="error" role="alert">{{ error }}</p></ion-text>
      <h2>Activas</h2>
      <p v-if="activeAlerts.length === 0">No hay alertas activas.</p>
      <ion-card v-for="alert in activeAlerts" :key="`active-${alert.id}`" button @click="router.push('/tabs/alerts')">
        <ion-card-content><ion-chip :color="alert.severity === 'critical' ? 'danger' : 'warning'">{{ alert.severity === 'critical' ? 'Crítica' : 'Advertencia' }}</ion-chip><strong>{{ alert.message }}</strong><p>{{ new Date(alert.started_at).toLocaleString('es-CO') }} · {{ alert.dimension }}</p></ion-card-content>
      </ion-card>
      <h2>Últimos eventos</h2>
      <ion-card v-for="alert in alerts" :key="`event-${alert.id}`">
        <ion-card-content><ion-chip>{{ alert.state === 'firing' ? 'Activa' : 'Resuelta' }}</ion-chip><strong>{{ alert.message }}</strong><p>{{ new Date(alert.started_at).toLocaleString('es-CO') }}<span v-if="alert.resolved_at"> · Resuelta {{ new Date(alert.resolved_at).toLocaleString('es-CO') }}</span></p></ion-card-content>
      </ion-card>
    </ion-content>
  </ion-page>
</template>

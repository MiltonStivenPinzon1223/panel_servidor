<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue'
import { onIonViewDidEnter, onIonViewDidLeave } from '@ionic/vue'
import { IonPage, IonHeader, IonToolbar, IonTitle, IonContent, IonCard, IonCardHeader, IonCardTitle, IonCardContent, IonChip, IonRefresher, IonRefresherContent, IonIcon, IonText } from '@ionic/vue'
import { checkmarkCircleOutline, warningOutline, alertCircleOutline, helpCircleOutline, thermometerOutline } from 'ionicons/icons'
import { api } from '../services/api'

type Status = {
  overall_health: string
  collected_at: string | null
  is_stale: boolean
  lock_timeout_seconds: number
  metrics: Record<string, Array<{ dimension: string; value: number; unit: string }>>
  services: Array<{ name: string; unit_name: string; status: string | null; message: string | null }>
}

const status = ref<Status | null>(null)
const error = ref('')
const updated = ref('')
let timer: ReturnType<typeof setInterval> | undefined
let visible = false

async function refresh(): Promise<void> {
  try {
    status.value = await api<Status>('/server/status')
    error.value = ''
    if (status.value.collected_at) {
      const age = Math.max(0, Math.floor((Date.now() - new Date(status.value.collected_at).getTime()) / 1000))
      const amount = age < 60 ? `${age} s` : age < 3600 ? `${Math.floor(age / 60)} min` : `${Math.floor(age / 3600)} h`
      updated.value = `Actualizado hace ${amount}`
    } else {
      updated.value = 'Sin datos'
    }
  } catch (reason) {
    error.value = reason instanceof Error ? reason.message : 'No se pudo consultar el servidor.'
  }
}

function metric(key: string): Array<{ dimension: string; value: number; unit: string }> {
  return status.value?.metrics[key] ?? []
}

function formatMetric(value: number, unit: string): string {
  if (unit === 's') {
    const hours = Math.floor(value / 3600)
    const days = Math.floor(hours / 24)
    return days ? `${days} d ${hours % 24} h` : `${hours} h ${Math.floor((value % 3600) / 60)} min`
  }
  return `${value.toFixed(unit === '' ? 2 : 1)}${unit ? ` ${unit}` : ''}`
}

function healthLabel(value: string): string {
  return ({ ok: 'Correcto', warning: 'Advertencia', critical: 'Crítico', unknown: 'Desconocido' } as Record<string, string>)[value] ?? 'Desconocido'
}

function healthIcon(value: string) {
  return value === 'ok' ? checkmarkCircleOutline : value === 'warning' ? warningOutline : value === 'critical' ? alertCircleOutline : helpCircleOutline
}

function startRefresh(): void {
  if (timer) clearInterval(timer)
  visible = true
  void refresh()
  timer = setInterval(() => { if (visible && document.visibilityState === 'visible') void refresh() }, 30_000)
}

function stopRefresh(): void {
  visible = false
  if (timer) clearInterval(timer)
  timer = undefined
}

async function pulled(event: CustomEvent): Promise<void> {
  await refresh()
  ;(event.target as HTMLIonRefresherElement).complete()
}

onIonViewDidEnter(startRefresh)
onIonViewDidLeave(stopRefresh)
onMounted(() => { if (!timer) startRefresh() })
onUnmounted(stopRefresh)
</script>

<template>
  <ion-page>
    <ion-header><ion-toolbar><ion-title>Estado del servidor</ion-title></ion-toolbar></ion-header>
    <ion-content class="ion-padding">
      <ion-refresher slot="fixed" @ionRefresh="pulled"><ion-refresher-content pulling-text="Desliza para actualizar" /></ion-refresher>
      <ion-text color="danger"><p v-if="error" role="alert">{{ error }}</p></ion-text>
      <p>{{ updated || 'Cargando…' }}</p>
      <ion-card :class="`health-${status?.overall_health ?? 'unknown'}`">
        <ion-card-content>
          <ion-icon :icon="healthIcon(status?.overall_health ?? 'unknown')" aria-hidden="true" />
          <strong> Estado: {{ healthLabel(status?.overall_health ?? 'unknown') }}</strong>
          <p v-if="status?.is_stale" class="stale" role="status">Los datos están desactualizados. Revisa el colector del servidor.</p>
        </ion-card-content>
      </ion-card>
      <section class="metric-grid" aria-label="Métricas">
        <ion-card v-for="item in [
          ['Carga CPU (1 min)', 'cpu_load_1'], ['Carga CPU (5 min)', 'cpu_load_5'], ['Carga CPU (15 min)', 'cpu_load_15'],
          ['RAM usada', 'ram_used_pct'], ['Swap usada', 'swap_used_pct'], ['Disco', 'disk_used_pct'], ['Tiempo activo', 'uptime_s'],
        ]" :key="item[1]">
          <ion-card-header><ion-card-title>{{ item[0] }}</ion-card-title></ion-card-header>
          <ion-card-content>
            <p v-if="metric(item[1]).length === 0">No disponible</p>
            <p v-for="value in metric(item[1])" :key="value.dimension">
              {{ value.dimension ? `${value.dimension}: ` : '' }}{{ formatMetric(value.value, value.unit) }}
            </p>
          </ion-card-content>
        </ion-card>
        <ion-card>
          <ion-card-header><ion-card-title><ion-icon :icon="thermometerOutline" /> Temperatura CPU</ion-card-title></ion-card-header>
          <ion-card-content>{{ metric('temp_cpu_c').length ? formatMetric(metric('temp_cpu_c')[0].value, '°C') : 'No disponible' }}</ion-card-content>
        </ion-card>
      </section>
      <h2>Servicios</h2>
      <ion-card v-for="service in status?.services ?? []" :key="service.unit_name">
        <ion-card-content>
          <strong>{{ service.name }}</strong>
          <ion-chip :color="service.status === 'up' ? 'success' : service.status === 'unknown' || !service.status ? 'medium' : 'danger'">
            {{ service.status === 'up' ? 'Activo' : service.status === 'unknown' || !service.status ? 'Desconocido' : 'Inactivo' }}
          </ion-chip>
          <p>{{ service.unit_name }} · {{ service.message ?? 'Sin comprobación' }}</p>
        </ion-card-content>
      </ion-card>
    </ion-content>
  </ion-page>
</template>

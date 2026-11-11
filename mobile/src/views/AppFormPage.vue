<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { IonPage, IonHeader, IonToolbar, IonTitle, IonButtons, IonBackButton, IonContent, IonItem, IonInput, IonTextarea, IonSelect, IonSelectOption, IonButton, IonCheckbox, IonText } from '@ionic/vue'
import { api } from '../services/api'

type Technology = { id: number; name: string; category: string }
type SelectedTechnology = { id: number; version: string }
type EditableApp = {
  name: string; slug: string; kind: string; description: string | null; repository_url: string | null
  local_url: string | null; public_url: string | null; exposure: string; environment: string
  lifecycle: string; port: number | null; notes: string | null
  technologies: Array<{ id: number; version: string | null }>
}
const route = useRoute()
const router = useRouter()
const editing = computed(() => Boolean(route.params.id))
const technologies = ref<Technology[]>([])
const selected = ref<SelectedTechnology[]>([])
const error = ref('')
const form = ref({
  name: '', slug: '', kind: 'laravel', description: '', repository_url: '',
  local_url: '', public_url: '', exposure: 'private', environment: 'test',
  lifecycle: 'active', port: '', notes: '',
})
const appUrl = computed(() => route.params.id ? `/apps/${route.params.id}` : '/apps')

onMounted(async () => {
  try {
    technologies.value = (await api<{ data: Technology[] }>('/technologies')).data
    if (editing.value) {
      const current = (await api<{ data: EditableApp }>(appUrl.value)).data
      Object.assign(form.value, current, { port: current.port ? String(current.port) : '' })
      selected.value = current.technologies.map((technology: any) => ({ id: technology.id, version: technology.version || '' }))
    }
  } catch (reason) { error.value = reason instanceof Error ? reason.message : 'No se pudieron cargar los datos.' }
})

function toggleTechnology(id: number, checked: boolean): void {
  selected.value = checked
    ? [...selected.value, { id, version: '' }]
    : selected.value.filter((technology) => technology.id !== id)
}

function updateVersion(id: number, version: string): void {
  const item = selected.value.find((technology) => technology.id === id)
  if (item) item.version = version
}

async function submit(): Promise<void> {
  error.value = ''
  if (!form.value.name.trim() || !form.value.slug.trim()) { error.value = 'El nombre y el identificador son obligatorios.'; return }
  try {
    const body = { ...form.value, port: form.value.port ? Number(form.value.port) : null, technologies: selected.value }
    const result = await api<{ data: { id: number } }>(appUrl.value, { method: editing.value ? 'PATCH' : 'POST', body: JSON.stringify(body) })
    await router.replace(`/tabs/apps/${result.data.id}`)
  } catch (reason) { error.value = reason instanceof Error ? reason.message : 'No se pudo guardar la aplicación.' }
}
</script>

<template>
  <ion-page>
    <ion-header><ion-toolbar><ion-buttons slot="start"><ion-back-button default-href="/tabs/apps" /></ion-buttons><ion-title>{{ editing ? 'Editar aplicación' : 'Nueva aplicación' }}</ion-title></ion-toolbar></ion-header>
    <ion-content class="ion-padding">
      <form @submit.prevent="submit">
        <ion-item><ion-input v-model="form.name" label="Nombre" label-placement="stacked" :maxlength="160" required /></ion-item>
        <ion-item><ion-input v-model="form.slug" label="Identificador" label-placement="stacked" :maxlength="160" required /></ion-item>
        <ion-item><ion-select v-model="form.kind" label="Tipo"><ion-select-option value="nextcloud">Nextcloud</ion-select-option><ion-select-option value="laravel">Laravel</ion-select-option><ion-select-option value="other">Otra</ion-select-option></ion-select></ion-item>
        <ion-item><ion-textarea v-model="form.description" label="Descripción" label-placement="stacked" /></ion-item>
        <ion-item><ion-input v-model="form.repository_url" label="Repositorio URL" label-placement="stacked" type="url" /></ion-item>
        <ion-item><ion-input v-model="form.local_url" label="URL local (solo localhost)" label-placement="stacked" type="url" /></ion-item>
        <ion-item><ion-input v-model="form.public_url" label="URL pública" label-placement="stacked" type="url" /></ion-item>
        <ion-item><ion-select v-model="form.exposure" label="Exposición"><ion-select-option value="private">Privada</ion-select-option><ion-select-option value="ngrok">ngrok</ion-select-option></ion-select></ion-item>
        <ion-item><ion-select v-model="form.environment" label="Entorno"><ion-select-option value="personal">Personal</ion-select-option><ion-select-option value="test">Pruebas</ion-select-option></ion-select></ion-item>
        <ion-item><ion-select v-model="form.lifecycle" label="Ciclo de vida"><ion-select-option value="active">Activa</ion-select-option><ion-select-option value="disabled">Desactivada</ion-select-option><ion-select-option value="archived">Archivada</ion-select-option></ion-select></ion-item>
        <p class="ion-padding-start">El ciclo de vida es solo un dato informativo; no apaga ni enciende la app.</p>
        <ion-item><ion-input v-model="form.port" label="Puerto" label-placement="stacked" type="number" min="1" max="65535" /></ion-item>
        <ion-item><ion-textarea v-model="form.notes" label="Notas" label-placement="stacked" /></ion-item>
        <h2>Tecnologías</h2>
        <ion-item v-for="technology in technologies" :key="technology.id">
          <ion-checkbox :checked="selected.some((entry) => entry.id === technology.id)" @ionChange="toggleTechnology(technology.id, $event.detail.checked)">{{ technology.name }}</ion-checkbox>
          <ion-input v-if="selected.some((entry) => entry.id === technology.id)" :value="selected.find((entry) => entry.id === technology.id)?.version" label="Versión" @ionInput="updateVersion(technology.id, $event.detail.value || '')" />
        </ion-item>
        <ion-text color="danger"><p v-if="error" role="alert">{{ error }}</p></ion-text>
        <ion-button expand="block" type="submit">Guardar</ion-button>
      </form>
    </ion-content>
  </ion-page>
</template>

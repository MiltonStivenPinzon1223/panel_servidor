import { defineStore } from 'pinia'
import { api } from '../services/api'
import { readToken, removeToken, setPreference, writeToken } from '../services/storage'

type User = { id: number; name: string; username: string }

export const useAuthStore = defineStore('auth', {
  state: () => ({ tokenPresent: false, unlocked: false, user: null as User | null }),
  actions: {
    async restore(): Promise<void> {
      this.tokenPresent = Boolean(await readToken())
      this.unlocked = !this.tokenPresent
      if (this.tokenPresent) {
        this.user = await api<User>('/auth/me')
      }
    },
    async login(username: string, password: string): Promise<void> {
      const result = await api<{ token: string; user: User }>('/auth/login', {
        method: 'POST',
        body: JSON.stringify({ username, password, device_name: 'Android personal' }),
      })
      await writeToken(result.token)
      this.tokenPresent = true
      this.unlocked = true
      this.user = result.user
      try {
        await import('../services/push').then(({ configurePush }) => configurePush())
      } catch (error) {
        await setPreference('push_error', error instanceof Error ? error.message : 'No se pudo registrar el dispositivo.')
      }
    },
    async unlock(): Promise<void> {
      const { BiometricAuth } = await import('@aparajita/capacitor-biometric-auth')
      const status = await BiometricAuth.checkBiometry()
      if (!status.deviceIsSecure) {
        throw new Error('Configura primero un bloqueo de pantalla (PIN, patrón o contraseña) en Android.')
      }
      await BiometricAuth.authenticate({
        reason: 'Desbloquear el panel del servidor',
        allowDeviceCredential: true,
        androidTitle: 'Desbloquear panel',
        androidSubtitle: 'Usa tu huella o el PIN, patrón o contraseña del dispositivo',
      })
      this.unlocked = true
    },
    async logout(): Promise<void> {
      try {
        await api('/auth/logout', { method: 'POST' })
      } finally {
        await removeToken()
        this.tokenPresent = false
        this.unlocked = false
        this.user = null
      }
    },
    lock(): void {
      if (this.tokenPresent) this.unlocked = false
    },
  },
})

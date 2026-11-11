import { Capacitor } from '@capacitor/core'
import { PushNotifications, type Token } from '@capacitor/push-notifications'
import { api } from './api'
import { setPreference } from './storage'

let listenersRegistered = false

export async function configurePush(): Promise<void> {
  if (!Capacitor.isNativePlatform()) return

  const permission = await PushNotifications.checkPermissions()
  const result = permission.receive === 'granted'
    ? permission
    : await PushNotifications.requestPermissions()
  if (result.receive !== 'granted') return

  await PushNotifications.createChannel({
    id: 'alerts',
    name: 'Alertas del servidor',
    description: 'Alertas de estado y notificaciones del panel',
    importance: 5,
    sound: 'default',
    vibration: true,
  })
  if (!listenersRegistered) {
    await PushNotifications.addListener('registration', async (token: Token) => {
      try {
        const result = await api<{ data: { id: number } }>('/devices', {
          method: 'POST',
          body: JSON.stringify({ name: 'Android', platform: 'android', fcm_token: token.value }),
        })
        await setPreference('push_device_id', String(result.data.id))
        await setPreference('push_error', '')
      } catch (error) {
        await setPreference('push_error', error instanceof Error ? error.message : 'No se pudo registrar el dispositivo.')
      }
    })
    listenersRegistered = true
  }
  await PushNotifications.register()
}

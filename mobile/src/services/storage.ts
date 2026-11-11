import { Capacitor } from '@capacitor/core'
import { SecureStorage } from '@aparajita/capacitor-secure-storage'
import { Preferences } from '@capacitor/preferences'

const TOKEN_KEY = 'api_token'

function requireNativeSecureStorage(): void {
  if (!Capacitor.isNativePlatform()) {
    throw new Error('El token solo se puede guardar en el almacenamiento seguro de Android.')
  }
}

export async function readToken(): Promise<string | null> {
  requireNativeSecureStorage()
  const token = await SecureStorage.get(TOKEN_KEY)
  return typeof token === 'string' ? token : null
}

export async function writeToken(token: string): Promise<void> {
  requireNativeSecureStorage()
  await SecureStorage.set(TOKEN_KEY, token)
}

export async function removeToken(): Promise<void> {
  requireNativeSecureStorage()
  await SecureStorage.remove(TOKEN_KEY)
}

export async function getPreference(key: string): Promise<string | null> {
  const result = await Preferences.get({ key })
  return result.value
}

export async function setPreference(key: string, value: string): Promise<void> {
  await Preferences.set({ key, value })
}

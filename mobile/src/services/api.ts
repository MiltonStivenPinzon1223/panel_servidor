import { getPreference, readToken, removeToken } from './storage'

export const DEFAULT_API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'https://servidor.tailnet-usuario.ts.net'

export type ApiOptions = Omit<RequestInit, 'headers'> & { headers?: Record<string, string> }

export async function api<T>(path: string, options: ApiOptions = {}): Promise<T> {
  const baseUrl = (await getPreference('api_base_url'))?.trim() || DEFAULT_API_BASE_URL
  const token = await readToken()
  let response: Response
  try {
    response = await fetch(`${baseUrl.replace(/\/+$/, '')}/api/v1${path}`, {
      ...options,
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
        ...options.headers,
      },
    })
  } catch {
    throw new Error('No se pudo conectar con el servidor. Comprueba que Tailscale esté conectado y la URL use HTTPS.')
  }
  if (response.status === 401) {
    await removeToken()
    window.dispatchEvent(new Event('panel:unauthorized'))
  }
  const payload = await response.json().catch(() => ({}))
  if (!response.ok) {
    const errors = payload.errors ? Object.values(payload.errors).flat().join(' ') : ''
    throw new Error(errors || payload.message || `Error del servidor (${response.status}).`)
  }
  return payload as T
}

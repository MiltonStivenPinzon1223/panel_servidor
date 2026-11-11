import { beforeEach, describe, expect, it, vi } from 'vitest'

const { secureGet, secureSet, secureRemove, nativePlatform } = vi.hoisted(() => ({
  secureGet: vi.fn(),
  secureSet: vi.fn(),
  secureRemove: vi.fn(),
  nativePlatform: vi.fn(),
}))

vi.mock('@capacitor/core', () => ({
  Capacitor: { isNativePlatform: nativePlatform },
}))

vi.mock('@aparajita/capacitor-secure-storage', () => ({
  SecureStorage: { get: secureGet, set: secureSet, remove: secureRemove },
}))

vi.mock('@capacitor/preferences', () => ({
  Preferences: { get: vi.fn(async () => ({ value: 'setting' })), set: vi.fn() },
}))

import { readToken, removeToken, writeToken } from './storage'

describe('almacenamiento de credenciales', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    nativePlatform.mockReturnValue(true)
    secureGet.mockResolvedValue('secure-token')
    secureSet.mockResolvedValue(undefined)
    secureRemove.mockResolvedValue(undefined)
  })

  it('lee, escribe y elimina el token mediante el plugin de almacenamiento seguro', async () => {
    expect(await readToken()).toBe('secure-token')
    await writeToken('new-token')
    await removeToken()
    expect(secureGet).toHaveBeenCalledWith('api_token')
    expect(secureSet).toHaveBeenCalledWith('api_token', 'new-token')
    expect(secureRemove).toHaveBeenCalledWith('api_token')
  })

  it('rechaza el almacenamiento en web para no guardar el token en localStorage', async () => {
    nativePlatform.mockReturnValue(false)
    await expect(readToken()).rejects.toThrow('almacenamiento seguro de Android')
    await expect(writeToken('secret')).rejects.toThrow('almacenamiento seguro de Android')
    expect(secureGet).not.toHaveBeenCalled()
    expect(secureSet).not.toHaveBeenCalled()
  })
})

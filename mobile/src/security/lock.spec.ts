import { describe, expect, it } from 'vitest'
import { shouldLockForBackground } from './lock'

describe('bloqueo de la aplicación', () => {
  it('no bloquea si aún está en primer plano o han pasado exactamente 60 segundos', () => {
    expect(shouldLockForBackground(null, 100_000)).toBe(false)
    expect(shouldLockForBackground(100_000, 160_000)).toBe(false)
  })

  it('bloquea después de más de 60 segundos en segundo plano', () => {
    expect(shouldLockForBackground(100_000, 160_001)).toBe(true)
  })
})

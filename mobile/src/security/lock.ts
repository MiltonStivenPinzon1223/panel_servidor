export const LOCK_AFTER_SECONDS = 60

export function shouldLockForBackground(backgroundedAt: number | null, now: number): boolean {
  return backgroundedAt !== null && now - backgroundedAt > LOCK_AFTER_SECONDS * 1000
}

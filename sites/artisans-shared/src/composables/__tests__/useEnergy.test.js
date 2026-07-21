import { describe, it, expect } from 'vitest'
import { projectEnergy } from '../useEnergy.js'

describe('projectEnergy', () => {
  it('renvoie null sans données', () => {
    expect(projectEnergy(null)).toBeNull()
  })

  it('renvoie le courant si plein', () => {
    expect(projectEnergy({ current: 100, max: 100, next_energy_at: null })).toBe(100)
  })

  it('pas de tick avant 10 min', () => {
    const next = new Date(Date.now() + 8 * 60_000).toISOString()
    expect(projectEnergy({ current: 50, max: 100, next_energy_at: next })).toBe(50)
  })

  it('+5 par tranche de 10 min écoulée', () => {
    const at = Date.now()
    const next = new Date(at - 25 * 60_000).toISOString() // next dépassé de 25 min → 3 ticks
    expect(projectEnergy({ current: 50, max: 100, next_energy_at: next }, at)).toBe(65)
  })

  it('plafonne au max', () => {
    const at = Date.now()
    const next = new Date(at - 120 * 60_000).toISOString()
    expect(projectEnergy({ current: 95, max: 100, next_energy_at: next }, at)).toBe(100)
  })

  it('accepte un `at` explicite', () => {
    const base = new Date('2026-07-20T12:00:00Z').getTime()
    const next = new Date(base + 600_000).toISOString()
    expect(projectEnergy({ current: 90, max: 100, next_energy_at: next }, base + 600_000)).toBe(95)
  })
})

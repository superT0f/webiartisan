// @vitest-environment jsdom
import { describe, it, expect, beforeEach, vi } from 'vitest'
import {
  readPositionCache, writePositionCache, useGeolocation,
} from '../useGeolocation.js'

const FIX = { latitude: 48.66, longitude: 2.56, accuracy: 12 }

function mockGeolocation({ fail = false } = {}) {
  const impl = (success, error) => {
    if (fail) error({ code: 3, message: 'timeout' })
    else success({ coords: { latitude: FIX.latitude, longitude: FIX.longitude, accuracy: FIX.accuracy } })
  }
  const geo = {
    getCurrentPosition: vi.fn(impl),
    watchPosition: vi.fn(() => 42),
    clearWatch: vi.fn(),
  }
  Object.defineProperty(navigator, 'geolocation', { value: geo, configurable: true })
  return geo
}

beforeEach(() => {
  localStorage.clear()
})

describe('cache de position (localStorage)', () => {
  it('retourne null quand le cache est vide', () => {
    expect(readPositionCache()).toBeNull()
  })

  it('lit ce que write a écrit', () => {
    writePositionCache(FIX)
    expect(readPositionCache()).toMatchObject({ latitude: FIX.latitude, longitude: FIX.longitude })
  })

  it('retourne null si le cache est trop vieux (> 5 min)', () => {
    localStorage.setItem('geo_last_position', JSON.stringify({ ...FIX, at: Date.now() - 6 * 60 * 1000 }))
    expect(readPositionCache()).toBeNull()
  })

  it('retourne null sur JSON corrompu', () => {
    localStorage.setItem('geo_last_position', '{oops')
    expect(readPositionCache()).toBeNull()
  })
})

describe('useGeolocation — repli sur la dernière position', () => {
  it('refresh() garde la dernière position connue si le GPS lâche', async () => {
    const geo = mockGeolocation()
    const { position, refresh } = useGeolocation()

    await refresh() // fix réussi
    expect(position.value).toMatchObject({ latitude: FIX.latitude })

    geo.getCurrentPosition.mockImplementation((s, e) => e({ code: 3 }))
    const pos = await refresh() // échec → repli
    expect(pos).toMatchObject({ latitude: FIX.latitude })
  })

  it('start() sans erreur affichée si une position est déjà connue', async () => {
    mockGeolocation({ fail: true })
    const { position, error, start, refresh } = useGeolocation()

    // Une position existe déjà (fix précédent simulé via refresh réussi)
    mockGeolocation()
    await refresh()
    expect(position.value).not.toBeNull()

    mockGeolocation({ fail: true })
    await start()
    expect(error.value).toBe('')
    expect(position.value).toMatchObject({ latitude: FIX.latitude })
  })
})

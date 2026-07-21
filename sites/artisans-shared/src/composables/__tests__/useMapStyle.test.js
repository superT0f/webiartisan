import { describe, it, expect } from 'vitest'
import { isMapTilerKey, useMapStyle } from '../useMapStyle.js'

describe('isMapTilerKey', () => {
  it('faux sans clé', () => {
    expect(isMapTilerKey(undefined)).toBe(false)
    expect(isMapTilerKey('')).toBe(false)
  })

  it('faux avec le placeholder', () => {
    expect(isMapTilerKey('your_maptiler_key_here')).toBe(false)
  })

  it('vrai avec une clé réelle', () => {
    expect(isMapTilerKey('abc123')).toBe(true)
  })
})

describe('useMapStyle', () => {
  it('style vectoriel MapTiler avec clé', () => {
    expect(useMapStyle('abc123')).toContain('api.maptiler.com')
  })

  it('fallback raster OSM sans clé', () => {
    const style = useMapStyle(undefined)
    expect(style.sources.osm.type).toBe('raster')
  })
})

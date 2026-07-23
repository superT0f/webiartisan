import { describe, it, expect } from 'vitest'
import { angleFromTop, angularDeltaCW, progressFromAccum } from '../swipeRingGeometry.js'

const PI = Math.PI

describe('angleFromTop', () => {
  it('12 h = 0, 3 h = π/2, 6 h = π, 9 h = 3π/2', () => {
    expect(angleFromTop(0, -10)).toBeCloseTo(0)
    expect(angleFromTop(10, 0)).toBeCloseTo(PI / 2)
    expect(angleFromTop(0, 10)).toBeCloseTo(PI)
    expect(angleFromTop(-10, 0)).toBeCloseTo(3 * PI / 2)
  })
})

describe('angularDeltaCW', () => {
  it('delta horaire simple', () => {
    expect(angularDeltaCW(0.5, 1.0)).toBeCloseTo(0.5)
  })

  it('wrap autour de 2π (passage par 12 h)', () => {
    expect(angularDeltaCW(6.0, 0.5)).toBeCloseTo(0.5 + (2 * PI - 6.0))
  })

  it('même angle = 0', () => {
    expect(angularDeltaCW(1.2, 1.2)).toBe(0)
  })
})

describe('progressFromAccum', () => {
  it('0 sans mouvement, 0.5 à π, 1 au tour complet', () => {
    expect(progressFromAccum(0)).toBe(0)
    expect(progressFromAccum(PI)).toBeCloseTo(0.5)
    expect(progressFromAccum(2 * PI)).toBe(1)
  })

  it('clampé à 1 au-delà', () => {
    expect(progressFromAccum(3 * PI)).toBe(1)
  })
})

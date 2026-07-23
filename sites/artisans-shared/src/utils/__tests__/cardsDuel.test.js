import { describe, it, expect } from 'vitest'
import { elementBeats, ELEMENT_EMOJI } from '../cardsDuel.js'

describe('elementBeats', () => {
  it('feu > plante > eau > feu', () => {
    expect(elementBeats('feu', 'plante')).toBe(true)
    expect(elementBeats('plante', 'eau')).toBe(true)
    expect(elementBeats('eau', 'feu')).toBe(true)
    expect(elementBeats('plante', 'feu')).toBe(false)
    expect(elementBeats('eau', 'plante')).toBe(false)
    expect(elementBeats('feu', 'eau')).toBe(false)
  })

  it('égalité élément = pas de vainqueur d élément', () => {
    expect(elementBeats('feu', 'feu')).toBe(false)
  })

  it('emojis des 3 éléments', () => {
    for (const e of ['feu', 'eau', 'plante']) expect(ELEMENT_EMOJI[e]).toBeTruthy()
  })
})

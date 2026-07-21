import { describe, it, expect } from 'vitest'
import { pickMapAction, PICKUP_RANGE_M } from '../pickMapAction.js'

const target = { target_type: 'artisan', target_id: 1, name: 'Boulangerie', distance_m: 80 }

describe('pickMapAction', () => {
  it('null sans objet ni cible', () => {
    expect(pickMapAction([], null)).toBeNull()
  })

  it('check-in si aucune cible de ramassage à portée', () => {
    expect(pickMapAction([], target)).toEqual({ kind: 'checkin', target })
  })

  it('objet hors portée ignoré', () => {
    const far = { id: 1, distance_m: PICKUP_RANGE_M + 1 }
    expect(pickMapAction([far], target)).toEqual({ kind: 'checkin', target })
  })

  it('objet à portée prioritaire sur le check-in', () => {
    const near = { id: 2, distance_m: PICKUP_RANGE_M }
    expect(pickMapAction([near], target)).toEqual({ kind: 'pickup', object: near })
  })

  it('prend le premier objet à portée (tri par distance)', () => {
    const a = { id: 3, distance_m: 12 }
    const b = { id: 4, distance_m: 30 }
    expect(pickMapAction([b, a], null)).toEqual({ kind: 'pickup', object: a })
  })
})

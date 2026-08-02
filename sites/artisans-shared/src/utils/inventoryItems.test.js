import { describe, it, expect } from 'vitest'
import { groupInventoryByType, ITEM_META } from './inventoryItems.js'

describe('groupInventoryByType', () => {
  it('regroupe par type avec compteurs et premier id', () => {
    const items = [
      { id: 3, type: 'energy_store' },
      { id: 7, type: 'boss_spawner' },
      { id: 9, type: 'energy_store' },
    ]
    expect(groupInventoryByType(items)).toEqual([
      { type: 'energy_store', emoji: ITEM_META.energy_store.emoji, label: ITEM_META.energy_store.label, count: 2, firstId: 3 },
      { type: 'boss_spawner', emoji: ITEM_META.boss_spawner.emoji, label: ITEM_META.boss_spawner.label, count: 1, firstId: 7 },
    ])
  })
  it('retourne [] pour un inventaire vide', () => {
    expect(groupInventoryByType([])).toEqual([])
  })
  it('ignore les types inconnus (fallback emoji générique)', () => {
    const [g] = groupInventoryByType([{ id: 1, type: 'mystere' }])
    expect(g.count).toBe(1)
    expect(g.emoji).toBe('🎁')
  })
})

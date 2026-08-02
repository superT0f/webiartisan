// Métadonnées d'affichage des objets d'inventaire (emojis — pas d'images dédiées).
export const ITEM_META = {
  boss_spawner: { emoji: '🎯', label: 'Leurre à Big Brother', description: 'Invoque Affamer de Gaffe près de ta position (il reste 2 h).' },
  energy_store: { emoji: '🔋', label: "Réserve d'énergie", description: '+30 ⚡ d\'énergie immédiatement.' },
}

const FALLBACK = { emoji: '🎁', label: 'Objet', description: '' }

/**
 * Regroupe les items d'inventaire (lignes individuelles) par type.
 * @param {Array<{id:number, type:string}>} items
 * @returns {Array<{type:string, emoji:string, label:string, count:number, firstId:number}>}
 */
export function groupInventoryByType(items) {
  const groups = new Map()
  for (const item of items || []) {
    const g = groups.get(item.type)
    if (g) g.count += 1
    else groups.set(item.type, { type: item.type, count: 1, firstId: item.id })
  }
  return [...groups.values()].map((g) => {
    const meta = ITEM_META[g.type] || FALLBACK
    return { ...g, emoji: meta.emoji, label: meta.label }
  })
}

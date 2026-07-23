/** Cycle élémentaire du duel de cartes (miroir de boss_card_beats serveur). */

export const ELEMENT_EMOJI = { feu: '🔥', eau: '💧', plante: '🌿' }

const WINS = { feu: 'plante', plante: 'eau', eau: 'feu' }

export function elementBeats(a, b) {
  return WINS[a] === b
}

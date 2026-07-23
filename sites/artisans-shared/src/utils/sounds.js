/**
 * Sons du jeu (public/sounds/*.mp3, fournis par l'utilisateur).
 * Repli silencieux si un fichier est absent ou la lecture bloquée.
 *
 * Kinds : 'success' (check-in), 'xp-boost' (ramassage), 'quest-complete',
 *         'level-up', 'badge'
 */
export function playSound(kind) {
  try {
    new Audio(`/sounds/${kind}.mp3`).play().catch(() => {})
  } catch (err) {
    console.warn('Son indisponible', kind, err)
  }
}

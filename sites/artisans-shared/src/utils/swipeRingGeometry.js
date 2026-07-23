/**
 * Géométrie de l'anneau de swipe (SwipeRingOverlay).
 * Convention : angle 0 = 12 h, sens horaire, en radians dans [0, 2π).
 */

export function angleFromTop(dx, dy) {
  const a = Math.atan2(dx, -dy)
  return a < 0 ? a + 2 * Math.PI : a
}

export function angularDeltaCW(prev, next) {
  const d = next - prev
  return d < 0 ? d + 2 * Math.PI : d
}

export function progressFromAccum(accum, required = 2 * Math.PI) {
  return Math.min(1, Math.max(0, accum / required))
}

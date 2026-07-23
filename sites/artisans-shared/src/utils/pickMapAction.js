// Miroir du PICKUP_RANGE_M serveur (lib/WorldObjects.php)
export const PICKUP_RANGE_M = 150

/**
 * Choisit l'action du FAB : ramassage si un objet est à portée,
 * sinon check-in sur la cible la plus proche.
 */
export function pickMapAction(objects, nearestTarget) {
  const inRange = (objects || [])
    .filter(o => o.distance_m <= PICKUP_RANGE_M)
    .sort((a, b) => a.distance_m - b.distance_m)
  if (inRange.length) return { kind: 'pickup', object: inRange[0] }
  if (nearestTarget) return { kind: 'checkin', target: nearestTarget }
  return null
}

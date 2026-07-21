export function isMapTilerKey(apiKey) {
  return !!(apiKey && apiKey !== 'your_maptiler_key_here')
}

export function terrainTilesUrl(apiKey) {
  return `https://api.maptiler.com/tiles/terrain-rgb-v2/tiles.json?key=${apiKey}`
}

export function useMapStyle(apiKey) {
  if (isMapTilerKey(apiKey)) {
    return `https://api.maptiler.com/maps/bright/style.json?key=${apiKey}`
  }
  return {
    version: 8,
    glyphs: 'https://fonts.openmaptiles.org/{fontstack}/{range}.pbf',
    sources: {
      osm: {
        type: 'raster',
        tiles: ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'],
        tileSize: 256,
        attribution: '&copy; OpenStreetMap contributors'
      }
    },
    layers: [
      {
        id: 'background',
        type: 'background',
        paint: { 'background-color': '#fef3c7' }
      },
      {
        id: 'osm-raster',
        type: 'raster',
        source: 'osm',
        paint: {
          'raster-brightness-max': 1,
          'raster-saturation': 0.4,
          'raster-contrast': 0.1
        }
      }
    ]
  }
}

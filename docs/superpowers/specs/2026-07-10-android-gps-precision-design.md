# Design : amélioration de la précision GPS dans l'app Android

## Contexte

L'application Android actuelle (`/home/tof/code/webiartisan-flutter-app`) est un shell WebView qui expose la position au contenu web via un bridge natif custom (`tech.prigent.webiartisan/location`). L'exploration du code a montré plusieurs points faibles :

- Aucun package Flutter de géolocalisation (`geolocator`, `location`, etc.) n'est utilisé.
- Le bridge natif Kotlin (`LocationService.kt`) semble mal branché : il étend `FlutterActivity()` alors que l'activité déclarée est `MainActivity.kt` (`FlutterFragmentActivity()`), donc le `MethodChannel` risque de ne jamais être enregistré.
- La demande de permission runtime n'est pas implémentée (`requestPermission` renvoie toujours `"denied"`).
- La localisation est one-shot, sans filtrage de précision, sans fallback réseau, sans suivi continu.
- Le côté web ne consomme pas encore la position, bien que le bridge existe.

## Objectifs

1. Remplacer le bridge natif custom par une solution fiable et maintenable.
2. Obtenir une position précise (objectif < 10 m, idéalement < 5 m en extérieur).
3. Garantir la fiabilité : gérer permissions, GPS désactivé, timeout, fallback réseau.
4. Permettre le suivi continu pour la carte / la navigation / les mini-jeux de proximité.
5. Exposer la position au web via un contrat JS stable.

## Approches considérées

| Approche | Description | Avantages | Inconvénients |
|----------|-------------|-----------|---------------|
| 1. Remplacement minimal par `geolocator` | One-shot `getCurrentPosition(best)` | Rapide, peu de code | Pas de suivi temps réel, pas de filtrage de précision |
| 2. **Service robuste avec flux continu** (choix retenu) | `geolocator` + `permission_handler`, `getCurrentPosition` + `getPositionStream`, filtrage, timeout, fallback | Précision, fiabilité, temps réel, maintenable | Un peu plus de code |
| 3. Localisation intelligente avancée | Approche 2 + géofencing, modes batterie/précision, calibration | Très complet | Overkill pour la phase actuelle |

## Architecture

```
┌─────────────────────────────────────────┐
│  Web (artisans-*.prigent.tech)          │
│  - helper JS flutterBridge.getPosition() │
│  - carte /carte centrée sur position     │
└────────────┬────────────────────────────┘
             │ JS messages via FlutterBridge
┌────────────▼────────────────────────────┐
│  Flutter WebViewScreen                  │
│  - route les messages getPosition/      │
│    watchPosition/cancelWatchPosition    │
└────────────┬────────────────────────────┘
             │ appels Dart
┌────────────▼────────────────────────────┐
│  LocationService (Dart)                 │
│  - getCurrentPosition()                 │
│  - getPositionStream()                  │
│  - filtrage accuracy + timeout          │
└────────────┬────────────────────────────┘
             │ packages Flutter
┌────────────▼────────────────────────────┐
│  geolocator + permission_handler        │
│  Android FusedLocationProvider / GPS    │
└─────────────────────────────────────────┘
```

## Composants

### `lib/services/location_service.dart`

Singleton exposant :

```dart
Future<LocationResult> getCurrentPosition({
  LocationAccuracy accuracy = LocationAccuracy.best,
  Duration timeout = const Duration(seconds: 15),
  double maxAcceptableAccuracyMeters = 20.0,
});

Stream<LocationResult> getPositionStream({
  LocationAccuracy accuracy = LocationAccuracy.best,
  double distanceFilter = 5.0,
});

Future<LocationPermissionStatus> checkPermission();
Future<LocationPermissionStatus> requestPermission();
Future<bool> isLocationServiceEnabled();
```

`LocationResult` :

```dart
class LocationResult {
  final double latitude;
  final double longitude;
  final double accuracy;
  final double? altitude;
  final double? heading;
  final double? speed;
  final DateTime timestamp;
}
```

Comportement de `getCurrentPosition` :

1. Vérifier service GPS activé.
2. Demander permission `locationWhenInUse` si besoin.
3. Démarrer `getPositionStream` avec `accuracy: best`.
4. Attendre la première position avec `accuracy <= maxAcceptableAccuracyMeters`.
5. Si timeout atteint, renvoyer la **meilleure** position obtenue (même si > seuil).
6. Si aucune position obtenue, tenter avec `accuracy: medium` (réseau/WiFi).
7. Logger accuracy, provider, timestamp en debug.

### `lib/screens/webview_screen.dart`

Enrichir le `JavaScriptChannel` `FlutterBridge` pour router :

- `getPosition` → `LocationService.getCurrentPosition`
- `watchPosition` → démarre un stream, chaque update renvoie un message JS
- `cancelWatchPosition` → arrête le stream associé

Contrat de message JS (cf. section suivante).

### Suppressions / nettoyage

- Supprimer `android/app/src/main/kotlin/tech/prigent/webiartisan/LocationService.kt` (bridge natif custom).
- Supprimer l'enregistrement du `MethodChannel` natif dans `MainActivity.kt` s'il existe.

## Contrat bridge JS

### Requête JS → Flutter

```js
FlutterBridge.postMessage(JSON.stringify({
  action: 'getPosition',
  callbackId: 'uuid-123',
  payload: {
    accuracy: 'best',        // best | medium | low
    timeout: 15000,          // ms
    maxAccuracy: 20,         // mètres
  }
}));
```

```js
FlutterBridge.postMessage(JSON.stringify({
  action: 'watchPosition',
  callbackId: 'uuid-456',
  payload: {
    accuracy: 'best',
    distanceFilter: 5,
  }
}));
```

```js
FlutterBridge.postMessage(JSON.stringify({
  action: 'cancelWatchPosition',
  callbackId: 'uuid-456',
}));
```

### Réponse Flutter → JS

Le WebView existant appelle `window.onBiometricResponse(callbackId, data)`. On conserve ce mécanisme partagé.

```js
window.onBiometricResponse('uuid-123', {
  success: true,
  data: {
    latitude: 48.664,
    longitude: 2.568,
    accuracy: 4.5,
    altitude: 92.0,
    heading: 180.0,
    speed: 0.0,
    timestamp: '2026-07-10T16:00:00Z',
  }
});
```

En cas d'erreur :

```js
window.onBiometricResponse('uuid-123', {
  success: false,
  error: 'permission_denied', // service_disabled | timeout | permission_denied | unknown
  message: '...',
});
```

## Permissions Android

- Conserver dans `AndroidManifest.xml` :
  - `android.permission.ACCESS_FINE_LOCATION`
  - `android.permission.ACCESS_COARSE_LOCATION`
- Ne **pas** ajouter `ACCESS_BACKGROUND_LOCATION` (hors scope).
- Utiliser `permission_handler` pour la demande runtime.
- Gérer les cas :
  - refus définitif → afficher un dialog avec bouton "Ouvrir les paramètres"
  - service GPS désactivé → proposer d'ouvrir les paramètres localisation

## Côté web

Créer `sites/artisans-shared/src/utils/flutterBridge.js` (ou `.ts`) :

```js
export function getPosition(options = {}) {
  return new Promise((resolve, reject) => {
    const callbackId = generateId();
    pending[callbackId] = { resolve, reject };
    FlutterBridge.postMessage(JSON.stringify({ action: 'getPosition', callbackId, payload: options }));
  });
}

export function watchPosition(callback, options = {}) {
  const callbackId = generateId();
  watchers[callbackId] = callback;
  FlutterBridge.postMessage(JSON.stringify({ action: 'watchPosition', callbackId, payload: options }));
  return callbackId;
}

export function clearWatch(callbackId) {
  delete watchers[callbackId];
  FlutterBridge.postMessage(JSON.stringify({ action: 'cancelWatchPosition', callbackId }));
}
```

Utilisation sur `/carte` (`sites/artisans-shared/src/views/MapView.vue` ou `ImmersiveMap.vue`) :

- Au montage, appeler `getPosition()` pour centrer la carte.
- Afficher un marqueur bleu à la position utilisateur.
- Afficher un cercle d'incertitude basé sur `accuracy`.
- En mode suivi, appeler `watchPosition()` et recentrer à chaque update.
- Afficher un message si permission refusée ou GPS désactivé.

## Tests & validation

### Tests unitaires Flutter

- Mocker `geolocator` et `permission_handler`.
- Tester `getCurrentPosition` :
  - retourne la position si accuracy <= seuil
  - renvoie la meilleure position si timeout
  - fallback réseau si GPS échoue
  - renvoie une erreur si permission refusée
- Tester `getPositionStream` :
  - émet les positions filtrées
  - s'arrête correctement sur `cancelWatchPosition`

### Tests d'intégration

- Vérifier que le bridge JS reçoit bien les réponses.
- Vérifier le cycle `watchPosition` / `cancelWatchPosition`.

### Tests manuels

- Extérieur : vérifier accuracy < 10 m.
- Intérieur : vérifier fallback réseau / WiFi.
- Refus de permission : message explicatif.
- GPS désactivé : redirection paramètres.

## Dépendances

Ajouter dans `pubspec.yaml` :

```yaml
dependencies:
  geolocator: ^13.0.0
  permission_handler: ^11.0.0
```

## Non-goals

- Pas de géolocalisation en arrière-plan (`ACCESS_BACKGROUND_LOCATION`).
- Pas d'implémentation iOS (projet Android-only actuellement).
- Pas de géofencing dans cette itération.

## Notes de mise en œuvre

- Le bridge natif custom existant doit être supprimé proprement pour éviter les conflits.
- Vérifier que `MainActivity.kt` n'enregistre plus l'ancien `MethodChannel`.
- Penser à mettre à jour `pubspec.lock` et les builds CI.
- Bumper la version Flutter si l'app est republiée après cette évolution.

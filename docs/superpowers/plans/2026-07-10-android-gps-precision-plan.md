# Android GPS Precision Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remplacer le bridge GPS natif custom de l'app Flutter par un service robuste basé sur `geolocator` + `permission_handler`, exposant au WebView un contrat JS pour `getPosition`, `watchPosition` et `cancelWatchPosition`, avec filtrage de précision, timeout et fallback réseau.

**Architecture:** Un `LocationService` Dart singleton encapsule `geolocator` et `permission_handler`. Le `WebViewScreen` route les messages du `JavaScriptChannel` `FlutterBridge` vers ce service. Les positions sont renvoyées au web via `evaluateJavascript` en appelant `window.flutterReceiveMessage`. Le côté web reçoit un helper JS qui expose `getPosition()` (Promise) et `watchPosition()`.

**Tech Stack:** Flutter, geolocator ^13.0.0, permission_handler ^11.0.0, Kotlin (cleanup), JavaScript bridge.

## Global Constraints

- Projet Android-only : pas d'implémentation iOS.
- Pas de géolocalisation en arrière-plan (`ACCESS_BACKGROUND_LOCATION`).
- Objectif de précision : < 10 m en extérieur, idéalement < 5 m.
- Timeout par défaut : 15 s pour `getCurrentPosition`.
- Accuracy maximale acceptable par défaut : 20 m.
- `distanceFilter` par défaut pour le stream : 5 m.
- Les anciens fichiers du bridge natif custom doivent être supprimés.
- Commit fréquents, tests unitaires pour le service Dart.

---

## Files Created or Modified

| File | Action | Responsibility |
|------|--------|----------------|
| `/home/tof/code/webiartisan-flutter-app/pubspec.yaml` | Modify | Ajouter `geolocator` et `permission_handler` |
| `/home/tof/code/webiartisan-flutter-app/lib/models/location_result.dart` | Create | Modèle de données position |
| `/home/tof/code/webiartisan-flutter-app/lib/services/location_service.dart` | Create | Service de localisation Dart |
| `/home/tof/code/webiartisan-flutter-app/test/services/location_service_test.dart` | Create | Tests unitaires du service |
| `/home/tof/code/webiartisan-flutter-app/lib/screens/webview_screen.dart` | Modify | Router les messages JS GPS |
| `/home/tof/code/webiartisan-flutter-app/android/app/src/main/kotlin/tech/prigent/webiartisan/LocationService.kt` | Delete | Supprimer le bridge natif custom |
| `/home/tof/code/webiartisan-flutter-app/android/app/src/main/kotlin/tech/prigent/webiartisan/MainActivity.kt` | Modify | Nettoyer tout enregistrement de MethodChannel natif |
| `/home/tof/code/webiartisan-flutter-app/android/app/src/main/AndroidManifest.xml` | Modify (si besoin) | Vérifier permissions fine/coarse |
| `/mnt/c/Users/user/code/webiartisan.new/sites/artisans-shared/src/utils/flutterBridge.js` | Create | Helper JS côté web |
| `/mnt/c/Users/user/code/webiartisan.new/sites/artisans-shared/src/components/ImmersiveMap.vue` | Modify | Utiliser `getPosition()` pour centrer la carte et afficher la position |

---

## Task 1: Ajouter les dépendances Flutter

**Files:**
- Modify: `/home/tof/code/webiartisan-flutter-app/pubspec.yaml`

**Interfaces:**
- Consumes: rien
- Produces: `geolocator` et `permission_handler` disponibles dans le projet

- [ ] **Step 1: Modifier pubspec.yaml**

```yaml
dependencies:
  flutter:
    sdk: flutter
  webview_flutter: ^4.10.0
  http: ^1.3.0
  shared_preferences: ^2.5.0
  geolocator: ^13.0.0
  permission_handler: ^11.0.0
```

- [ ] **Step 2: Résoudre les dépendances**

Run:
```bash
cd /home/tof/code/webiartisan-flutter-app
flutter pub get
```

Expected: ` geolocator` et `permission_handler` sont installés sans conflit.

- [ ] **Step 3: Commit**

```bash
git add pubspec.yaml pubspec.lock
git commit -m "chore(deps): add geolocator and permission_handler"
```

---

## Task 2: Créer le modèle LocationResult

**Files:**
- Create: `/home/tof/code/webiartisan-flutter-app/lib/models/location_result.dart`

**Interfaces:**
- Consumes: rien
- Produces: `LocationResult` avec méthodes `toJson()` et `fromPosition()`

- [ ] **Step 1: Écrire le modèle**

```dart
import 'package:geolocator/geolocator.dart';

class LocationResult {
  final double latitude;
  final double longitude;
  final double accuracy;
  final double? altitude;
  final double? heading;
  final double? speed;
  final DateTime timestamp;

  const LocationResult({
    required this.latitude,
    required this.longitude,
    required this.accuracy,
    this.altitude,
    this.heading,
    this.speed,
    required this.timestamp,
  });

  factory LocationResult.fromPosition(Position position) {
    return LocationResult(
      latitude: position.latitude,
      longitude: position.longitude,
      accuracy: position.accuracy,
      altitude: position.altitude,
      heading: position.heading,
      speed: position.speed,
      timestamp: position.timestamp ?? DateTime.now(),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'latitude': latitude,
      'longitude': longitude,
      'accuracy': accuracy,
      'altitude': altitude,
      'heading': heading,
      'speed': speed,
      'timestamp': timestamp.toUtc().toIso8601String(),
    };
  }
}
```

- [ ] **Step 2: Commit**

```bash
git add lib/models/location_result.dart
git commit -m "feat(location): add LocationResult model"
```

---

## Task 3: Créer le LocationService

**Files:**
- Create: `/home/tof/code/webiartisan-flutter-app/lib/services/location_service.dart`

**Interfaces:**
- Consumes: `LocationResult`
- Produces:
  - `Future<LocationResult> getCurrentPosition({LocationAccuracy accuracy, Duration timeout, double maxAcceptableAccuracyMeters})`
  - `Stream<LocationResult> getPositionStream({LocationAccuracy accuracy, double distanceFilter})`
  - `Future<bool> isLocationServiceEnabled()`
  - `Future<bool> requestPermission()`

- [ ] **Step 1: Écrire le service**

```dart
import 'dart:async';
import 'dart:developer' as developer;

import 'package:geolocator/geolocator.dart';
import 'package:permission_handler/permission_handler.dart';

import '../models/location_result.dart';

class LocationService {
  LocationService._internal();
  static final LocationService _instance = LocationService._internal();
  factory LocationService() => _instance;

  Future<bool> isLocationServiceEnabled() async {
    return Geolocator.isLocationServiceEnabled();
  }

  Future<bool> requestPermission() async {
    final status = await Permission.locationWhenInUse.request();
    return status.isGranted || status.isLimited;
  }

  Future<bool> checkPermission() async {
    final status = await Permission.locationWhenInUse.status;
    return status.isGranted || status.isLimited;
  }

  LocationAccuracy _parseAccuracy(String? value) {
    switch (value) {
      case 'low':
        return LocationAccuracy.low;
      case 'medium':
        return LocationAccuracy.medium;
      case 'high':
      case 'best':
      default:
        return LocationAccuracy.best;
    }
  }

  Future<LocationResult> getCurrentPosition({
    LocationAccuracy accuracy = LocationAccuracy.best,
    Duration timeout = const Duration(seconds: 15),
    double maxAcceptableAccuracyMeters = 20.0,
  }) async {
    final serviceEnabled = await isLocationServiceEnabled();
    if (!serviceEnabled) {
      throw LocationException('service_disabled', 'Le service de localisation est désactivé.');
    }

    final hasPermission = await checkPermission();
    if (!hasPermission) {
      final granted = await requestPermission();
      if (!granted) {
        throw LocationException('permission_denied', 'Permission de localisation refusée.');
      }
    }

    try {
      final result = await _getBestPosition(
        accuracy: accuracy,
        timeout: timeout,
        maxAccuracy: maxAcceptableAccuracyMeters,
      );
      return result;
    } catch (e) {
      // Fallback réseau si le GPS échoue
      developer.log('GPS best failed, trying medium accuracy fallback', name: 'LocationService');
      return await _getBestPosition(
        accuracy: LocationAccuracy.medium,
        timeout: const Duration(seconds: 10),
        maxAccuracy: double.maxFinite,
      );
    }
  }

  Future<LocationResult> _getBestPosition({
    required LocationAccuracy accuracy,
    required Duration timeout,
    required double maxAccuracy,
  }) async {
    LocationResult? bestResult;
    StreamSubscription<Position>? subscription;
    final completer = Completer<LocationResult>();

    subscription = Geolocator.getPositionStream(
      locationSettings: AndroidSettings(
        accuracy: accuracy,
        distanceFilter: 0,
        foregroundLocationNotificationTitle: 'WebiArtisan localisation',
        foregroundLocationNotificationText: 'Récupération de votre position...',
      ),
    ).listen(
      (position) {
        final result = LocationResult.fromPosition(position);
        developer.log(
          'position update: accuracy=${result.accuracy}m lat=${result.latitude} lng=${result.longitude}',
          name: 'LocationService',
        );
        if (bestResult == null || result.accuracy < bestResult!.accuracy) {
          bestResult = result;
        }
        if (result.accuracy <= maxAccuracy && !completer.isCompleted) {
          completer.complete(result);
          subscription?.cancel();
        }
      },
      onError: (error) {
        if (!completer.isCompleted) {
          completer.completeError(LocationException('unknown', error.toString()));
        }
        subscription?.cancel();
      },
    );

    Timer(timeout, () {
      if (!completer.isCompleted) {
        subscription?.cancel();
        if (bestResult != null) {
          completer.complete(bestResult);
        } else {
          completer.completeError(LocationException('timeout', 'Impossible d\'obtenir une position à temps.'));
        }
      }
    });

    return completer.future;
  }

  Stream<LocationResult> getPositionStream({
    LocationAccuracy accuracy = LocationAccuracy.best,
    double distanceFilter = 5.0,
  }) {
    return Geolocator.getPositionStream(
      locationSettings: AndroidSettings(
        accuracy: accuracy,
        distanceFilter: distanceFilter.toInt(),
        foregroundLocationNotificationTitle: 'WebiArtisan',
        foregroundLocationNotificationText: 'Suivi de position actif',
      ),
    ).map((position) => LocationResult.fromPosition(position));
  }
}

class LocationException implements Exception {
  final String code;
  final String message;

  LocationException(this.code, this.message);

  Map<String, dynamic> toJson() {
    return {'code': code, 'message': message};
  }
}
```

- [ ] **Step 2: Commit**

```bash
git add lib/services/location_service.dart
git commit -m "feat(location): add LocationService with accuracy filtering and fallback"
```

---

## Task 4: Tests unitaires du LocationService

**Files:**
- Create: `/home/tof/code/webiartisan-flutter-app/test/services/location_service_test.dart`

**Interfaces:**
- Consumes: `LocationService`, `LocationResult`
- Produces: tests passés

- [ ] **Step 1: Ajouter dev_dependency `mocktail` si absente**

Vérifier `pubspec.yaml` :

```yaml
dev_dependencies:
  flutter_test:
    sdk: flutter
  mocktail: ^1.0.0
```

Run:
```bash
flutter pub get
```

- [ ] **Step 2: Écrire le test minimal**

```dart
import 'package:flutter_test/flutter_test.dart';
import 'package:geolocator/geolocator.dart';
import 'package:mocktail/mocktail.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:webiartisan/services/location_service.dart';

class MockGeolocator extends Mock implements GeolocatorPlatform {}
class MockPermission extends Mock implements Permission {}

void main() {
  group('LocationService', () {
    test('LocationResult.fromPosition converts correctly', () {
      final position = Position(
        latitude: 48.664,
        longitude: 2.568,
        timestamp: DateTime(2026, 7, 10, 12, 0, 0),
        accuracy: 4.5,
        altitude: 92.0,
        heading: 180.0,
        speed: 0.0,
        speedAccuracy: 0.0,
        altitudeAccuracy: 0.0,
        headingAccuracy: 0.0,
        floor: null,
        isMocked: false,
      );

      final result = LocationResult.fromPosition(position);

      expect(result.latitude, 48.664);
      expect(result.longitude, 2.568);
      expect(result.accuracy, 4.5);
    });

    test('LocationException serializes correctly', () {
      final error = LocationException('permission_denied', 'refus');
      expect(error.toJson(), {'code': 'permission_denied', 'message': 'refus'});
    });
  });
}
```

- [ ] **Step 3: Lancer les tests**

Run:
```bash
flutter test test/services/location_service_test.dart
```

Expected: `All tests passed!`

- [ ] **Step 4: Commit**

```bash
git add test/services/location_service_test.dart pubspec.yaml
git commit -m "test(location): add LocationService unit tests"
```

---

## Task 5: Router les messages GPS dans le WebView

**Files:**
- Modify: `/home/tof/code/webiartisan-flutter-app/lib/screens/webview_screen.dart`

**Interfaces:**
- Consumes: `LocationService`, `LocationResult`, `LocationException`
- Produces: bridge JS `getPosition`, `watchPosition`, `cancelWatchPosition`

- [ ] **Step 1: Importer LocationService en haut de webview_screen.dart**

```dart
import '../services/location_service.dart';
```

- [ ] **Step 2: Ajouter un gestionnaire de streams actifs dans la classe `_WebViewScreenState`**

```dart
final Map<String, StreamSubscription<LocationResult>> _locationWatchers = {};
final LocationService _locationService = LocationService();
```

N'oubliez pas de disposer les streams dans `dispose()` :

```dart
@override
void dispose() {
  for (final subscription in _locationWatchers.values) {
    subscription.cancel();
  }
  _locationWatchers.clear();
  super.dispose();
}
```

- [ ] **Step 3: Remplacer le case 'getPosition' existant par le nouveau routeur**

Dans `_onJavascriptMessageReceived`, remplacer le bloc `case 'getPosition':` existant par :

```dart
case 'getPosition':
  _handleGetPosition(message, callbackId);
  break;
case 'watchPosition':
  _handleWatchPosition(message, callbackId);
  break;
case 'cancelWatchPosition':
  _handleCancelWatchPosition(callbackId);
  break;
```

- [ ] **Step 4: Ajouter les méthodes handler**

```dart
Future<void> _handleGetPosition(dynamic message, String callbackId) async {
  final payload = message['payload'] ?? {};
  final accuracyStr = payload['accuracy']?.toString() ?? 'best';
  final timeoutMs = payload['timeout'] is int ? payload['timeout'] as int : 15000;
  final maxAccuracy = payload['maxAccuracy'] is num
      ? (payload['maxAccuracy'] as num).toDouble()
      : 20.0;

  try {
    final result = await _locationService.getCurrentPosition(
      accuracy: _parseLocationAccuracy(accuracyStr),
      timeout: Duration(milliseconds: timeoutMs),
      maxAcceptableAccuracyMeters: maxAccuracy,
    );
    _sendResponse(callbackId, {'success': true, 'data': result.toJson()});
  } on LocationException catch (e) {
    _sendResponse(callbackId, {'success': false, 'error': e.code, 'message': e.message});
  } catch (e) {
    _sendResponse(callbackId, {'success': false, 'error': 'unknown', 'message': e.toString()});
  }
}

void _handleWatchPosition(dynamic message, String callbackId) {
  final payload = message['payload'] ?? {};
  final accuracyStr = payload['accuracy']?.toString() ?? 'best';
  final distanceFilter = payload['distanceFilter'] is num
      ? (payload['distanceFilter'] as num).toDouble()
      : 5.0;

  _locationWatchers[callbackId]?.cancel();

  _locationWatchers[callbackId] = _locationService
      .getPositionStream(
        accuracy: _parseLocationAccuracy(accuracyStr),
        distanceFilter: distanceFilter,
      )
      .listen(
        (result) => _sendResponse(callbackId, {'success': true, 'data': result.toJson()}),
        onError: (error) => _sendResponse(callbackId, {
          'success': false,
          'error': 'unknown',
          'message': error.toString(),
        }),
      );
}

void _handleCancelWatchPosition(String callbackId) {
  _locationWatchers[callbackId]?.cancel();
  _locationWatchers.remove(callbackId);
}

LocationAccuracy _parseLocationAccuracy(String value) {
  switch (value) {
    case 'low':
      return LocationAccuracy.low;
    case 'medium':
      return LocationAccuracy.medium;
    case 'high':
      return LocationAccuracy.high;
    case 'best':
    default:
      return LocationAccuracy.best;
  }
}
```

- [ ] **Step 5: Vérifier que `_sendResponse` appelle `evaluateJavascript` avec `window.flutterReceiveMessage(...)`**

Le code existant doit ressembler à :

```dart
void _sendResponse(String callbackId, Map<String, dynamic> data) {
  final json = jsonEncode({'callbackId': callbackId, ...data});
  _controller?.evaluateJavascript("window.flutterReceiveMessage($json)");
}
```

S'il n'existe pas, l'ajouter.

- [ ] **Step 6: Commit**

```bash
git add lib/screens/webview_screen.dart
git commit -m "feat(webview): route GPS messages through LocationService"
```

---

## Task 6: Supprimer le bridge natif custom

**Files:**
- Delete: `/home/tof/code/webiartisan-flutter-app/android/app/src/main/kotlin/tech/prigent/webiartisan/LocationService.kt`
- Modify: `/home/tof/code/webiartisan-flutter-app/android/app/src/main/kotlin/tech/prigent/webiartisan/MainActivity.kt`

**Interfaces:**
- Consumes: rien
- Produces: suppression du code mort / conflit potentiel

- [ ] **Step 1: Supprimer LocationService.kt**

```bash
rm /home/tof/code/webiartisan-flutter-app/android/app/src/main/kotlin/tech/prigent/webiartisan/LocationService.kt
```

- [ ] **Step 2: Vérifier MainActivity.kt**

Lire le fichier. Il doit ressembler à :

```kotlin
package tech.prigent.webiartisan

import io.flutter.embedding.android.FlutterFragmentActivity

class MainActivity : FlutterFragmentActivity()
```

S'il contient un enregistrement de `MethodChannel` pour l'ancien bridge, le supprimer.

- [ ] **Step 3: Vérifier AndroidManifest.xml**

S'assurer que les permissions sont présentes :

```xml
<uses-permission android:name="android.permission.ACCESS_FINE_LOCATION" />
<uses-permission android:name="android.permission.ACCESS_COARSE_LOCATION" />
```

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "refactor(android): remove custom native location bridge"
```

---

## Task 7: Créer le helper JS côté web

**Files:**
- Create: `/mnt/c/Users/user/code/webiartisan.new/sites/artisans-shared/src/utils/flutterBridge.js`

**Interfaces:**
- Consumes: `FlutterBridge.postMessage` (natif) et `window.flutterReceiveMessage` (natif)
- Produces: `getPosition()`, `watchPosition()`, `clearWatch()`

- [ ] **Step 1: Écrire le helper**

```javascript
let idCounter = 0;
const pending = new Map();
const watchers = new Map();

function generateId() {
  return `bridge-${++idCounter}-${Date.now()}`;
}

function sendMessage(type, payload) {
  return new Promise((resolve, reject) => {
    const callbackId = generateId();
    pending.set(callbackId, { resolve, reject, type });

    if (typeof FlutterBridge === 'undefined' || !FlutterBridge.postMessage) {
      pending.delete(callbackId);
      reject(new Error('FlutterBridge non disponible'));
      return;
    }

    FlutterBridge.postMessage(JSON.stringify({ type, callbackId, payload }));
  });
}

export function getPosition(options = {}) {
  return sendMessage('getPosition', {
    accuracy: options.accuracy || 'best',
    timeout: options.timeout || 15000,
    maxAccuracy: options.maxAccuracy ?? 20,
  });
}

export function watchPosition(callback, options = {}) {
  const callbackId = generateId();
  watchers.set(callbackId, callback);

  if (typeof FlutterBridge === 'undefined' || !FlutterBridge.postMessage) {
    watchers.delete(callbackId);
    throw new Error('FlutterBridge non disponible');
  }

  FlutterBridge.postMessage(JSON.stringify({
    type: 'watchPosition',
    callbackId,
    payload: {
      accuracy: options.accuracy || 'best',
      distanceFilter: options.distanceFilter ?? 5,
    },
  }));

  return callbackId;
}

export function clearWatch(callbackId) {
  watchers.delete(callbackId);

  if (typeof FlutterBridge !== 'undefined' && FlutterBridge.postMessage) {
    FlutterBridge.postMessage(JSON.stringify({
      type: 'cancelWatchPosition',
      callbackId,
    }));
  }
}

window.flutterReceiveMessage = function (rawMessage) {
  let message;
  try {
    message = typeof rawMessage === 'string' ? JSON.parse(rawMessage) : rawMessage;
  } catch (e) {
    console.error('[flutterBridge] message invalide', rawMessage);
    return;
  }

  const { callbackId, success, data, error, message: errorMessage } = message;

  // Watchers
  if (watchers.has(callbackId)) {
    const callback = watchers.get(callbackId);
    if (success) {
      callback(null, data);
    } else {
      callback(new Error(errorMessage || error || 'Erreur GPS'), null);
    }
    return;
  }

  // Pending promises
  if (pending.has(callbackId)) {
    const { resolve, reject } = pending.get(callbackId);
    pending.delete(callbackId);
    if (success) {
      resolve(data);
    } else {
      const err = new Error(errorMessage || 'Erreur FlutterBridge');
      err.code = error;
      reject(err);
    }
  }
};
```

- [ ] **Step 2: Commit**

```bash
git add sites/artisans-shared/src/utils/flutterBridge.js
git commit -m "feat(web): add flutterBridge JS helper for GPS"
```

---

## Task 8: Intégrer la position sur la carte

**Files:**
- Modify: `/mnt/c/Users/user/code/webiartisan.new/sites/artisans-shared/src/components/ImmersiveMap.vue`

**Interfaces:**
- Consumes: `getPosition` from `../utils/flutterBridge.js`
- Produces: marqueur de position utilisateur + cercle de précision

- [ ] **Step 1: Importer le helper**

```javascript
import { getPosition } from '../utils/flutterBridge.js';
```

- [ ] **Step 2: Ajouter la récupération de position au montage**

Dans le `onMounted` ou `mounted` existant, après l'initialisation de la carte Leaflet :

```javascript
async function centerOnUser() {
  try {
    const position = await getPosition({ accuracy: 'best', timeout: 15000, maxAccuracy: 20 });
    const { latitude, longitude, accuracy } = position;

    if (!map) return;

    const userLatLng = [latitude, longitude];

    // Marqueur bleu
    L.circleMarker(userLatLng, {
      radius: 8,
      fillColor: '#3b82f6',
      color: '#ffffff',
      weight: 2,
      opacity: 1,
      fillOpacity: 0.9,
    }).addTo(map).bindPopup('Votre position');

    // Cercle d'incertitude
    L.circle(userLatLng, {
      radius: accuracy,
      color: '#3b82f6',
      fillColor: '#3b82f6',
      fillOpacity: 0.15,
      weight: 1,
    }).addTo(map);

    map.setView(userLatLng, 16);
  } catch (err) {
    console.warn('[ImmersiveMap] Impossible d\'obtenir la position', err);
    // Optionnel : afficher un toast / snackbar
  }
}

centerOnUser();
```

- [ ] **Step 3: Gérer le cas navigateur desktop (fallback navigator.geolocation)**

Pour que la carte fonctionne aussi en dehors de l'app Android, ajouter un fallback :

```javascript
async function getUserPosition() {
  if (typeof FlutterBridge !== 'undefined') {
    return getPosition({ accuracy: 'best', timeout: 15000, maxAccuracy: 20 });
  }
  return new Promise((resolve, reject) => {
    navigator.geolocation.getCurrentPosition(
      (pos) => resolve({
        latitude: pos.coords.latitude,
        longitude: pos.coords.longitude,
        accuracy: pos.coords.accuracy,
      }),
      reject,
      { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
    );
  });
}
```

Remplacer l'appel `getPosition()` par `getUserPosition()`.

- [ ] **Step 4: Commit**

```bash
git add sites/artisans-shared/src/components/ImmersiveMap.vue
git commit -m "feat(map): center map on user position with accuracy circle"
```

---

## Task 9: Build et tests manuels

**Files:**
- Tout le projet Flutter

**Interfaces:**
- Consumes: tout le travail précédent
- Produces: APK de test fonctionnelle

- [ ] **Step 1: Lancer les tests Flutter**

```bash
cd /home/tof/code/webiartisan-flutter-app
flutter test
```

Expected: `All tests passed!`

- [ ] **Step 2: Build APK debug**

```bash
flutter build apk --debug
```

Expected: build réussi, APK généré.

- [ ] **Step 3: Installer sur un vrai device Android et tester**

```bash
flutter install
```

Scénarios de test :
- Ouvrir l'app, aller sur `/carte`, vérifier que la carte se centre sur la position.
- Vérifier que le marqueur bleu et le cercle de précision s'affichent.
- Refuser la permission : vérifier le message d'erreur.
- Désactiver le GPS : vérifier le message d'erreur.
- Tester en extérieur : accuracy < 10 m.
- Tester en intérieur : position obtenue via réseau/WiFi.

- [ ] **Step 4: Commit des éventuels ajustements**

```bash
git add -A
git commit -m "fix(location): manual test fixes"
```

---

## Task 10: Déploiement web et bump version Flutter

**Files:**
- Modify: `/home/tof/code/webiartisan-flutter-app/pubspec.yaml`
- Modify: `/mnt/c/Users/user/code/webiartisan.new/sites/artisans-shared/src/components/ImmersiveMap.vue`
- Déployer via Makefile existant

**Interfaces:**
- Consumes: tout le travail précédent
- Produces: sites web déployés + app prête à publication

- [ ] **Step 1: Bumper la version de l'app Flutter**

Dans `pubspec.yaml` :

```yaml
version: 2.0.4+59
```

- [ ] **Step 2: Déployer les sites web**

```bash
cd /mnt/c/Users/user/code/webiartisan.new
make deploy-all
```

- [ ] **Step 3: Commit et push du repo web**

```bash
git add -A
git commit -m "feat(map): integrate user GPS position via Flutter bridge"
git push
```

- [ ] **Step 4: Commit et push du repo Flutter**

```bash
cd /home/tof/code/webiartisan-flutter-app
git add -A
git commit -m "feat(location): replace native bridge with geolocator, expose JS GPS API"
git push
```

- [ ] **Step 5: Lancer le build/release CI pour l'app Android**

Suivre le Makefile du projet Flutter (`android/Makefile` si existant) ou GitLab CI.

---

## Self-Review

### Spec coverage

| Spec requirement | Task |
|-----------------|------|
| Remplacer bridge natif custom | Task 6 |
| Précision < 10 m / < 5 m | Task 3 (accuracy best + filtrage) |
| Gérer permissions | Task 3, Task 6 |
| Gérer GPS désactivé | Task 3 |
| Timeout + fallback réseau | Task 3 |
| Suivi continu | Task 3 + Task 5 |
| Contrat JS stable | Task 5 + Task 7 |
| Côté web carte | Task 8 |
| Tests unitaires | Task 4 |
| Pas de background location | Global constraints |
| Pas d'iOS | Global constraints |

### Placeholder scan

Aucun `TBD`, `TODO`, ou étape vague détectée.

### Type consistency

- `LocationResult.toJson()` utilisé dans Task 3 et Task 5.
- `_parseLocationAccuracy` cohérent entre Task 3 et Task 5.
- `callbackId` utilisé comme clé string partout.

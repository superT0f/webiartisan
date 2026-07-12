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


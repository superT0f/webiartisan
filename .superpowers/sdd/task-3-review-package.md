# Review package: 317810d..4e0eb17

## Commits
4e0eb17 feat(location): add LocationService with accuracy filtering and fallback

## Files changed
 lib/services/location_service.dart | 145 +++++++++++++++++++++++++++++++++++++
 1 file changed, 145 insertions(+)

## Diff
diff --git a/lib/services/location_service.dart b/lib/services/location_service.dart
new file mode 100644
index 0000000..3dd4c0a
--- /dev/null
+++ b/lib/services/location_service.dart
@@ -0,0 +1,145 @@
+import 'dart:async';
+import 'dart:developer' as developer;
+
+import 'package:geolocator/geolocator.dart';
+import 'package:permission_handler/permission_handler.dart';
+
+import '../models/location_result.dart';
+
+class LocationService {
+  LocationService._internal();
+  static final LocationService _instance = LocationService._internal();
+  factory LocationService() => _instance;
+
+  Future<bool> isLocationServiceEnabled() async {
+    return Geolocator.isLocationServiceEnabled();
+  }
+
+  Future<bool> requestPermission() async {
+    final status = await Permission.locationWhenInUse.request();
+    return status.isGranted || status.isLimited;
+  }
+
+  Future<bool> checkPermission() async {
+    final status = await Permission.locationWhenInUse.status;
+    return status.isGranted || status.isLimited;
+  }
+
+  Future<LocationResult> getCurrentPosition({
+    LocationAccuracy accuracy = LocationAccuracy.best,
+    Duration timeout = const Duration(seconds: 15),
+    double maxAcceptableAccuracyMeters = 20.0,
+  }) async {
+    final serviceEnabled = await isLocationServiceEnabled();
+    if (!serviceEnabled) {
+      throw LocationException('service_disabled', 'Le service de localisation est désactivé.');
+    }
+
+    final hasPermission = await checkPermission();
+    if (!hasPermission) {
+      final granted = await requestPermission();
+      if (!granted) {
+        throw LocationException('permission_denied', 'Permission de localisation refusée.');
+      }
+    }
+
+    try {
+      final result = await _getBestPosition(
+        accuracy: accuracy,
+        timeout: timeout,
+        maxAccuracy: maxAcceptableAccuracyMeters,
+      );
+      return result;
+    } catch (e) {
+      // Fallback réseau si le GPS échoue
+      developer.log('GPS best failed, trying medium accuracy fallback', name: 'LocationService');
+      return await _getBestPosition(
+        accuracy: LocationAccuracy.medium,
+        timeout: const Duration(seconds: 10),
+        maxAccuracy: double.maxFinite,
+      );
+    }
+  }
+
+  Future<LocationResult> _getBestPosition({
+    required LocationAccuracy accuracy,
+    required Duration timeout,
+    required double maxAccuracy,
+  }) async {
+    LocationResult? bestResult;
+    StreamSubscription<Position>? subscription;
+    final completer = Completer<LocationResult>();
+
+    subscription = Geolocator.getPositionStream(
+      locationSettings: AndroidSettings(
+        accuracy: accuracy,
+        distanceFilter: 0,
+        foregroundNotificationConfig: const ForegroundNotificationConfig(
+          notificationTitle: 'WebiArtisan localisation',
+          notificationText: 'Récupération de votre position...',
+        ),
+      ),
+    ).listen(
+      (position) {
+        final result = LocationResult.fromPosition(position);
+        developer.log(
+          'position update: accuracy=${result.accuracy}m lat=${result.latitude} lng=${result.longitude}',
+          name: 'LocationService',
+        );
+        if (bestResult == null || result.accuracy < bestResult!.accuracy) {
+          bestResult = result;
+        }
+        if (result.accuracy <= maxAccuracy && !completer.isCompleted) {
+          completer.complete(result);
+          subscription?.cancel();
+        }
+      },
+      onError: (error) {
+        if (!completer.isCompleted) {
+          completer.completeError(LocationException('unknown', error.toString()));
+        }
+        subscription?.cancel();
+      },
+    );
+
+    Timer(timeout, () {
+      if (!completer.isCompleted) {
+        subscription?.cancel();
+        if (bestResult != null) {
+          completer.complete(bestResult);
+        } else {
+          completer.completeError(LocationException('timeout', 'Impossible d\'obtenir une position à temps.'));
+        }
+      }
+    });
+
+    return completer.future;
+  }
+
+  Stream<LocationResult> getPositionStream({
+    LocationAccuracy accuracy = LocationAccuracy.best,
+    double distanceFilter = 5.0,
+  }) {
+    return Geolocator.getPositionStream(
+      locationSettings: AndroidSettings(
+        accuracy: accuracy,
+        distanceFilter: distanceFilter.toInt(),
+        foregroundNotificationConfig: const ForegroundNotificationConfig(
+          notificationTitle: 'WebiArtisan',
+          notificationText: 'Suivi de position actif',
+        ),
+      ),
+    ).map((position) => LocationResult.fromPosition(position));
+  }
+}
+
+class LocationException implements Exception {
+  final String code;
+  final String message;
+
+  LocationException(this.code, this.message);
+
+  Map<String, dynamic> toJson() {
+    return {'code': code, 'message': message};
+  }
+}

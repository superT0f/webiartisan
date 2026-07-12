# Review package: 317810d..89a1483

## Commits
89a1483 fix(location): refine fallback logic, cancel timer, and add unit tests
4e0eb17 feat(location): add LocationService with accuracy filtering and fallback

## Files changed
 lib/services/location_service.dart       | 198 +++++++++++++++++++++++++++++++
 pubspec.lock                             |   8 ++
 pubspec.yaml                             |   1 +
 test/services/location_service_test.dart | 168 ++++++++++++++++++++++++++
 4 files changed, 375 insertions(+)

## Diff
diff --git a/lib/services/location_service.dart b/lib/services/location_service.dart
new file mode 100644
index 0000000..17b3808
--- /dev/null
+++ b/lib/services/location_service.dart
@@ -0,0 +1,198 @@
+import 'dart:async';
+import 'dart:developer' as developer;
+
+import 'package:flutter/foundation.dart';
+import 'package:geolocator/geolocator.dart';
+import 'package:permission_handler/permission_handler.dart';
+
+import '../models/location_result.dart';
+
+/// Abstraction over platform location APIs so [LocationService] can be tested.
+abstract class LocationServiceDeps {
+  Future<bool> isLocationServiceEnabled();
+  Future<bool> checkPermission();
+  Future<bool> requestPermission();
+  Stream<Position> getPositionStream({
+    required LocationAccuracy accuracy,
+    required int distanceFilter,
+  });
+}
+
+class _DefaultLocationServiceDeps implements LocationServiceDeps {
+  @override
+  Future<bool> isLocationServiceEnabled() => Geolocator.isLocationServiceEnabled();
+
+  @override
+  Future<bool> checkPermission() async {
+    final status = await Permission.locationWhenInUse.status;
+    return status.isGranted || status.isLimited;
+  }
+
+  @override
+  Future<bool> requestPermission() async {
+    final status = await Permission.locationWhenInUse.request();
+    return status.isGranted || status.isLimited;
+  }
+
+  @override
+  Stream<Position> getPositionStream({
+    required LocationAccuracy accuracy,
+    required int distanceFilter,
+  }) {
+    return Geolocator.getPositionStream(
+      locationSettings: AndroidSettings(
+        accuracy: accuracy,
+        distanceFilter: distanceFilter,
+        foregroundNotificationConfig: const ForegroundNotificationConfig(
+          notificationTitle: 'WebiArtisan localisation',
+          notificationText: 'Récupération de votre position...',
+        ),
+      ),
+    );
+  }
+}
+
+class LocationService {
+  final LocationServiceDeps _deps;
+
+  LocationService._internal() : _deps = _DefaultLocationServiceDeps();
+
+  @visibleForTesting
+  LocationService.withDeps(this._deps);
+
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
+    final serviceEnabled = await _deps.isLocationServiceEnabled();
+    if (!serviceEnabled) {
+      throw LocationException('service_disabled', 'Le service de localisation est désactivé.');
+    }
+
+    final hasPermission = await _deps.checkPermission();
+    if (!hasPermission) {
+      final granted = await _deps.requestPermission();
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
+      // Permission or service errors must not be retried.
+      if (e is LocationException &&
+          (e.code == 'permission_denied' || e.code == 'service_disabled')) {
+        rethrow;
+      }
+
+      // Fallback en précision moyenne si le GPS échoue ou en cas de timeout.
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
+    subscription = _deps
+        .getPositionStream(accuracy: accuracy, distanceFilter: 0)
+        .listen(
+          (position) {
+            final result = LocationResult.fromPosition(position);
+            developer.log(
+              'position update: accuracy=${result.accuracy}m lat=${result.latitude} lng=${result.longitude}',
+              name: 'LocationService',
+            );
+            if (bestResult == null || result.accuracy < bestResult!.accuracy) {
+              bestResult = result;
+            }
+            if (result.accuracy <= maxAccuracy && !completer.isCompleted) {
+              completer.complete(result);
+              subscription?.cancel();
+            }
+          },
+          onError: (error) {
+            if (!completer.isCompleted) {
+              completer.completeError(LocationException('unknown', error.toString()));
+            }
+            subscription?.cancel();
+          },
+        );
+
+    final timeoutTimer = Timer(timeout, () {
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
+    completer.future.whenComplete(() => timeoutTimer.cancel());
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
diff --git a/pubspec.lock b/pubspec.lock
index fde3ad7..6b5bd84 100644
--- a/pubspec.lock
+++ b/pubspec.lock
@@ -577,20 +577,28 @@ packages:
     source: hosted
     version: "1.15.0"
   mime:
     dependency: transitive
     description:
       name: mime
       sha256: "801fd0b26f14a4a58ccb09d5892c3fbdeff209594300a542492cf13fba9d247a"
       url: "https://pub.dev"
     source: hosted
     version: "1.0.6"
+  mocktail:
+    dependency: "direct dev"
+    description:
+      name: mocktail
+      sha256: "5e1bf53cc7baa8062a33b84424deb61513858ea05c601b8509e683815b5914aa"
+      url: "https://pub.dev"
+    source: hosted
+    version: "1.0.5"
   nm:
     dependency: transitive
     description:
       name: nm
       sha256: "2c9aae4127bdc8993206464fcc063611e0e36e72018696cd9631023a31b24254"
       url: "https://pub.dev"
     source: hosted
     version: "0.5.0"
   os_detect:
     dependency: transitive
diff --git a/pubspec.yaml b/pubspec.yaml
index d6297a5..405f173 100644
--- a/pubspec.yaml
+++ b/pubspec.yaml
@@ -24,20 +24,21 @@ dependencies:
   upgrader: ^10.3.0
   device_info_plus: ^10.1.2
   shared_preferences: ^2.5.0
   geolocator: ^13.0.0
   permission_handler: ^11.0.0
 dev_dependencies:
   flutter_test:
     sdk: flutter
   flutter_lints: ^5.0.0
   flutter_launcher_icons: ^0.14.3
+  mocktail: ^1.0.4
 
 flutter:
   uses-material-design: true
   assets:
     - assets/images/
 
 flutter_launcher_icons:
   android: "launcher_icon"
   ios: false
   image_path: "assets/images/launcher_icon.png"
diff --git a/test/services/location_service_test.dart b/test/services/location_service_test.dart
new file mode 100644
index 0000000..560577e
--- /dev/null
+++ b/test/services/location_service_test.dart
@@ -0,0 +1,168 @@
+import 'dart:async';
+
+import 'package:flutter_test/flutter_test.dart';
+import 'package:geolocator/geolocator.dart';
+import 'package:mocktail/mocktail.dart';
+import 'package:webiartisan/models/location_result.dart';
+import 'package:webiartisan/services/location_service.dart';
+
+class MockLocationServiceDeps extends Mock implements LocationServiceDeps {}
+
+Position _position({
+  required double latitude,
+  required double longitude,
+  required double accuracy,
+}) {
+  return Position(
+    latitude: latitude,
+    longitude: longitude,
+    timestamp: DateTime(2024, 6, 15, 12, 0, 0),
+    accuracy: accuracy,
+    altitude: 10.0,
+    altitudeAccuracy: 1.0,
+    heading: 90.0,
+    headingAccuracy: 5.0,
+    speed: 2.0,
+    speedAccuracy: 0.5,
+  );
+}
+
+void main() {
+  setUpAll(() {
+    registerFallbackValue(LocationAccuracy.best);
+    registerFallbackValue(0);
+  });
+
+  group('LocationResult.fromPosition', () {
+    test('converts a Position to LocationResult', () {
+      final position = _position(
+        latitude: 48.8566,
+        longitude: 2.3522,
+        accuracy: 10.0,
+      );
+      final result = LocationResult.fromPosition(position);
+
+      expect(result.latitude, 48.8566);
+      expect(result.longitude, 2.3522);
+      expect(result.accuracy, 10.0);
+      expect(result.altitude, 10.0);
+      expect(result.heading, 90.0);
+      expect(result.speed, 2.0);
+      expect(result.timestamp, position.timestamp);
+    });
+  });
+
+  group('LocationException', () {
+    test('toJson serializes code and message', () {
+      final exception = LocationException('permission_denied', 'Permission refusée.');
+      expect(exception.toJson(), {
+        'code': 'permission_denied',
+        'message': 'Permission refusée.',
+      });
+    });
+  });
+
+  group('LocationService.getCurrentPosition', () {
+    late MockLocationServiceDeps deps;
+    late LocationService service;
+
+    setUp(() {
+      deps = MockLocationServiceDeps();
+      service = LocationService.withDeps(deps);
+
+      when(() => deps.isLocationServiceEnabled()).thenAnswer((_) async => true);
+      when(() => deps.checkPermission()).thenAnswer((_) async => true);
+      when(() => deps.requestPermission()).thenAnswer((_) async => true);
+    });
+
+    test('returns the first accurate position within timeout', () async {
+      final controller = StreamController<Position>();
+      addTearDown(controller.close);
+
+      when(
+        () => deps.getPositionStream(
+          accuracy: any(named: 'accuracy'),
+          distanceFilter: any(named: 'distanceFilter'),
+        ),
+      ).thenAnswer((_) => controller.stream);
+
+      final future = service.getCurrentPosition(
+        maxAcceptableAccuracyMeters: 20.0,
+        timeout: const Duration(seconds: 5),
+      );
+
+      controller.add(_position(latitude: 1.0, longitude: 1.0, accuracy: 100.0));
+      await pumpEventQueue();
+
+      controller.add(_position(latitude: 2.0, longitude: 2.0, accuracy: 15.0));
+      final result = await future;
+
+      expect(result.accuracy, 15.0);
+      expect(result.latitude, 2.0);
+      expect(result.longitude, 2.0);
+    });
+
+    test('falls back to medium accuracy on GPS failure', () async {
+      final controller = StreamController<Position>();
+      addTearDown(controller.close);
+
+      when(
+        () => deps.getPositionStream(
+          accuracy: LocationAccuracy.best,
+          distanceFilter: 0,
+        ),
+      ).thenThrow(Exception('GPS failure'));
+
+      when(
+        () => deps.getPositionStream(
+          accuracy: LocationAccuracy.medium,
+          distanceFilter: 0,
+        ),
+      ).thenAnswer((_) => controller.stream);
+
+      final future = service.getCurrentPosition();
+      controller.add(_position(latitude: 3.0, longitude: 4.0, accuracy: 50.0));
+      final result = await future;
+
+      expect(result.accuracy, 50.0);
+      expect(result.latitude, 3.0);
+      expect(result.longitude, 4.0);
+
+      verify(
+        () => deps.getPositionStream(
+          accuracy: LocationAccuracy.best,
+          distanceFilter: 0,
+        ),
+      ).called(1);
+      verify(
+        () => deps.getPositionStream(
+          accuracy: LocationAccuracy.medium,
+          distanceFilter: 0,
+        ),
+      ).called(1);
+    });
+
+    test('throws permission_denied immediately without fallback', () async {
+      when(() => deps.checkPermission()).thenAnswer((_) async => false);
+      when(() => deps.requestPermission()).thenAnswer((_) async => false);
+
+      await expectLater(
+        service.getCurrentPosition(),
+        throwsA(
+          isA<LocationException>().having(
+            (e) => e.code,
+            'code',
+            'permission_denied',
+          ),
+        ),
+      );
+
+      verifyNever(
+        () => deps.getPositionStream(
+          accuracy: any(named: 'accuracy'),
+          distanceFilter: any(named: 'distanceFilter'),
+        ),
+      );
+    });
+  });
+}

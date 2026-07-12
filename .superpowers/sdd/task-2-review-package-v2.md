# Review package: 88191a2..317810d

## Commits
317810d feat(location): add LocationResult model

## Files changed
 lib/models/location_result.dart | 46 +++++++++++++++++++++++++++++++++++++++++
 1 file changed, 46 insertions(+)

## Diff
diff --git a/lib/models/location_result.dart b/lib/models/location_result.dart
new file mode 100644
index 0000000..50b171b
--- /dev/null
+++ b/lib/models/location_result.dart
@@ -0,0 +1,46 @@
+import 'package:geolocator/geolocator.dart';
+
+class LocationResult {
+  final double latitude;
+  final double longitude;
+  final double accuracy;
+  final double? altitude;
+  final double? heading;
+  final double? speed;
+  final DateTime timestamp;
+
+  const LocationResult({
+    required this.latitude,
+    required this.longitude,
+    required this.accuracy,
+    this.altitude,
+    this.heading,
+    this.speed,
+    required this.timestamp,
+  });
+
+  factory LocationResult.fromPosition(Position position) {
+    return LocationResult(
+      latitude: position.latitude,
+      longitude: position.longitude,
+      accuracy: position.accuracy,
+      altitude: position.altitude,
+      heading: position.heading,
+      speed: position.speed,
+      // ignore: dead_null_aware_expression
+      timestamp: position.timestamp ?? DateTime.now(),
+    );
+  }
+
+  Map<String, dynamic> toJson() {
+    return {
+      'latitude': latitude,
+      'longitude': longitude,
+      'accuracy': accuracy,
+      'altitude': altitude,
+      'heading': heading,
+      'speed': speed,
+      'timestamp': timestamp.toUtc().toIso8601String(),
+    };
+  }
+}

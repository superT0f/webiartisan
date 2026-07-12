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


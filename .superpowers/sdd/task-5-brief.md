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

- [ ] **Step 5: Vérifier que `_sendResponse` appelle `runJavaScript` avec `window.onBiometricResponse(...)`**

Le code existant ressemble à :

```dart
void _sendResponse(String? callbackId, Map<String, dynamic> data) {
  if (callbackId == null) return;
  final jsonResponse = json.encode(data);
  _controller.runJavaScript(
      'window.onBiometricResponse("$callbackId", $jsonResponse)');
}
```

Conserver ce mécanisme existant.

- [ ] **Step 6: Commit**

```bash
git add lib/screens/webview_screen.dart
git commit -m "feat(webview): route GPS messages through LocationService"
```

---


# Review package: b6ff523..e9884b2

## Commits
e9884b2 feat(webview): route GPS messages through LocationService

## Files changed
 lib/screens/webview_screen.dart | 118 +++++++++++++++++++++++++++-------------
 1 file changed, 81 insertions(+), 37 deletions(-)

## Diff
diff --git a/lib/screens/webview_screen.dart b/lib/screens/webview_screen.dart
index 62b49b8..cbdaf9b 100644
--- a/lib/screens/webview_screen.dart
+++ b/lib/screens/webview_screen.dart
@@ -1,27 +1,30 @@
 import 'dart:async';
 import 'dart:convert';
 import 'dart:io';
 import 'package:flutter/material.dart';
 import 'package:flutter/foundation.dart';
 import 'package:flutter/gestures.dart';
 import 'package:flutter/services.dart';
 import 'package:webview_flutter/webview_flutter.dart';
+import 'package:geolocator/geolocator.dart';
 import 'package:url_launcher/url_launcher.dart';
 import 'package:package_info_plus/package_info_plus.dart';
 import 'package:connectivity_plus/connectivity_plus.dart';
 import 'package:device_info_plus/device_info_plus.dart';
 import 'package:share_plus/share_plus.dart';
 import 'package:image_picker/image_picker.dart';
 import '../config/app_config.dart';
 import '../services/biometric_service.dart';
 import '../services/auth_service.dart';
+import '../services/location_service.dart';
+import '../models/location_result.dart';
 
 class WebViewScreen extends StatefulWidget {
   final String url;
   final String title;
 
   const WebViewScreen({
     super.key,
     required this.url,
     this.title = AppConfig.appName,
   });
@@ -36,20 +39,22 @@ class _WebViewScreenState extends State<WebViewScreen>
   final BiometricService _biometricService = BiometricService();
   final ImagePicker _picker = ImagePicker();
   bool _isInitialLoad = true;
   bool _isLoading = true;
   bool _isOffline = false;
   double _progress = 0;
   String _version = "";
   String _deviceModel = "Inconnu";
 
   late StreamSubscription<List<ConnectivityResult>> _connectivitySubscription;
+  final Map<String, StreamSubscription<LocationResult>> _locationWatchers = {};
+  final LocationService _locationService = LocationService();
   late AnimationController _animationController;
   late Animation<double> _fadeAnimation;
   late Animation<double> _scaleAnimation;
 
   @override
   void initState() {
     super.initState();
     _loadPackageInfo();
     _initConnectivity();
 
@@ -232,57 +237,27 @@ class _WebViewScreenState extends State<WebViewScreen>
               'base64': base64Image,
               'mimeType': 'image/${image.name.split('.').last}',
               'name': image.name,
             });
           } else {
             _sendResponse(callbackId, {'success': false, 'error': 'cancelled'});
           }
           break;
 
         case 'getPosition':
-          const platform = MethodChannel('tech.prigent.webiartisan/location');
-
-          try {
-            final bool serviceEnabled =
-                await platform.invokeMethod('isLocationServiceEnabled');
-            if (!serviceEnabled) {
-              _sendResponse(callbackId, {'success': false, 'error': 'service_disabled'});
-              return;
-            }
-
-            final String permission = await platform.invokeMethod('checkPermission');
-            if (permission == 'denied') {
-              _sendResponse(callbackId, {'success': false, 'error': 'permission_denied'});
-              return;
-            }
-
-            final Map<String, dynamic> locationData =
-                await platform.invokeMethod('getCurrentLocation');
-            _sendResponse(callbackId, {
-              'success': true,
-              'latitude': locationData['latitude'],
-              'longitude': locationData['longitude'],
-              'accuracy': locationData['accuracy'],
-            });
-          } on PlatformException catch (e) {
-            if (e.code == 'permission_denied') {
-              _sendResponse(callbackId, {'success': false, 'error': 'permission_denied'});
-            } else if (e.code == 'service_disabled') {
-              _sendResponse(callbackId, {'success': false, 'error': 'service_disabled'});
-            } else {
-              _sendResponse(callbackId, {
-                'success': false,
-                'error': 'location_error',
-                'message': e.message,
-              });
-            }
-          }
+          _handleGetPosition(data, callbackId);
+          break;
+        case 'watchPosition':
+          _handleWatchPosition(data, callbackId);
+          break;
+        case 'cancelWatchPosition':
+          _handleCancelWatchPosition(callbackId);
           break;
 
         default:
           debugPrint('Unknown bridge action: $action');
           _sendResponse(callbackId, {'error': 'unknown_action'});
       }
     } catch (e) {
       debugPrint('Error handling bridge message: $e');
     }
   }
@@ -358,20 +333,85 @@ class _WebViewScreenState extends State<WebViewScreen>
     }
   }
 
   void _sendResponse(String? callbackId, Map<String, dynamic> data) {
     if (callbackId == null) return;
     final jsonResponse = json.encode(data);
     _controller.runJavaScript(
         'window.onBiometricResponse("$callbackId", $jsonResponse)');
   }
 
+  Future<void> _handleGetPosition(dynamic message, String callbackId) async {
+    final payload = message['payload'] ?? {};
+    final accuracyStr = payload['accuracy']?.toString() ?? 'best';
+    final timeoutMs = payload['timeout'] is int ? payload['timeout'] as int : 15000;
+    final maxAccuracy = payload['maxAccuracy'] is num
+        ? (payload['maxAccuracy'] as num).toDouble()
+        : 20.0;
+
+    try {
+      final result = await _locationService.getCurrentPosition(
+        accuracy: _parseLocationAccuracy(accuracyStr),
+        timeout: Duration(milliseconds: timeoutMs),
+        maxAcceptableAccuracyMeters: maxAccuracy,
+      );
+      _sendResponse(callbackId, {'success': true, 'data': result.toJson()});
+    } on LocationException catch (e) {
+      _sendResponse(callbackId, {'success': false, 'error': e.code, 'message': e.message});
+    } catch (e) {
+      _sendResponse(callbackId, {'success': false, 'error': 'unknown', 'message': e.toString()});
+    }
+  }
+
+  void _handleWatchPosition(dynamic message, String callbackId) {
+    final payload = message['payload'] ?? {};
+    final accuracyStr = payload['accuracy']?.toString() ?? 'best';
+    final distanceFilter = payload['distanceFilter'] is num
+        ? (payload['distanceFilter'] as num).toDouble()
+        : 5.0;
+
+    _locationWatchers[callbackId]?.cancel();
+
+    _locationWatchers[callbackId] = _locationService
+        .getPositionStream(
+          accuracy: _parseLocationAccuracy(accuracyStr),
+          distanceFilter: distanceFilter,
+        )
+        .listen(
+          (result) => _sendResponse(callbackId, {'success': true, 'data': result.toJson()}),
+          onError: (error) => _sendResponse(callbackId, {
+            'success': false,
+            'error': 'unknown',
+            'message': error.toString(),
+          }),
+        );
+  }
+
+  void _handleCancelWatchPosition(String callbackId) {
+    _locationWatchers[callbackId]?.cancel();
+    _locationWatchers.remove(callbackId);
+  }
+
+  LocationAccuracy _parseLocationAccuracy(String value) {
+    switch (value) {
+      case 'low':
+        return LocationAccuracy.low;
+      case 'medium':
+        return LocationAccuracy.medium;
+      case 'high':
+        return LocationAccuracy.high;
+      case 'best':
+      default:
+        return LocationAccuracy.best;
+    }
+  }
+
   Future<void> _triggerHaptic(String type) async {
     switch (type) {
       case 'light':
         await HapticFeedback.lightImpact();
         break;
       case 'medium':
         await HapticFeedback.mediumImpact();
         break;
       case 'heavy':
         await HapticFeedback.heavyImpact();
@@ -451,20 +491,24 @@ class _WebViewScreenState extends State<WebViewScreen>
             _controller.reload();
           }
         });
       }
     });
   }
 
   @override
   void dispose() {
     _connectivitySubscription.cancel();
+    for (final subscription in _locationWatchers.values) {
+      subscription.cancel();
+    }
+    _locationWatchers.clear();
     _animationController.dispose();
     super.dispose();
   }
 
   @override
   Widget build(BuildContext context) {
     final isDarkMode = Theme.of(context).brightness == Brightness.dark;
     final backgroundColor = isDarkMode ? const Color(0xFF1A1A2E) : Colors.white;
 
     return PopScope(

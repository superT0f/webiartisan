# Review package: d4796d7..88191a2

## Commits
88191a2 chore(deps): add geolocator and permission_handler

## Files changed
 pubspec.lock | 98 +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++-
 pubspec.yaml |  5 +++-
 2 files changed, 101 insertions(+), 2 deletions(-)

## Diff
diff --git a/pubspec.lock b/pubspec.lock
index 1e2981d..fde3ad7 100644
--- a/pubspec.lock
+++ b/pubspec.lock
@@ -305,20 +305,68 @@ packages:
   flutter_test:
     dependency: "direct dev"
     description: flutter
     source: sdk
     version: "0.0.0"
   flutter_web_plugins:
     dependency: transitive
     description: flutter
     source: sdk
     version: "0.0.0"
+  geolocator:
+    dependency: "direct main"
+    description:
+      name: geolocator
+      sha256: f62bcd90459e63210bbf9c35deb6a51c521f992a78de19a1fe5c11704f9530e2
+      url: "https://pub.dev"
+    source: hosted
+    version: "13.0.4"
+  geolocator_android:
+    dependency: transitive
+    description:
+      name: geolocator_android
+      sha256: fcb1760a50d7500deca37c9a666785c047139b5f9ee15aa5469fae7dbbe3170d
+      url: "https://pub.dev"
+    source: hosted
+    version: "4.6.2"
+  geolocator_apple:
+    dependency: transitive
+    description:
+      name: geolocator_apple
+      sha256: "853803d6bb1713c094e935b4a5ae5f19c0308acf81da13fa9ff84fb4c70c0b73"
+      url: "https://pub.dev"
+    source: hosted
+    version: "2.3.14"
+  geolocator_platform_interface:
+    dependency: transitive
+    description:
+      name: geolocator_platform_interface
+      sha256: cdb082e4f048b69da244117b7914cc60d2a8897546ffaa4f2529c786ded7aee2
+      url: "https://pub.dev"
+    source: hosted
+    version: "4.2.8"
+  geolocator_web:
+    dependency: transitive
+    description:
+      name: geolocator_web
+      sha256: "19e485a0f8d6a88abcf9c53cba3a4105e14b7435ed8ac1c108c067b938fe8429"
+      url: "https://pub.dev"
+    source: hosted
+    version: "4.1.4"
+  geolocator_windows:
+    dependency: transitive
+    description:
+      name: geolocator_windows
+      sha256: "175435404d20278ffd220de83c2ca293b73db95eafbdc8131fe8609be1421eb6"
+      url: "https://pub.dev"
+    source: hosted
+    version: "0.2.5"
   html:
     dependency: transitive
     description:
       name: html
       sha256: "6d1264f2dffa1b1101c25a91dff0dc2daee4c18e87cd8538729773c073dbf602"
       url: "https://pub.dev"
     source: hosted
     version: "0.15.6"
   http:
     dependency: "direct main"
@@ -617,20 +665,68 @@ packages:
     source: hosted
     version: "2.1.2"
   path_provider_windows:
     dependency: transitive
     description:
       name: path_provider_windows
       sha256: bd6f00dbd873bfb70d0761682da2b3a2c2fccc2b9e84c495821639601d81afe7
       url: "https://pub.dev"
     source: hosted
     version: "2.3.0"
+  permission_handler:
+    dependency: "direct main"
+    description:
+      name: permission_handler
+      sha256: "59adad729136f01ea9e35a48f5d1395e25cba6cea552249ddbe9cf950f5d7849"
+      url: "https://pub.dev"
+    source: hosted
+    version: "11.4.0"
+  permission_handler_android:
+    dependency: transitive
+    description:
+      name: permission_handler_android
+      sha256: d3971dcdd76182a0c198c096b5db2f0884b0d4196723d21a866fc4cdea057ebc
+      url: "https://pub.dev"
+    source: hosted
+    version: "12.1.0"
+  permission_handler_apple:
+    dependency: transitive
+    description:
+      name: permission_handler_apple
+      sha256: "79dfa1df734798aa3cfdad166d3a3698c206d8813de13516ea1071b5d7e2f420"
+      url: "https://pub.dev"
+    source: hosted
+    version: "9.4.10"
+  permission_handler_html:
+    dependency: transitive
+    description:
+      name: permission_handler_html
+      sha256: "38f000e83355abb3392140f6bc3030660cfaef189e1f87824facb76300b4ff24"
+      url: "https://pub.dev"
+    source: hosted
+    version: "0.1.3+5"
+  permission_handler_platform_interface:
+    dependency: transitive
+    description:
+      name: permission_handler_platform_interface
+      sha256: eb99b295153abce5d683cac8c02e22faab63e50679b937fa1bf67d58bb282878
+      url: "https://pub.dev"
+    source: hosted
+    version: "4.3.0"
+  permission_handler_windows:
+    dependency: transitive
+    description:
+      name: permission_handler_windows
+      sha256: "1a790728016f79a41216d88672dbc5df30e686e811ad4e698bfc51f76ad91f1e"
+      url: "https://pub.dev"
+    source: hosted
+    version: "0.2.1"
   petitparser:
     dependency: transitive
     description:
       name: petitparser
       sha256: c15605cd28af66339f8eb6fbe0e541bfe2d1b72d5825efc6598f3e0a31b9ad27
       url: "https://pub.dev"
     source: hosted
     version: "6.0.2"
   platform:
     dependency: transitive
@@ -666,21 +762,21 @@ packages:
     version: "7.2.2"
   share_plus_platform_interface:
     dependency: transitive
     description:
       name: share_plus_platform_interface
       sha256: "251eb156a8b5fa9ce033747d73535bf53911071f8d3b6f4f0b578505ce0d4496"
       url: "https://pub.dev"
     source: hosted
     version: "3.4.0"
   shared_preferences:
-    dependency: transitive
+    dependency: "direct main"
     description:
       name: shared_preferences
       sha256: "6e8bf70b7fef813df4e9a36f658ac46d107db4b4cfe1048b477d4e453a8159f5"
       url: "https://pub.dev"
     source: hosted
     version: "2.5.3"
   shared_preferences_android:
     dependency: transitive
     description:
       name: shared_preferences_android
diff --git a/pubspec.yaml b/pubspec.yaml
index 4b95c65..d6297a5 100644
--- a/pubspec.yaml
+++ b/pubspec.yaml
@@ -3,33 +3,36 @@ description: "WebIArtisan - Application mobile pour artisans et PME"
 publish_to: 'none'
 version: 2.0.3+58
 
 environment:
   sdk: '>=3.0.0 <4.0.0'
 
 dependencies:
   flutter:
     sdk: flutter
   cupertino_icons: ^1.0.8
-  http: ^1.2.0
+  http: ^1.3.0
   web: ^1.1.0
   webview_flutter: ^4.10.0
   connectivity_plus: ^6.1.0
   url_launcher: ^6.3.1
   share_plus: ^7.2.2
   image_picker: ^1.1.2
   flutter_native_splash: ^2.4.3
   package_info_plus: ^8.1.1
   local_auth: ^2.3.0
   flutter_secure_storage: ^9.2.4
   upgrader: ^10.3.0
   device_info_plus: ^10.1.2
+  shared_preferences: ^2.5.0
+  geolocator: ^13.0.0
+  permission_handler: ^11.0.0
 dev_dependencies:
   flutter_test:
     sdk: flutter
   flutter_lints: ^5.0.0
   flutter_launcher_icons: ^0.14.3
 
 flutter:
   uses-material-design: true
   assets:
     - assets/images/

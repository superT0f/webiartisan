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


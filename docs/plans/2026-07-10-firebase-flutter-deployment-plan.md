# Plan — Déploiement Flutter Android via Firebase

> Contexte : le build local avec Podman/Docker de l'image `ghcr.io/cirruslabs/flutter:3.41.6` échoue à cause de la lenteur du réseau. On explore Firebase comme alternative pour distribuer l'app Android sans dépendre de GitLab CI + Fastlane.

## Objectifs

1. Distribuer l'app WebIArtisan Android aux testeurs sans passer par le build local lourd.
2. Garder la possibilité de publier sur Google Play Console en production.
3. Centraliser les secrets (keystore, service account) de manière sécurisée.

## Options à explorer

### Option A : Firebase App Distribution (recommandée en interne)

- Build de l'AAB/APK côté GitLab CI ou GitHub Actions avec l'image Flutter officielle.
- Upload automatique sur Firebase App Distribution via `firebase appdistribution:distribute`.
- Les testeurs reçoivent un email d'invitation et peuvent installer l'app.
- Avantage : pas besoin de Play Console pour les tests internes.
- Inconvénient : ne remplace pas Google Play pour la production publique.

### Option B : GitLab CI + Fastlane (recommandée pour la production)

- Conserver le pipeline existant dans `.gitlab-ci.yml`.
- Configurer les variables CI/CD dans GitLab avec les valeurs de `vars.md`.
- Lancer manuellement `gplay:build` puis `gplay:internal`.
- Avantage : publication directe sur Play Store Internal track.
- Inconvénient : nécessite les secrets GitLab et une image Flutter lourde à télécharger.

### Option C : Firebase Cloud Build / GitHub Actions

- Utiliser GitHub Actions avec `subosito/flutter-action` pour builder.
- Publier sur Firebase App Distribution ou Google Play via `maierj/fastlane-action`.
- Avantage : les runners cloud ont un réseau fiable.
- Inconvénient : migration du repo ou ajout d'un miroir GitHub.

## Étapes concrètes

1. **Keystore**
   - Valider le keystore `android/app/upload-keystore.jks` créé le 2026-07-10.
   - Empreinte SHA-256 enregistrée sur Google Play : `11:79:16:56:32:BE:45:51:F4:16:C1:09:CB:0B:84:94:5C:FC:A6:01:A4:3F:40:CF:01:D2:EE:7F:36:36:D6:12`.
   - Sauvegarder `key.properties` et `upload-keystore.jks` hors du repo.

2. **Version**
   - `pubspec.yaml` est passé à `2.0.1+54` dans `webiartisan-flutter-app`.

3. **Firebase App Distribution**
   - Créer un projet Firebase `webiartisan` si non existant.
   - Ajouter l'app Android `tech.prigent.webiartisan`.
   - Télécharger `google-services.json` et le placer dans `android/app/`.
   - Installer Firebase CLI (`npm install -g firebase-tools`).
   - Créer un service account Firebase pour CI avec rôle `Firebase App Distribution Admin`.
   - Ajouter la commande dans le CI :
     ```bash
     firebase appdistribution:distribute build/app/outputs/flutter-apk/app-release.apk \
       --app <firebase_app_id> \
       --groups testeurs \
       --token "$FIREBASE_TOKEN"
     ```

4. **GitLab CI (optionnel mais recommandé pour Play Store)**
   - Variables à configurer dans GitLab :
     - `KEYSTORE_BASE64` (base64 de `upload-keystore.jks`)
     - `KEYSTORE_PASSWORD`, `KEY_PASSWORD`, `KEY_ALIAS`
     - `GOOGLE_PLAY_SERVICE_ACCOUNT_JSON` (fichier JSON complet)
   - Corriger `.gitlab-ci.yml` si besoin : le keystore est attendu dans `android/app/upload-keystore.jks`.

5. **Test**
   - Lancer un build `gplay:build` manuel depuis GitLab.
   - Vérifier que l'AAB est signé avec le bon keystore.
   - Pour Firebase : vérifier que les testeurs reçoivent l'invitation.

## Notes

- Le fichier `vars.md` contient les secrets legacy (`SIGNING_KEYSTORE_B64`, `GOOGLE_PLAY_SERVICE_ACCOUNT_JSON`, etc.) mais les mots de passe n'y figurent pas. Il doit rester gitignoré.
- Le build local est bloqué par le téléchargement de l'image Flutter (> 3 Go). Privilégier un runner cloud.

## Prochaine action attendue

Décider entre Option A (Firebase App Distribution) et Option B (GitLab CI + Fastlane), puis configurer les secrets correspondants.

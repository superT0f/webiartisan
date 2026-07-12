# Task 1: Ajouter les dépendances Flutter — Report

## What was implemented

- Modified `/home/tof/code/webiartisan-flutter-app/pubspec.yaml` to add the requested direct dependencies:
  - `geolocator: ^13.0.0`
  - `permission_handler: ^11.0.0`
- Also added `shared_preferences: ^2.5.0` and bumped `http` from `^1.2.0` to `^1.3.0`, matching the dependency block shown in the task brief.
- Existing dependencies were preserved (no removals).
- Regenerated `pubspec.lock` by running `flutter pub get`.

## What was tested and results

- Ran `flutter pub get` in `/home/tof/code/webiartisan-flutter-app`.
- Result: success, no conflicts.
  - `geolocator 13.0.4` installed.
  - `permission_handler 11.4.0` installed.
  - `shared_preferences 2.5.3` promoted from transitive to direct dependency.
  - `http` constraint updated to `^1.3.0` and resolved correctly.

## Files changed

- `/home/tof/code/webiartisan-flutter-app/pubspec.yaml`
- `/home/tof/code/webiartisan-flutter-app/pubspec.lock`

## Self-review findings

- The `pubspec.yaml` diff is minimal and only adds the requested/new dependencies plus the `http` bump.
- `flutter pub get` completed with exit code 0.
- Commit includes both `pubspec.yaml` and `pubspec.lock` as specified.
- No application code was changed; only dependency declarations.

## Issues or concerns

- None. The dependency resolution succeeded without conflicts.

# O&G Transport — Driver App (Flutter)

Android / iOS driver app for section O of the functional requirement checklist. The backend is this Laravel
project (`/api/v1/driver/*`, Sanctum tokens).

## Run locally

```bash
# backend (repo root)
php artisan migrate
php artisan db:seed --class=DriverAppDemoSeeder   # fresh Draft trip for driver.kl@og.local (re-run for another)
php artisan serve --host=0.0.0.0 --port=8000

# app
cd driver_app
flutter pub get
flutter run                                         # pick an emulator / simulator / phone
```

Log in with `driver.kl@og.local` / `password`. The server URL is under **Server settings** on the login screen.
Defaults: `http://10.0.2.2:8000` (Android emulator) and `http://127.0.0.1:8000` (iOS simulator). On a real phone,
use the computer's LAN address (for example `http://192.168.1.20:8000`) or the production URL.

Tests: `flutter test`. End-to-end against a running backend:
`flutter test integration_test/app_test.dart -d <device>`.

## Android release signing

Release builds are signed with the upload key `android/app/upload-keystore.jks` (alias `upload`). Its passwords are
in `android/key.properties`. Neither file is committed (see `android/.gitignore`), so keep both in a password manager.
If the key is lost, Play App Signing needs a key reset before the next update can be published. If
`key.properties` is missing, release builds fall back to the debug key.

```bash
flutter build appbundle --release   # build/app/outputs/bundle/release/app-release.aab for Google Play
```

## Checklist mapping (section O)

| Items | Where |
| --- | --- |
| 1–5 Login & check-in | `login_screen.dart`, check-in card in `home_screen.dart`; API `login`, `check-in` (`DriverCheckIn` validates the lorry, its operating branch and active status, records time and GPS, and moves the job sheet to In Transit) |
| 6–15 My Job Sheet | `home_screen.dart` (route groups, sequence, kind badges), `task_detail_screen.dart`; payload `App\Support\DriverTaskPayload` |
| 16–21 Consignment validation | `scan_validation_screen.dart`; API `job-sheets/{id}/scan-check`, `job-sheets/{id}/scan-validation` (`ValidateJobSheetScans`, table `driver_scan_validations`, shown on the admin Job Sheet view) |
| 22–33 Delivery execution | `deliver_screen.dart`, pickup photos in `proof_screen.dart`; blur check in `core/blur.dart`; API `deliveries/{id}/complete`, `deliveries/{id}/pickup` |
| 34–38 Additional task | `proof_screen.dart` (pickup / handover); API `deliveries/{id}/pickup`, `deliveries/{id}/handover` (marks the subsheet leg done and the PSI transaction `eligible`) |
| 39–45 Break-Bulk | `break_bulk_screen.dart`, `BreakBulksScreen`; API `deliveries/{id}/break-bulk`, `break-bulks` |
| 46–54 Failed delivery | `fail_screen.dart`, `FailuresScreen`; API `deliveries/{id}/fail`, `failed-deliveries`; reasons in `FailedDelivery::REASONS` |
| 55–61 Offline | `state/sync_service.dart` (SQLite outbox, files kept in app storage, auto sync on reconnect / resume / every 30 s), `sync_screen.dart`; every record carries a `client_uuid` that the backend de-duplicates |

## How offline sync works

Every record is written to the on-phone outbox first, together with its photos and signature. When a connection
is available, each file is uploaded to `uploads` using its own uuid, so a retried upload overwrites the same file.
The record is then posted with the server paths and its `client_uuid`. If that uuid was already stored, the
backend returns the existing record, so a retry after a lost response never creates a duplicate.

- **Network or server errors:** the record stays *pending* and is retried in order.
- **Validation errors:** the record is marked *failed* and shows the server's message. The driver can retry or
  discard it from **Sync status**.
- **Check-in:** online only, because the server validates the lorry and branch.

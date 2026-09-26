# A & J OASIS — Mobile (Flutter, Android)

Tenant-facing mobile app matching the **C-series wireframes**
(`AJ_Oasis_Wireframe_Exports/C1`–`C10`). Public browsing, booking + Xendit
checkout, and the authenticated tenant portal (My Rental, Payments, Transfer
requests, Maintenance, Notifications, Profile).

## Status: wired to the real Laravel API

Every screen now talks to real endpoints under `routes/api.php` (Sanctum
bearer-token auth) instead of mock data. See:

- `app/Http/Controllers/Api/` — the API controllers. They don't duplicate
  business logic — they call the same `BookingService`, `XenditService`,
  `PaymentCompletionService`, `NotificationService`, and Policies the web app
  uses, so a booking/payment/lease/notification created from mobile behaves
  identically to one created from the web portal.
- `app/Http/Controllers/Api/Concerns/HandlesPaymentGateway.php` — shared
  pay/poll logic (booking upfront payment and rent payments both use this).
- `app/Http/Resources/` — JSON shaping.
- `mobile/lib/services/api_client.dart` — the Flutter-side HTTP client
  (Bearer token from `flutter_secure_storage`, JSON in/out, error mapping to
  `ApiException`).
- `mobile/lib/state/app_state.dart` — same public shape as before, but every
  method now makes a real network call. Screens still only ever go through
  `AppState`; the bodies (and screens' `onPressed` handlers, which had to
  become `async` with loading/error states) are what changed.

### Reaching the backend from the phone/emulator

`ApiClient.baseUrl` defaults to `http://127.0.0.1:8000/api` and can be
overridden per build:

```bash
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api   # Android emulator
flutter run --dart-define=API_BASE_URL=http://192.168.x.x:8000/api # phone over Wi-Fi
```

For a physical device over **USB** (what this project was tested with — the
dev laptop can't sustain the emulator, see the auto-memory notes), the
default `127.0.0.1` works as-is via `adb reverse`:

```bash
php artisan serve                # backend, from the repo root
adb reverse tcp:8000 tcp:8000    # tunnel the phone's localhost:8000 -> the laptop's
flutter run -d <device-id>
```

`adb reverse` needs to be re-run any time the phone is unplugged/replugged or
`adb` is restarted — it doesn't persist.

### XENDIT_FAKE_MODE

Real Xendit is currently blocked (see the top-level `CLAUDE.md` — a
persistent `403 REQUEST_FORBIDDEN_ERROR` on the account, unrelated to this
app). With `XENDIT_FAKE_MODE=true`, both the booking-payment and rent-payment
API endpoints (`POST /api/bookings/{id}/pay`, `POST /api/payments/{id}/pay`)
complete immediately, matching the web app's fake-mode behavior — no invoice
URL, no redirect. With it `false`, those endpoints return
`{"status":"redirect","invoice_url":...}` and the Flutter screens open that
URL via `url_launcher`, then offer an "I've paid — check status" button that
calls `POST .../check-status` (same polling approach the web `PaymentController`
uses, since Xendit's webhook can't reach a local dev server).

**This was flipped to `true` in `.env` during this build to verify the
payment flow end-to-end** (it was `false`/real-mode before). Flip it back to
`false` once real Xendit is confirmed working, or leave it on for continued
mobile dev/demo purposes — no code changes needed either way.

## Structure

- `lib/screens/` — one file per wireframe screen (`landing_screen.dart` = C1,
  `auth_screen.dart` = C2, etc.)
- `lib/screens/root_shell.dart` — owns the persistent bottom nav
  (Home/Rental/Pay/Alerts/Profile) and the login-gate redirect for logged-out
  users tapping a protected tab.
- `lib/screens/rental_hub_screen.dart` — the wireframes don't show how a
  tenant moves between My Rental (C5), Request Transfer (C7), and Maintenance
  (C8), which all share the "Rental" tab. A small segmented control was added
  here purely for navigation; the three screen bodies are otherwise a direct
  match of their wireframes.
- `lib/widgets/wireframe_placeholder.dart` — reproduces the wireframes'
  dashed-border "X" placeholder boxes (hero art, photo gallery, room photos)
  rather than inventing photography the design docs never specified. (Room
  photos *are* now available from the API — `Room.images` — this just hasn't
  been swapped in for the placeholder boxes yet, to keep this pass scoped to
  data wiring rather than a visual redesign.)
- `lib/theme.dart` — brand palette lifted from the web app
  (`resources/views/home.blade.php`: `--oasis-green`, `--oasis-gold`,
  `--oasis-sand`) so mobile matches the existing identity. The wireframes
  themselves are structural/monochrome only.
- `lib/state/app_state.dart` — single `ChangeNotifier` source of truth; no
  external state-management package. `bootstrap()` restores a saved session
  (stored Sanctum token) on app start.

## Design decisions / deviations from the wireframes and the web app

- Callout numbers/arrows (①②③) in the wireframes are reviewer annotations,
  not UI — not reproduced.
- C3's "Email · Phone" and "Password · Confirm" combined-looking rows are
  built as separate fields (4 real inputs) since a working form needs them
  distinct.
- Locked business rule from `CLAUDE.md` (move-in date must be within 7 days
  of booking) is enforced on C3's date picker, matching the API's own
  validation (`BookingService::setMoveInDate` / the `store` validation rule).
- **The mobile booking flow creates the account and the booking together**
  (`POST /api/rooms/{room}/book`), matching the wireframe's single combined
  form. The web app instead requires signing up first, then booking
  separately (`tenant.bookings.create/store` sit behind `auth+role:tenant`).
  The new endpoint reuses the exact same `User::create()` validation as
  `Api\AuthController::register` and the exact same `BookingService` the web
  app uses — it's a new entry point, not new business logic.
- Added a `reason` column to `room_transfers` (nullable text, migration
  `2026_09_07_071529_add_reason_to_room_transfers_table`) since the C7
  wireframe explicitly asks for a "reason + date" field that didn't exist in
  the schema. The web app's transfer flow doesn't collect this; only the
  mobile API endpoint (`POST /api/room-transfers`) does.
- Profile's "Change password" field is present (matching the wireframe) but
  **not yet wired** — the real `ProfileController::update` requires
  `current_password` when changing password, which the wireframe's
  single-field design doesn't collect. Needs either an extra field or a
  separate "change password" flow as a follow-up.
- Maintenance's and Profile's "attach/upload photo" are still visual-only —
  wiring real photo upload needs `image_picker` + multipart form requests,
  which is a distinct addition from the API/data wiring this pass covered.

## Running

Android is the only shipped target (per project scope — iOS excluded).

```bash
flutter run -d <device-id>
```

A `web` platform folder is also present, added only as a local preview
convenience (no Android emulator was available in the dev sandbox at build
time) — not part of the shipped app.

# Mobile Google and Apple integration

The Flutter app in `/Users/ge/Developer/Tedar-App-UI-UX` now uses the existing
mobile API, with native Google sign-in on Android/iOS and native Apple sign-in
on iOS. The existing backend endpoints required no runtime changes.

Implemented in the app:

- Provider buttons on login and registration follow `/auth/social/providers`
  and the current platform. Student/parent selection precedes registration;
  teacher registration remains the document-based application.
- Native identity tokens go to `/auth/social`; sessions use the same secure
  token storage and role routing as password login.
- Apple receives the SHA-256 nonce; the API receives the raw nonce. Its one-time
  name is saved temporarily in secure storage until an exchange succeeds.
- Incomplete profiles open an optional completion screen with “Later”. A
  persistent completion row in settings uses `/auth/me` metadata on restore.
- Every role has linked-account controls and first-password support. An invalid
  provider token does not refresh or destroy the existing app session. It gets
  one fresh native-token retry. Last-method unlink remains guarded by the API.
- Logout/unlink clear Google SDK state. Device families receive a stable
  installation ID and the installed app version.
- Google’s current public iOS client ID is registered as a URL scheme. Apple’s
  capability and entitlements are enabled for `com.taqdar.taqdar`.

## Verification

`php tests/social_mobile_test.php` runs 20 offline checks against the real PHP
identity verifier: signed RSA tokens, tampering, algorithm, issuer, audience,
expiry, nonce, verified email, relay email and Apple’s name. It uses a temporary
in-memory settings fixture and never contacts providers or the production DB.

The app’s regression tests use its real API sources, repositories and
interceptors with a fake transport. They cover cancellation, retry limits,
session preservation, linking conflicts, profile validation, platform buttons,
first passwords, and the real Apple plugin channel’s nonce/name handling.

Android debug and unsigned iOS native builds were checked locally. The initial
Flutter iOS build encountered a stale Xcode dependency scan; an isolated build
with `xcodebuild -derivedDataPath /private/tmp/taqdar-social-ios-derived` succeeded.

## Device checks before release

1. Apple Developer: enable Sign in with Apple for `com.taqdar.taqdar`, and use
   a provisioning profile containing that capability. Test on a signed iPhone.
2. Google Cloud: confirm `com.taqdar.taqdar` and SHA-1 fingerprints for debug,
   release, and Google Play App Signing. The Android project currently signs
   its release build with its debug key; production signing still needs setup.
3. Test a fresh Google/Apple account, an existing password account with the
   same email, repeat sign-in, parent registration, an approved teacher login,
   and Apple’s first authorization/hidden email on real devices.
4. Verify completion “Later”, invalid/valid phone and age, setting a first
   password, linking a used account, unlinking, logout, and account deletion.

Client IDs are public configuration, not secrets. The Google web client is read
at runtime as `serverClientId`; the iOS URL scheme is build-time configuration.
Changing the iOS client ID requires updating both the URL scheme and the app’s
matching guard in `native_social_identity.dart`. The app rejects a mismatch
before invoking the SDK.

Public references: https://taqdaredu.com/api/docs,
https://pub.dev/packages/google_sign_in,
https://pub.dev/packages/sign_in_with_apple,
https://developers.google.com/identity/branding-guidelines.

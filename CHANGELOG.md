# Changelog

All notable changes to `padosoft/laravel-rebel-channel-twilio` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and
[Semantic Versioning](https://semver.org/).

## [Unreleased]

## [0.1.0] - 2026-06-03

### Added
- **`TwilioVerifyProvider`**: a Rebel Channels `VerificationProvider` backed by Twilio
  Verify (SMS / WhatsApp / voice). Maps Rebel channels to Twilio (voice → `call`),
  maps Verify statuses explicitly, and converts any SDK/transport error into a clean
  `provider_error` so the router can fall back.
- **Gateway seam** (`TwilioVerifyGateway` + `RestTwilioVerifyGateway`) over the Twilio
  SDK, with a `FakeTwilioVerifyGateway` for offline tests.
- **Auto-registration** into the Channels registry when the three `TWILIO_*` credentials
  are present (no unauthenticated Twilio client is ever constructed otherwise).
- **Live test suite** (`tests/Live`, opt-in via `REBEL_TWILIO_LIVE=1`) that hits the real
  Twilio Verify API; self-skips when credentials are absent.
- Config file, CI matrix (PHP 8.3/8.4/8.5 × Laravel 12/13), Pest suite, PHPStan level max, Pint.

[Unreleased]: https://github.com/padosoft/laravel-rebel-channel-twilio/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/padosoft/laravel-rebel-channel-twilio/releases/tag/v0.1.0

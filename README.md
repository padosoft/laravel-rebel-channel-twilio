# Laravel Rebel — Twilio Channel

> **Send phone verifications through Twilio Verify, the Rebel way.** This package plugs [Twilio Verify](https://www.twilio.com/docs/verify) (SMS / WhatsApp / voice) into [`laravel-rebel-channels`](https://github.com/padosoft/laravel-rebel-channels) as a `VerificationProvider` — so you get Twilio's global delivery *plus* Rebel's fraud guard, rate limiting, fallback and audit on top. Part of the `padosoft/laravel-rebel-*` suite.

<p align="center">
  <img src="resources/screenshoots/Laravel-Rebel-banner.png" alt="Laravel Rebel" width="100%">
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-12%20%7C%2013-FF2D20?style=flat-square&logo=laravel&logoColor=white" alt="Laravel 12|13">
  <img src="https://img.shields.io/badge/PHP-8.3%20%7C%208.4%20%7C%208.5-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP 8.3+">
  <img src="https://img.shields.io/badge/PHPStan-max-2A6FDB?style=flat-square" alt="PHPStan max">
  <img src="https://img.shields.io/badge/tests-Pest%204-22C55E?style=flat-square" alt="Pest 4">
  <img src="https://img.shields.io/badge/Twilio-Verify-F22F46?style=flat-square&logo=twilio&logoColor=white" alt="Twilio Verify">
  <img src="https://img.shields.io/badge/license-MIT-blue?style=flat-square" alt="MIT">
</p>

---

## Table of contents

- [What it is](#what-it-is)
- [Quick glossary](#quick-glossary)
- [Why this package](#why-this-package)
- [Rebel + Twilio vs the alternatives](#rebel--twilio-vs-the-alternatives)
- [Twilio portal setup (step by step)](#twilio-portal-setup-step-by-step)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [Delivery receipts (status webhook)](#delivery-receipts-status-webhook)
- [Live tests against the real API](#live-tests-against-the-real-api)
- [`.env.example`](#envexample)
- [Security notes](#security-notes)
- [Testing & License](#testing--license)

---

## What it is

A thin, well-tested **Twilio Verify** provider for Rebel Channels. You don't call it
directly — you call the Channels `VerificationRouter`, and it routes through this provider
(with Rebel's bot gate, IRSF defences, per-number rate limit, fallback and audit around it).

A small **gateway seam** (`TwilioVerifyGateway`) wraps the Twilio SDK, so the whole thing is
unit-testable offline and has a real **live** test-suite for the actual API.

Depends on [`padosoft/laravel-rebel-core`](https://github.com/padosoft/laravel-rebel-core)
and [`padosoft/laravel-rebel-channels`](https://github.com/padosoft/laravel-rebel-channels).

---

## Quick glossary

| Term | In plain words |
|---|---|
| **Twilio Verify** | A Twilio product that sends and checks one-time codes for you — you never store or generate the OTP. |
| **Verify Service** | A Verify configuration (sender IDs, code length, channels). Identified by a SID starting with `VA`. |
| **Account SID / Auth Token** | Your Twilio account credentials (the SID starts with `AC`). |
| **Channel** | `sms`, `whatsapp` or `voice` (Twilio calls voice `call`). |
| **Verification SID** | The handle (`VE...`) for an in-flight verification. |

---

## Why this package

| ★ | What | In short |
|---|---|---|
| ★★★ | **Twilio Verify, fully wrapped** | Start + check codes over SMS/WhatsApp/voice; you never handle the OTP yourself. |
| ★★★ | **Rebel guarantees for free** | Inherits the Channels fraud guard (IRSF), rate limit, fallback and HMAC'd audit. |
| ★★ | **Never throws out** | Any SDK/transport error becomes a clean `provider_error`, so the router can fall back to another provider. |
| ★★ | **Offline-testable** | A gateway seam + fake means your tests don't hit Twilio; a separate live suite does. |
| ★★ | **Safe by default** | No credentials → nothing registers, and no unauthenticated Twilio client is ever built. |
| ★ | **Explicit status mapping** | Twilio Verify statuses are mapped deliberately (an unexpected status is a failure, not a fake "pending"). |

---

## Rebel + Twilio vs the alternatives

Sending an OTP with Twilio, three ways:

| Capability | **Rebel + this package** | Shopify | Twilio Verify SDK (direct) | Raw Twilio SMS + your own OTP |
|---|:---:|:---:|:---:|:---:|
| Send/check a code via Twilio | ✅ | ❌ | ✅ | ➖ (you build OTP logic) |
| You never store/generate the OTP | ✅ | ✅ | ✅ | ❌ |
| Anti toll-fraud / IRSF guard | ✅ | ❌ | ❌ | ❌ |
| Per-number rate limit + bot gate | ✅ | ➖ | ❌ | ❌ |
| **Provider fallback** to another vendor | ✅ | ❌ | ❌ | ❌ |
| Signed, phone-bound reference (anti replay) | ✅ | ❌ | ❌ | ❌ |
| Unified audit trail (number HMAC'd) | ✅ | ❌ | ❌ | ❌ |
| Graceful failure → router fallback | ✅ | ❌ | ❌ | ❌ |

> Legend: ✅ built-in · ➖ partial / hosted-only · ❌ not available. Twilio Verify is excellent at delivery;
> this package keeps all of that and adds the Rebel fraud/routing/audit layer around it.
> Shopify is a closed, hosted commerce platform: it sends its own customer OTPs but lets you
> neither pick Twilio as the sender, self-host it, fall back across vendors, nor configure
> its fraud controls — a black box, not a developer-facing verification library.

---

## Twilio portal setup (step by step)

1. **Create a Twilio account** at [twilio.com/try-twilio](https://www.twilio.com/try-twilio).
   The **free trial** gives you a small credit and a trial number — note that trial accounts
   can only send to **verified** caller IDs (add your test phone under *Phone Numbers →
   Verified Caller IDs*).
2. **Grab your credentials**: on the [Console](https://console.twilio.com) home page copy your
   **Account SID** (`AC...`) and **Auth Token**.
3. **Create a Verify Service**: go to **Verify → Services → Create new**, give it a friendly
   name (e.g. "MyApp Login"), choose the channels (SMS / WhatsApp / Voice), and copy the
   **Service SID** (`VA...`).
4. *(WhatsApp only)* enable the WhatsApp channel on the Verify Service and complete Twilio's
   WhatsApp sender onboarding.
5. Put the three values in your `.env` (see below). Done — the provider auto-registers.

> **Pricing:** Twilio Verify is billed per successful verification + the channel cost; the
> free trial credit is enough to test end-to-end. Always keep the Rebel **geo allowlist**
> (in `laravel-rebel-channels`) tight to avoid IRSF charges.

---

## Installation

```bash
composer require padosoft/laravel-rebel-channel-twilio
php artisan vendor:publish --tag="rebel-channel-twilio-config"
```

Add your credentials to `.env`:

```dotenv
TWILIO_ACCOUNT_SID=ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
TWILIO_AUTH_TOKEN=your_auth_token
TWILIO_VERIFY_SERVICE_SID=VAxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

That's it — the provider registers itself into the Channels router under the key `twilio`.

---

## Configuration

File `config/rebel-channel-twilio.php`:

| Key | Default | What it does |
|---|---|---|
| `account_sid` | `env(TWILIO_ACCOUNT_SID)` | Twilio Account SID (`AC...`). |
| `auth_token` | `env(TWILIO_AUTH_TOKEN)` | Twilio Auth Token. |
| `verify_service_sid` | `env(TWILIO_VERIFY_SERVICE_SID)` | Verify Service SID (`VA...`). |
| `channels` | `['sms','whatsapp','voice']` | Which Rebel channels this provider may handle. |
| `register_provider` | `true` | Auto-register into the Channels registry (when credentials exist). |
| `webhook.enabled` | `true` | Register the delivery-status callback endpoint. |
| `webhook.validate_signature` | `true` | Validate `X-Twilio-Signature` on the webhook. |
| `webhook.path` | `rebel/twilio/status` | The webhook route path. |

---

## Usage

You typically don't touch this package directly — you use the Channels router:

```php
use Padosoft\Rebel\Channels\Enums\Channel;
use Padosoft\Rebel\Channels\Routing\VerificationRouter;
use Padosoft\Rebel\Core\Context\SecurityContext;
use Padosoft\Rebel\Core\Identifiers\PhoneIdentifier;

$router = app(VerificationRouter::class);

// Send a code (Twilio Verify delivers it)
$start = $router->start(PhoneIdentifier::from('+39 333 1234567'), Channel::Sms, SecurityContext::fromRequest($request));

// Check what the user typed
$result = $router->check(PhoneIdentifier::from('+39 333 1234567'), $request->string('code'), $reference, SecurityContext::fromRequest($request));
if ($result->approved()) {
    // verified!
}
```

To force Twilio specifically, set it first in the Channels fallback order:

```php
// config/rebel-channels.php
'providers' => ['twilio'],
```

---

## Delivery receipts (status webhook)

Twilio knows whether a message was actually **delivered**, **failed**, and exactly how much it
**cost** — but only it knows, unless you let it call you back. This package ships a delivery-status
webhook that turns those callbacks into Rebel audit events, so the admin panel's **Channel
Performance** can show real delivered / failed rates and spend per channel.

**What it records.** On each callback the endpoint writes one audit event (recipient number stored
only as a keyed HMAC, never in clear):

| Twilio status | Audit `event_type` |
|---|---|
| `delivered` | `channel.verification.delivered` |
| `undelivered`, `failed` | `channel.verification.undelivered` |
| `queued`, `sent`, `accepted`, … | `channel.verification.dispatched` |

Each event carries `channel` (from the payload, default `sms`), `provider: 'twilio'`, the HMAC'd
recipient, and a `metadata` object:

```json
{
  "message_status": "delivered",
  "price": 0.0075,
  "price_unit": "USD",
  "message_sid": "SMxxxxxxxx",
  "error_code": null
}
```

(Twilio quotes `Price` as a negative debit string; we store the **absolute** value so spend sums
cleanly. `price`/`error_code` are `null` when absent.)

**Set the StatusCallback URL.** In the Twilio console, point the status callback at your app:

```
https://<your-host>/rebel/twilio/status
```

- **Messaging**: set *StatusCallback* on the Messaging Service (or per message).
- **Verify**: set the status webhook on the Verify Service.

The endpoint is enabled by default (`REBEL_TWILIO_WEBHOOK=true`); set it to `false` to drop the
route. The path is configurable via `webhook.path`.

**Signature validation.** The route has **no auth middleware** — Twilio posts server-to-server.
Instead, when `webhook.validate_signature` is true (default), every request must carry a valid
`X-Twilio-Signature` (HMAC-SHA1 of the full URL + POST params, keyed with your Auth Token); a
missing or forged signature is rejected with **403** and nothing is recorded. A malformed or empty
payload is acknowledged with **204** and recorded as nothing, so a junk callback never 500s.

```dotenv
REBEL_TWILIO_WEBHOOK=true            # register the /rebel/twilio/status route
REBEL_TWILIO_WEBHOOK_VALIDATE=true   # verify X-Twilio-Signature
REBEL_TWILIO_WEBHOOK_PATH=rebel/twilio/status
```

> Build now, wire live later: the endpoint and its audit events exist today, so you can simulate
> Twilio callbacks (the test suite does exactly that) and the admin aggregates real
> delivered/cost data as soon as you set the StatusCallback URL in Twilio.

---

## Live tests against the real API

The offline suite uses a fake gateway. To exercise the **real** Twilio Verify API
(`tests/Live`), opt in explicitly — it **sends a real message**:

```bash
# .env (or shell env)
REBEL_TWILIO_LIVE=1
TWILIO_TEST_PHONE=+39XXXXXXXXXX   # a verified number on trial accounts

vendor/bin/pest --group=live
```

Without `REBEL_TWILIO_LIVE=1` or with any credential missing, the live tests **self-skip**,
so `composer test` and external PRs never trigger a send. In CI, supply the values as
**secrets** and set `REBEL_TWILIO_LIVE=1` on a dedicated job.

---

## `.env.example`

```dotenv
TWILIO_ACCOUNT_SID=ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
TWILIO_AUTH_TOKEN=your_auth_token
TWILIO_VERIFY_SERVICE_SID=VAxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
REBEL_TWILIO_REGISTER=true

REBEL_TWILIO_WEBHOOK=true
REBEL_TWILIO_WEBHOOK_VALIDATE=true
REBEL_TWILIO_WEBHOOK_PATH=rebel/twilio/status

# Live tests (opt-in: SENDS A REAL MESSAGE)
REBEL_TWILIO_LIVE=0
TWILIO_TEST_PHONE=+391234567890
```

---

## Security notes

- **No unauthenticated client**: the Twilio client is only constructed when all three
  credentials are present.
- **No exception leakage**: SDK/transport errors are caught and returned as a generic
  `provider_error` — Twilio internals never bubble up to your app or logs.
- **Explicit status mapping**: only Twilio's `pending` is treated as a live challenge; any
  unexpected status fails closed.
- **Keep IRSF defences on**: pair this with the Channels geo allowlist / per-prefix cap to
  avoid premium-rate fraud charges.

---

## Testing & License

```bash
composer test      # Pest (provider + Channels integration; live suite self-skips)
composer phpstan   # static analysis, level max
composer pint      # code style
```

**License:** MIT — see [LICENSE](LICENSE). Part of the [`padosoft/laravel-rebel`](https://github.com/padosoft) suite.

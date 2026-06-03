<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Twilio credentials
    |--------------------------------------------------------------------------
    | From the Twilio Console. The Verify Service SID (starts with "VA...") is created
    | under Verify → Services. All three are required for the provider to register.
    */
    'account_sid' => env('TWILIO_ACCOUNT_SID'),
    'auth_token' => env('TWILIO_AUTH_TOKEN'),
    'verify_service_sid' => env('TWILIO_VERIFY_SERVICE_SID'),

    /*
    |--------------------------------------------------------------------------
    | Channels & registration
    |--------------------------------------------------------------------------
    | Which Rebel channels this provider may handle, and whether to auto-register it
    | into the Rebel Channels provider registry on boot (when credentials are present).
    */
    'channels' => ['sms', 'whatsapp', 'voice'],
    'register_provider' => env('REBEL_TWILIO_REGISTER', true),

    /*
    |--------------------------------------------------------------------------
    | Delivery status webhook
    |--------------------------------------------------------------------------
    | A POST endpoint that receives Twilio delivery-status callbacks and records
    | a Rebel audit event (delivered / undelivered / dispatched + cost), so the
    | admin panel's Channel Performance shows real numbers.
    |
    | Point Twilio at it by setting the StatusCallback URL in the Twilio console
    | (or on the Verify service / message) to:
    |
    |     https://<your-host>/rebel/twilio/status
    |
    | The route carries NO auth middleware (Twilio posts server-to-server);
    | instead, when `validate_signature` is true the X-Twilio-Signature header is
    | verified against your auth token + the full URL + POST params before the
    | callback is recorded. Disable `enabled` to drop the route entirely.
    */
    'webhook' => [
        'enabled' => env('REBEL_TWILIO_WEBHOOK', true),
        'validate_signature' => env('REBEL_TWILIO_WEBHOOK_VALIDATE', true),
        'path' => env('REBEL_TWILIO_WEBHOOK_PATH', 'rebel/twilio/status'),
    ],

];

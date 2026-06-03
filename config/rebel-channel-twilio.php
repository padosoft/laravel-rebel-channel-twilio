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
    | Optional endpoint for Twilio status callbacks. When enabled, the request's
    | X-Twilio-Signature is validated against your auth token before processing.
    */
    'webhook' => [
        'enabled' => env('REBEL_TWILIO_WEBHOOK', false),
        'validate_signature' => env('REBEL_TWILIO_WEBHOOK_VALIDATE', true),
        'path' => env('REBEL_TWILIO_WEBHOOK_PATH', 'rebel/twilio/status'),
    ],

];

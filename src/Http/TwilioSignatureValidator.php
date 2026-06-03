<?php

declare(strict_types=1);

namespace Padosoft\Rebel\Channel\Twilio\Http;

use Illuminate\Http\Request;
use Twilio\Security\RequestValidator;

/**
 * Validates the `X-Twilio-Signature` header that Twilio attaches to every
 * status callback.
 *
 * Twilio signs each request by HMAC-SHA1'ing the full request URL with the POST
 * parameters appended (sorted by key), keyed with your account Auth Token, then
 * base64-encoding the result. We recompute that here and compare it against the
 * header. Without the Auth Token the signature cannot be forged, so a matching
 * signature proves the callback really came from Twilio.
 *
 * Wraps the official {@see RequestValidator} from the Twilio SDK.
 */
final class TwilioSignatureValidator
{
    public function __construct(private readonly string $authToken) {}

    /**
     * @return bool true when the request carries a valid Twilio signature
     */
    public function isValid(Request $request): bool
    {
        if ($this->authToken === '') {
            return false;
        }

        $signature = $request->header('X-Twilio-Signature');
        if (! is_string($signature) || $signature === '') {
            return false;
        }

        return (new RequestValidator($this->authToken))->validate(
            $signature,
            $request->fullUrl(),
            $this->postParams($request),
        );
    }

    /**
     * Twilio signs the application/x-www-form-urlencoded POST body. We pass that
     * exact set of fields (string keys/values only) to the validator.
     *
     * @return array<string, string>
     */
    private function postParams(Request $request): array
    {
        $params = [];

        /** @var mixed $value */
        foreach ($request->post() as $key => $value) {
            if (is_string($key) && is_scalar($value)) {
                $params[$key] = (string) $value;
            }
        }

        return $params;
    }
}

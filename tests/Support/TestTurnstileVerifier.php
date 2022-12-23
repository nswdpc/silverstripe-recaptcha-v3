<?php

namespace NSWDPC\SpamProtection\Tests\Support;

use NSWDPC\SpamProtection\TokenResponse;
use NSWDPC\SpamProtection\TurnstileTokenResponse;
use NSWDPC\SpamProtection\TurnstileVerifier;
use NSWDPC\SpamProtection\Verifier;

/**
 * Test verifier for Turnstile testing
 * @see https://developers.cloudflare.com/turnstile/frequently-asked-questions/#are-there-sitekeys-and-secret-keys-that-can-be-used-for-testing

 */
class TestTurnstileVerifier extends TurnstileVerifier {

    /**
     * @var bool
     */
    protected $responseValue = true;

    /**
     * @return bool
     */
    public function setIsHuman(bool $is) {
        $this->responseValue = $is;
        return $this;
    }

    /**
     * Get a test response emulating a successful request
     */
    public function getTestResponse($action) : array {

        $dt = new \DateTime();
        $dt->modify('-15 seconds');
        $timestamp = $dt->format( \DateTimeInterface::ISO8601 );

        $success = true;
        $hostname = "localhost";
        $errorcodes = [];
        if(!$this->responseValue) {
            $errorcodes[] = 'bad-request';
        }

        $response = [
            "success" => $this->responseValue, // whether this request was a valid token
            "action" => $action, // the action name for this request (important to verify)
            "challenge_ts" => $timestamp, // timestamp of the challenge load (ISO format yyyy-MM-dd'T'HH:mm:ssZZ)
            "hostname" => $hostname, // the hostname of the site where the reCAPTCHA was solved
            "error-codes" => $errorcodes, // optional
            "cdata" => "" // optional customer data
        ];

        return $response;
    }

    /**
     * Return a RecaptchaV3TokenResponse instance for verification
     */
    protected function getTokenResponse( $decoded, $score, $action ) : TokenResponse {
        return new TurnstileTokenResponse( $decoded, $score, $action );
    }

    /**
     * Create a test verification response with whatever settings are present on this instance
     * @inheritdoc
     */
    public function check(string $token, float $score = null, string $action = "") : ?TokenResponse {
        $decoded = $this->getTestResponse($action);
        return $this->getTokenResponse( $decoded, $score, $action );
    }
}

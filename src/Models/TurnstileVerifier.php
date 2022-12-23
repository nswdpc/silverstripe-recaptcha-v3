<?php

namespace NSWDPC\SpamProtection;

/**
 * Verification model for Turnstile
 * Communicates with the siteverify Turnstile API endpoint
 * @author James
 */
class TurnstileVerifier extends Verifier {

    /**
     * @inheritdoc
     */
    private static $url_verify = "https://challenges.cloudflare.com/turnstile/v0/siteverify";

    /**
     * Return a TurnstileTokenResponse instance for verification
     */
    protected function getTokenResponse( $decoded, $score, $action ) : TokenResponse {
        return new TurnstileTokenResponse( $decoded, $score, $action );
    }

}

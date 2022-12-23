<?php

namespace NSWDPC\SpamProtection;

/**
 * Verification model for RecaptchaV3
 * Communicates with the siteverify reCAPTCHA API endpoint
 * @author James
 */
class RecaptchaV3Verifier extends Verifier {

    /**
     * @inheritdoc
     */
    private static $url_verify = "https://www.google.com/recaptcha/api/siteverify";

    /**
     * Return a RecaptchaV3TokenResponse instance for verification
     */
    protected function getTokenResponse( $decoded, $score, $action ) : TokenResponse {
        return new RecaptchaV3TokenResponse( $decoded, $score, $action );
    }

}

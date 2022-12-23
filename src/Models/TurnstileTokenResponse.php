<?php

namespace NSWDPC\SpamProtection;

/**
 * Represents a token response from the result of a call to the Turnstile
 * siteverify endpoint
 * @author James
 */
class TurnstileTokenResponse extends TokenResponse {

    /**
     * Score handling is not supported by Turnstile, so this never fails
     */
    public function failOnScore() : bool {
        return false;
    }

    /**
     * Turnstile does not support score validation
     */
    public static function validateScore($score) : ?float {
        return null;
    }

    /**
     * @see https://developers.cloudflare.com/turnstile/get-started/client-side-rendering/
     * "This can only contain up to 32 alphanumeric characters including _ and -."
     */
    public static function formatAction($action) : string {
        $action = preg_replace("/[^a-z0-9_\\-]/i", "", $action);
        $action = substr($action, 0, 32);// 32 chr maximum
        return $action;
    }

    /**
     * Checks whether the response is completely valid.
     * Whether the API responded with a success
     * Whether the action if provided matched the response action
     * @return boolean
     */
    public function isValid() : bool {

        // if the API return a false on 'success'
        if(!$this->isSuccess()) {
            return false;
        }

        // the action passed in does not match the response action
        if($this->failOnAction()) {
            return false;
        }

        return true;
    }

}

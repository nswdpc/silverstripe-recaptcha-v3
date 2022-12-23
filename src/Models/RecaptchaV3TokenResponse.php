<?php

namespace NSWDPC\SpamProtection;

/**
 * Represents a token response from the result of a call to the Recatpcha
 * siteverify endpoint
 * @author James
 */
class RecaptchaV3TokenResponse extends TokenResponse {

    /**
     * Validate a score parameter and return a score that is within bounds for comparison
     */
    public static function validateScore($score) : ?float {
        // null, return null (use configuration)
        if(is_null($score)){
            $score = static::getDefaultScore();
        }

        // not a number
        if(!is_float($score) && !is_int($score)) {
            throw new \Exception("Score should be a number between 0.0 and 1.0");
        }
        if($score > 1) {
            throw new \Exception("Score should not be > 1");
        } else if($score < 0) {
            throw new \Exception("Score should not be < 0");
        }
        return $score;
    }

    /**
     * @see https://developers.google.com/recaptcha/docs/v3#actions
     * "Note: Actions may only contain alphanumeric characters and slashes, and must not be user-specific."
     */
    public static function formatAction($action) : string {
        $action = preg_replace("/[^a-z0-9\\/]/i", "", $action);
        return $action;
    }

    /**
     * Checks whether the response is completely valid.
     * Whether the API responded with a success
     * Whether the action if provided matched the response action
     * Whether the score is above the threshold
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

        // if the score does not meet requirements for quality
        if( $this->failOnScore() ) {
            return false;
        }

        return true;
    }

}

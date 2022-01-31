<?php

namespace NSWDPC\SpamProtection;

use Silverstripe\Core\Config\Config;
use Silverstripe\Core\Config\Configurable;

/**
 * Represents a token response from the result of a call to the Recatpcha3
 * siteverify endpoint
 * The possible errors that come back from the token verification are:
 * missing-input-secret	The secret parameter is missing.
 * invalid-input-secret	The secret parameter is invalid or malformed.
 * missing-input-response	The response parameter is missing.
 * invalid-input-response	The response parameter is invalid or malformed.
 * bad-request	The request is invalid or malformed.
 * timeout-or-duplicate	The response is no longer valid: either is too old or has been used previously.
 * @author James <james.ellis@dpc.nsw.gov.au>
 */
class TokenResponse {

    use Configurable;

    private static $score = 0.5;//default score

    private $response = [];

    private $action = '';

    private $verification_score = null;

    // the 'secret' key (the secret key in your Recaptcha config) in the request is missing or bad
    const ERR_MISSING_INPUT_SECRET = 'missing-input-secret';
    const ERR_INVALID_INPUT_SECRET = 'invalid-input-secret';

    // the 'response' key (the token) in the request is missing or bad
    const ERR_MISSING_INPUT_RESPONSE = 'missing-input-response';
    const ERR_INVALID_INPUT_RESPONSE = 'invalid-input-response';

    // general bad request
    const ERR_BAD_REQUEST = 'bad-request';

    // token already checked or is outside the 2 minute timeout
    const ERR_TIMEOUT_OR_DUPLCIATE = 'timeout-or-duplicate';

    /**
     * @param array $response the result from a call to site verify
     * @param float|null $score
     * @param string $action
     */
    public function __construct(array $response, $score = null, $action = '') {
        $this->response = $response;
        $this->action = self::formatAction($action);
        $this->verification_score = self::validateScore($score);
    }

    /**
     * Validate a score parameter and return a score that is within bounds for comparison
     */
    public static function validateScore($score) {
        // null, return null (use configuration)
        if(is_null($score)){
            $score = self::getDefaultScore();
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
    public static function formatAction($action) {
        $action = preg_replace("/[^a-z0-9\\/]/i", "", $action);
        return $action;
    }

    /**
     * Return the response returned from the verification API
     * @returns array
     */
    public function getResponse() {
        return $this->response;
    }

    /**
     * Determins whether the response score suggests a lower quality action
     * @returns boolean
     */
    public function failOnScore() {
        $response_score = $this->getResponseScore();
        // if the response score is less than the allowed score, it's lower quality than we want
        $result = $response_score < $this->verification_score;
        Logger::log("RecaptchaV3 TokenResponse::failOnScore() check {$response_score} < {$this->verification_score} result=" . ($result ? "OK" : "FAIL"), "NOTICE");
        return $result;
    }

    /**
     * @returns boolean
     */
    public function failOnAction() {
        if($action = $this->getAction()) {
            return $action != $this->getResponseAction();
        } else {
            // no action provide, cannot check on it
            return false;
        }
    }

    /**
     * Checks whether the response is completely valid.
     * Whether the API responded with a success
     * Whether the action if provided matched the response action
     * Whether the score is above the threshold
     * @return boolean
     */
    public function isValid() {

        // if the API return a false on 'success'
        if(!$this->isSuccess()) {
            return false;
        }

        // the action passed in does not match the response action
        if($this->failOnAction()) {
            Logger::log("RecaptchaV3 failed - action mismatch", "NOTICE");
            return false;
        }

        // if the score does not meet requirements for quality
        if( $this->failOnScore() ) {
            return false;
        }

        return true;
    }

    /**
     * @returns string
     */
    public function getAction() {
        return $this->action;
    }

    /**
     * Return the score threshold to use for verifying responses
     * @return float
     */
    public function getScore() {
        return $this->verification_score ? $this->verification_score : self::getDefaultScore();
    }

    /**
     * Returns the default score from configuration
     * @return float
     */
    public static function getDefaultScore() {
        return round(Config::inst()->get(self::class, 'score'), 2);
    }

    /**
     * @returns string
     */
    public function getResponseAction() {
        return isset($this->response['action']) ? $this->response['action'] : '';
    }

    /**
     * Get score from response, 1.0 is very likely a good interaction, 0.0 is very likely a bot
     * @returns string
     */
    public function getResponseScore() {
        return isset($this->response['score']) ? $this->response['score'] : '';
    }

    /**
     * @returns string
     */
    public function getResponseHostname() {
        return isset($this->response['hostname']) ? $this->response['hostname'] : '';
    }

    /**
     * Note: "whether this request was a valid reCAPTCHA token for your site"
     * This does not do a score check or return that the token/action is valid
     * @returns boolean
     */
    public function isSuccess() {
        return isset($this->response['success']) && $this->response['success'];
    }

    /**
     * @returns array
     */
    public function errorCodes() {
        return isset($this->response['error-codes']) && is_array($this->response['error-codes']) ? $this->response['error-codes'] : [];
    }

    /**
     * @returns boolean
     */
    public function isTimeout() {
        $codes = $this->errorCodes();
        return array_search( self::ERR_TIMEOUT_OR_DUPLCIATE, $codes) !== false;
    }

    /**
     * @returns boolean
     */
    public function isBadRequest() {
        $codes = $this->errorCodes();
        return array_search( self::ERR_BAD_REQUEST, $codes) !== false;
    }

}

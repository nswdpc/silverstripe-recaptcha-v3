<?php

namespace NSWDPC\SpamProtection;

use Silverstripe\Core\Config\Config;
use Silverstripe\Core\Config\Configurable;

/**
 * Abstract TokenResponse handling
 * @author James
 */
abstract class TokenResponse {

    use Configurable;

    /**
     * @var float
     */
    private static $score = 0.5;//default score

    /**
     * @var array
     */
    protected $response = [];

    /**
     * @var string
     */
    protected $action = '';

    /**
     * @var float|null
     */
    protected $verification_score = null;

    // Verification error codes

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

    // An internal error happened while validating the response
    const ERR_INTERNAL_ERROR = 'internal-error';

    /**
     * @param array $response the result from a call to the siteverify endpoint
     * @param float|null $score some implementations do not support a score
     * @param string $action
     */
    public function __construct(array $response, float $score = null, $action = '') {
        $this->response = $response;
        $this->action = static::formatAction($action);
        $this->verification_score = static::validateScore($score);
    }

    /**
     * Validate a score parameter and return a score that is within bounds for comparison
     */
    abstract public static function validateScore(float $score) : ?float;

    /**
     * Format an action string based on implementation rules
     */
    abstract public static function formatAction(string $action) : string;

    /**
     * Return the response returned from the verification API
     * @returns array
     */
    public function getResponse() : array {
        return $this->response;
    }

    /**
     * Determines whether the response score suggests a lower quality action
     * @returns bool
     */
    public function failOnScore() : bool {
        $response_score = $this->getResponseScore();
        // if the response score is less than the allowed score, it's lower quality than we want
        return $response_score < $this->verification_score;
    }

    /**
     * @returns bool
     */
    public function failOnAction() : bool {
        if($action = $this->getAction()) {
            $responseAction = $this->getResponseAction();
            Logger::log("Action is {$action}, response action is {$responseAction}" );
            return $action != $responseAction;
        } else {
            // no action provide, cannot check on it
            return false;
        }
    }

    /**
     * Validte based on implementation rules whether a response is valid
     * @return bool
     */
    abstract public function isValid() : bool;

    /**
     * @returns string
     */
    public function getAction() : string {
        return $this->action;
    }

    /**
     * Return the score threshold to use for verifying responses
     * @return float
     */
    public function getScore() : float {
        return $this->verification_score ? $this->verification_score : static::getDefaultScore();
    }

    /**
     * Returns the default score from configuration
     * @return float
     */
    public static function getDefaultScore() : float {
        return round(Config::inst()->get(static::class, 'score'), 2);
    }

    /**
     * @returns string
     */
    public function getResponseAction() : string {
        return isset($this->response['action']) ? $this->response['action'] : '';
    }

    /**
     * Get score from response, if the implementation supports it
     * @returns float
     */
    public function getResponseScore() : ?float {
        return isset($this->response['score']) ? $this->response['score'] : null;
    }

    /**
     * @returns string
     */
    public function getResponseHostname() : string {
        return isset($this->response['hostname']) ? $this->response['hostname'] : '';
    }

    /**
     * Note: "whether this request was a valid reCAPTCHA token for your site"
     * This does not do a score check or return that the token/action is valid
     * @returns boolean
     */
    public function isSuccess() : bool {
        return isset($this->response['success']) && $this->response['success'];
    }

    /**
     * @returns array
     */
    public function errorCodes() : array {
        return isset($this->response['error-codes']) && is_array($this->response['error-codes']) ? $this->response['error-codes'] : [];
    }

    /**
     * @returns bool
     */
    public function isTimeout() : bool {
        $codes = $this->errorCodes();
        return array_search( self::ERR_TIMEOUT_OR_DUPLCIATE, $codes) !== false;
    }

    /**
     * @returns bool
     */
    public function isBadRequest() : bool {
        $codes = $this->errorCodes();
        return array_search( self::ERR_BAD_REQUEST, $codes) !== false;
    }

    /**
     * @returns bool
     */
    public function isInternalError() : bool {
        $codes = $this->errorCodes();
        return array_search( self::ERR_INTERNAL_ERROR, $codes) !== false;
    }

}

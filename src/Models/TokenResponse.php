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

    private static $log_stats = false;

    /**
     * @param array $response the result from a call to the siteverify endpoint
     * @param float|null $score some implementations do not support a score
     * @param string $action
     */
    public function __construct(array $response, ?float $score = null, string $action = '')
    {
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
     */
    public function getResponse() : array
    {
        return $this->response;
    }

    /**
     * Determines whether the response score suggests a lower quality action
     */
    public function failOnScore() : bool
    {
        $responseScore = $this->getResponseScore();
        // if the response score is less than the allowed score, it's lower quality than we want
        $result = ($responseScore < $this->verification_score);
        if($result) {
            self::logStat("failOnScore", ["threshold" => $this->verification_score, "response" => $responseScore ]);
        }
        return $result;
    }

    /**
     * Check for action mismatch
     */
    public function failOnAction() : bool
    {
        if ($action = $this->getAction()) {
            $responseAction = $this->getResponseAction();
            $result = ($action != $responseAction);
            if($result) {
                self::logStat("failOnAction", ["action" => $action, "response" => $responseAction]);
            }
        } else {
            // no action provided, cannot check on it
            $result = false;
        }
        return $result;
    }

    /**
     * Log a captcha stat
     */
    public static function logStat(string $message, $reason) : void
    {
        if(self::config()->get('log_stats')) {
            $stat = [
                "message" => $message,
                "reason" => $reason
            ];
            Logger::log("captcha stat:" . json_encode($stat), "INFO");
        }
    }

    /**
     * Validate based on implementation rules whether a response is valid
     * @return bool
     */
    abstract public function isValid() : bool;

    /**
     * Get the current action value
     */
    public function getAction() : string
    {
        return $this->action;
    }

    /**
     * Get the current score value
     * @return float
     */
    public function getScore() : float
    {
        return $this->verification_score ? $this->verification_score : static::getDefaultScore();
    }

    /**
     * Returns the default score from configuration
     */
    public static function getDefaultScore() : float {
        return round(Config::inst()->get(static::class, 'score'), 2);
    }

    /**
     * Get the action returned from the response
     */
    public function getResponseAction() : string
    {
        return isset($this->response['action']) ? $this->response['action'] : '';
    }

    /**
     * Get score from response, if the implementation supports it
     * @returns float
     */
    public function getResponseScore() : ?float
    {
        return isset($this->response['score']) ? $this->response['score'] : null;
    }

    /**
     * Get response hostname
     */
    public function getResponseHostname() : string
    {
        return isset($this->response['hostname']) ? $this->response['hostname'] : '';
    }

    /**
     * Note: "whether this request was a valid reCAPTCHA token for your site"
     * This does not do a score check or return that the token/action is valid
     */
    public function isSuccess() : bool
    {
        $is = isset($this->response['success']) && $this->response['success'];
        if(!$is) {
            TokenResponse::logStat("isSuccess", false);
        }
        return $is;
    }

    /**
     * Get all error codes
     */
    public function errorCodes() : array
    {
        return isset($this->response['error-codes']) && is_array($this->response['error-codes']) ? $this->response['error-codes'] : [];
    }

    /**
     * Check if the token has timed out (or is a duplicate)
     */
    public function isTimeout() : bool
    {
        $codes = $this->errorCodes();
        $is = array_search(self::ERR_TIMEOUT_OR_DUPLCIATE, $codes) !== false;
        if($is) {
            TokenResponse::logStat("isTimeoutOrDuplicate", true);
        }
        return $is;
    }

    /**
     * Check for bad request
     */
    public function isBadRequest() : bool
    {
        $codes = $this->errorCodes();
        $is = array_search(self::ERR_BAD_REQUEST, $codes) !== false;
        if($is) {
            TokenResponse::logStat("isBadRequest", true);
        }
        return $is;
    }

    /**
     * @returns bool
     */
    public function isInternalError() : bool
    {
        $codes = $this->errorCodes();
        return array_search( self::ERR_INTERNAL_ERROR, $codes) !== false;
    }
}

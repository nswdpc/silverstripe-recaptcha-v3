<?php

namespace NSWDPC\SpamProtection;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;

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
class TokenResponse
{
    use Configurable;

    private static float $score = 0.5;

    private string $action;

    private float $verification_score;

    // the 'secret' key (the secret key in your Recaptcha config) in the request is missing or bad
    public const ERR_MISSING_INPUT_SECRET = 'missing-input-secret';

    public const ERR_INVALID_INPUT_SECRET = 'invalid-input-secret';

    // the 'response' key (the token) in the request is missing or bad
    public const ERR_MISSING_INPUT_RESPONSE = 'missing-input-response';

    public const ERR_INVALID_INPUT_RESPONSE = 'invalid-input-response';

    // general bad request
    public const ERR_BAD_REQUEST = 'bad-request';

    // token already checked or is outside the 2 minute timeout
    public const ERR_TIMEOUT_OR_DUPLCIATE = 'timeout-or-duplicate';

    private static bool $log_stats = false;

    /**
     * @param array $response the result from a call to site verify
     */
    public function __construct(private array $response, ?float $score = null, string $action = '')
    {
        $this->action = self::formatAction($action);
        $this->verification_score = self::validateScore($score);
    }

    /**
     * Validate a score parameter and return a score that is within bounds for comparison
     */
    public static function validateScore(?float $score = null): float
    {
        // null, return null (use configuration)
        if (is_null($score)) {
            $score = self::getDefaultScore();
        }

        if ($score > 1) {
            throw new \Exception("Score should not be > 1");
        } elseif ($score < 0) {
            throw new \Exception("Score should not be < 0");
        }

        return $score;
    }

    /**
     * @see https://developers.google.com/recaptcha/docs/v3#actions
     * "Note: Actions may only contain alphanumeric characters and slashes, and must not be user-specific."
     */
    public static function formatAction(string $action): string
    {
        $action = preg_replace("/[^a-z0-9\\/]/i", "", $action);
        return trim((string) $action);
    }

    /**
     * Is this an empty action?
     */
    public static function isEmptyAction(?string $action): bool
    {
        return is_null($action) || $action === '';
    }

    /**
     * Return the response returned from the verification API
     */
    public function getResponse(): array
    {
        return $this->response;
    }

    /**
     * Determins whether the response score suggests a lower quality action
     */
    public function failOnScore(): bool
    {
        $responseScore = $this->getResponseScore();
        // if the response score is less than the allowed score, it's lower quality than we want
        $result = ($responseScore < $this->verification_score);
        if ($result) {
            self::logStat("failOnScore", ["threshold" => $this->verification_score, "response" => $responseScore ]);
        }

        return $result;
    }

    /**
     * Check for action mismatch
     */
    public function failOnAction(): bool
    {
        if (($action = $this->getAction()) !== '') {
            $responseAction = $this->getResponseAction();
            $result = ($action !== $responseAction);
            if ($result) {
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
    public static function logStat(string $message, $reason): void
    {
        if (self::config()->get('log_stats')) {
            $stat = [
                "message" => $message,
                "reason" => $reason
            ];
            Logger::log("captcha stat:" . json_encode($stat), "INFO");
        }
    }

    /**
     * Checks whether the response is completely valid.
     * Whether the API responded with a success
     * Whether the action if provided matched the response action
     * Whether the score is above the threshold
     */
    public function isValid(): bool
    {

        // if the API return a false on 'success'
        if (!$this->isSuccess()) {
            return false;
        }

        // the action passed in does not match the response action
        if ($this->failOnAction()) {
            return false;
        }

        // if the score does not meet requirements for quality
        return !$this->failOnScore();
    }

    /**
     * Get the current action value
     */
    public function getAction(): string
    {
        return $this->action;
    }

    /**
     * Get the current score value
     */
    public function getScore(): float
    {
        return $this->verification_score ?: self::getDefaultScore();
    }

    /**
     * Returns the default score from configuration
     */
    public static function getDefaultScore(): float
    {
        return round(Config::inst()->get(self::class, 'score'), 2);
    }

    /**
     * Get the action returned from the response
     */
    public function getResponseAction(): string
    {
        return $this->response['action'] ?? '';
    }

    /**
     * Get score from response, 1.0 is very likely a good interaction, 0.0 is very likely a bot
     */
    public function getResponseScore(): float
    {
        return $this->response['score'] ?? '';
    }

    /**
     * Get response hostname
     */
    public function getResponseHostname(): string
    {
        return $this->response['hostname'] ?? '';
    }

    /**
     * Note: "whether this request was a valid reCAPTCHA token for your site"
     * This does not do a score check or return that the token/action is valid
     */
    public function isSuccess(): bool
    {
        $is = isset($this->response['success']) && $this->response['success'];
        if (!$is) {
            TokenResponse::logStat("isSuccess", false);
        }

        return $is;
    }

    /**
     * Get all error codes
     */
    public function errorCodes(): array
    {
        return isset($this->response['error-codes']) && is_array($this->response['error-codes']) ? $this->response['error-codes'] : [];
    }

    /**
     * Check if the token has timed out (or is a duplicate)
     */
    public function isTimeout(): bool
    {
        $codes = $this->errorCodes();
        $is = in_array(self::ERR_TIMEOUT_OR_DUPLCIATE, $codes);
        if ($is) {
            TokenResponse::logStat("isTimeoutOrDuplicate", true);
        }

        return $is;
    }

    /**
     * Check for bad request
     */
    public function isBadRequest(): bool
    {
        $codes = $this->errorCodes();
        $is = in_array(self::ERR_BAD_REQUEST, $codes);
        if ($is) {
            TokenResponse::logStat("isBadRequest", true);
        }

        return $is;
    }
}

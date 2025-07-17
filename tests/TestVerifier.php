<?php

namespace NSWDPC\SpamProtection\Tests;

use NSWDPC\SpamProtection\TokenResponse;
use NSWDPC\SpamProtection\Verifier;

/**
 * TestVerifier for reCAPTCHAv3 testing
 * @see https://developers.google.com/recaptcha/docs/v3

 */
class TestVerifier extends Verifier
{
    const RESPONSE_HUMAN_SCORE = 0.9;
    const RESPONSE_BOT_SCORE = 0.1;

    /**
     * @var float
     */
    protected $responseScore = self::RESPONSE_HUMAN_SCORE;

    /**
     * @var bool
     */
    protected $responseValue = true;

    public function setIsHuman(bool $is): self
    {
        $this->responseValue = $is;
        if ($is) {
            $this->responseScore = self::RESPONSE_HUMAN_SCORE;
            $this->responseValue = true;
        } else {
            $this->responseScore = self::RESPONSE_BOT_SCORE;
            $this->responseValue = false;
        }
        return $this;
    }

    /**
     * Get a test response emulating a successful request
     */
    public function getTestResponse($action) : array
    {
        $dt = new \DateTime();
        $dt->modify('-15 seconds');
        $timestamp = $dt->format(\DateTimeInterface::ISO8601);

        $success = true;
        $hostname = "localhost";
        $errorcodes = [];
        if (!$this->responseValue) {
            $errorcodes[] = 'an-error-code';
        }

        $response = [
            "success" => $this->responseValue, // whether this request was a valid reCAPTCHA token for your site
            "score" => $this->responseScore, // the score for this request (0.0 - 1.0)
            "action" => $action, // the action name for this request (important to verify)
            "challenge_ts" => $timestamp, // timestamp of the challenge load (ISO format yyyy-MM-dd'T'HH:mm:ssZZ)
            "hostname" => $hostname, // the hostname of the site where the reCAPTCHA was solved
            "error-codes" => $errorcodes // optional
        ];

        return $response;
    }

    /**
     * Create a test verification response with whatever settings are present on this instance
     * @inheritdoc
     */
    public function check(string $token, ?float $score = null, string $action = "") : ?TokenResponse
    {
        $decoded = $this->getTestResponse($action);
        return new TokenResponse($decoded, $score, $action);
    }
}

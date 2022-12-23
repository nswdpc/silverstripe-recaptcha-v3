<?php

namespace NSWDPC\SpamProtection\Tests;

use NSWDPC\SpamProtection\Verifier;
use NSWDPC\SpamProtection\TurnstileField;

/**
 * A test version of RecaptchaV3Field
 */
class TestTurnstileField extends TurnstileField {

    /**
     * Return a dummy 'token' as the submitted value of the field
     * @return string
     */
    public function Value() {
        $this->value = "test-value-for-" . $this->getName();
        return parent::Value();
    }

    /**
     * Set a test verifier to use for this test
     */
    public function setVerifier(Verifier $verifier) {
        if(!($verifier instanceof TestTurnstileVerifier)) {
            throw \InvalidArgumentException("Verifier parameter should be an instance of TestTurnstileVerifier");
        }
        return parent::setVerifier($verifier);
    }

    /**
     * Return the test verifier to use for this test
     */
    public function getVerifier() : Verifier {
        $verifier = parent::getVerifier();
        if(!($verifier instanceof TestTurnstileVerifier)) {
            throw \InvalidArgumentException("Verifier returned should be an instance of TestTurnstileVerifier");
        }
        return $verifier;
    }

}

<?php

namespace NSWDPC\SpamProtection\Tests;

use NSWDPC\SpamProtection\Verifier;
use NSWDPC\SpamProtection\RecaptchaV3Field;

/**
 * A test version of RecaptchaV3Field
 */
class TestRecaptchaV3Field extends RecaptchaV3Field {

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
        if(!($verifier instanceof TestRecaptchaV3Verifier)) {
            throw \InvalidArgumentException("Verifier parameter should be an instance of TestRecaptchaV3Verifier");
        }
        return parent::setVerifier($verifier);
    }

    /**
     * Return the test verifier to use for this test
     */
    public function getVerifier() : Verifier {
        $verifier = parent::getVerifier();
        if(!($verifier instanceof TestRecaptchaV3Verifier)) {
            throw \InvalidArgumentException("Verifier returned should be an instance of TestRecaptchaV3Verifier");
        }
        return $verifier;
    }

}

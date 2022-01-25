<?php

namespace NSWDPC\SpamProtection\Tests;

use NSWDPC\SpamProtection\Verifier;
use NSWDPC\SpamProtection\RecaptchaV3Field;

/**
 * A test version of RecaptchaV3Field
 */
class TestRecaptchaV3Field extends RecaptchaV3Field {

    protected $verifier = null;

    /**
     * Return a dummy 'token' as the submitted value of the field
     * @return string
     */
    public function Value() {
        $this->value = "test-value-for-" . $this->getName();
        return parent::Value();
    }

    /**
     * Set a verifier to use for this test
     */
    public function setVerifier(TestVerifier $verifier) {
        $this->verifier = $verifier;
        return $this;
    }

    /**
     * Return the verifier to use for this test
     */
    public function getVerifier() : TestVerifier {
        return $this->verifier;
    }

}

<?php

namespace NSWDPC\SpamProtection;

use SilverStripe\Core\Injector\Injector;

/**
 * Trait for controller that need to set/get a Verifier
 *
 */
trait HasVerifier {

    /**
     * @var NSWDPC\SpamProtection\Verifier|null
     */
    protected $verifier = null;

    /**
     * Set the verifier to use for this request
     */
    public function setVerifier(Verifier $verifier) {
        $this->verifier = $verifier;
        return $this;
    }

    /**
     * Return the verifier to use for this request
     */
    public function getVerifier() : Verifier {
        if(!$this->verifier) {
            $this->verifier = Injector::inst()->get(Verifier::class);
        }
        return $this->verifier;
    }

}

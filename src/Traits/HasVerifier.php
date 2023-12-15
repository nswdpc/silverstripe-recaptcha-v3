<?php

namespace NSWDPC\SpamProtection;

/**
 * Trait for controller that need to set/get a Verifier
 * @author James
 */
trait HasVerifier
{

    /**
     * @var NSWDPC\SpamProtection\Verifier|null
     */
    protected $verifier = null;

    /**
     * Set the verifier to use for this request
     * This can be used to override the verifier, eg. for testing
     */
    public function setVerifier(Verifier $verifier) : self
    {
        $this->verifier = $verifier;
        return $this;
    }

    /**
     * Implementations return their own verifier
     */
    public function getVerifier() : ?Verifier {
        return null;
    }
}

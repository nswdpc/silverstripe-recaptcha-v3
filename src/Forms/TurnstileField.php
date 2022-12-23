<?php

namespace NSWDPC\SpamProtection;

use Silverstripe\Forms\HiddenField;
use SilverStripe\View\Requirements;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Control\Controller;

/**
 * Provides a Cloudflare Turnstile field, compatible with RecaptchaV3Field
 * @author James
 */
class TurnstileField extends HiddenField {

    use HasVerifier;

    use CaptchaSupport;

    /**
     * Site key, configured in project
     * @param string
     */
    private static $site_key = '';

    /**
     * Script to use to load Turnstile API in recaptcha compat mode
     * @param string
     */
    private static $script_render = "https://challenges.cloudflare.com/turnstile/v0/api.js";

    /**
     * Default action name to use
     * @param string
     */
    private static $execute_action = "submit";

    /**
     * Session key for storing recaatcha response
     */
    private static $session_key = "TurnstileCaptcha";

    /**
     * Field holder template to use
     * @param string
     */
    protected $fieldHolderTemplate = "TurnstileField_holder";

    /**
     * Return the TurnstileVerifier to use for this request
     */
    public function getVerifier() : ?Verifier {
        if(!$this->verifier) {
            $this->verifier = Injector::inst()->get(TurnstileVerifier::class);
        }
        return $this->verifier;
    }

    /**
     * Returns the configured action name for this form field
     * @returns string
     */
    public function getCaptchaAction() : string {
        $prefix = "";
        if(!$this->has_prefixed_action) {
            $prefix = $this->ID() . "_";// prefix with underscore suffix
        }
        return TurnstileTokenResponse::formatAction($prefix . $this->getExecuteAction());
    }

    /**
     * Require the Turnstile client-side API script
     */
    protected function requireClientAPIScript() : void {
        $site_key = $this->config()->get('site_key');
        Requirements::javascript(
            $this->config()->get('script_render'),
            [
                'async' => true,
                'defer' => true
            ]
        );
    }

    /**
     * Get the requirements for this particular field
     * @returns void
     */
    protected function addRequirements() : void {
        $this->requireClientAPIScript();
    }

    /**
     * Return the token value, for sending to the verification endpoint
     */
    public function getTokenValue() {
        $token = null;
        try {
            $key = 'cf-turnstile-response';
            $form = $this->getForm();
            $request = $form->getRequestHandler()->getRequest();
            $token = $request->postVar($key);
        } catch (\Exception $e) {
            // failed to find the data
        }
        return $token;
    }

}

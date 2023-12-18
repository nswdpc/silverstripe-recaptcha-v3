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
 * Options: https://developers.cloudflare.com/turnstile/get-started/client-side-rendering/#configurations
 * @author James
 */
class TurnstileField extends HiddenField {

    use HasVerifier;

    use CaptchaSupport;

    /**
     * @var string
     * See validate()
     */
    const VALIDATION_ERROR_CODE = "FORM_TURNSTILE";

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
     * The default response field name for the hidden input added by Turnstile JS API
     */
    private static $response_field_name = "cf-turnstile-response";

    /**
     * The widget theme, empty value by default (auto)
     */
    private static $widget_theme = "dark";

    /**
     * The widget size, empty value by default (normal)
     */
    private static $widget_size = "compact";

    /**
     * Field holder template to use
     * @param string
     */
    protected $fieldHolderTemplate = "TurnstileField_holder";

    /**
     * @var string
     * Delimiter for action. / not allowed in Turnstile
     */
    const ACTION_DELIMITER = "_";

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
     * Returns the configured action for this form field, formatted to Turnstile rules
     * @returns string
     */
    public function getCaptchaAction() : string {
        return TurnstileTokenResponse::formatAction($this->getExecuteAction());
    }

    /**
     * Return response field name configured
     */
    public function getResponseFieldName() : string {
        return $this->config()->get('response_field_name');
    }

    /**
     * Return configured widget theme
     */
    public function getWidgetTheme() : string {
        return $this->config()->get('widget_theme');
    }

    /**
     * Return configured widget size
     */
    public function getWidgetSize() : string {
        return $this->config()->get('widget_size');
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
            $key = $this->getResponseFieldName();
            $form = $this->getForm();
            $request = $form->getRequestHandler()->getRequest();
            $token = $request->postVar($key);
        } catch (\Exception $e) {
            // failed to find the data
            Logger::log("TurnstileField failed to get a token value from POST data '{$key}' with error: {$e->getMessage()}", "NOTICE");
        }
        return $token;
    }

}

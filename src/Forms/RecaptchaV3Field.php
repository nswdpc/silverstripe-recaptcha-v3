<?php

namespace NSWDPC\SpamProtection;

use SilverStripe\Core\Injector\Injector;
use Silverstripe\Forms\HiddenField;
use SilverStripe\View\Requirements;

/**
 * Provides a Recaptcha Form Field for use in forms
 * When the action is fired, the token retrieved from Recaptcha is added to the hidden value
 * When the form field is validated after submission, the token is verified
 * @author James
 */
class RecaptchaV3Field extends HiddenField {

    use HasVerifier;

    use CaptchaSupport;

    /**
     * Site key, configured in project
     * @param string
     */
    private static $site_key = '';

    /**
     * Script to use to load recaptcha API
     * @param string
     */
    private static $script_render = "https://www.google.com/recaptcha/api.js";

    /**
     * Default action name to use
     * @param string
     */
    private static $execute_action = "submit";

    /**
     * Session key for storing recaatcha response
     */
    private static $session_key = "RecaptchaV3";

    /**
     * Field holder template to use
     * @param string
     */
    protected $fieldHolderTemplate = "RecaptchaV3Field_holder";

    /**
     * Return the RecaptchaV3Verifier to use for this request
     */
    public function getVerifier() : ?Verifier {
        if(!$this->verifier) {
            $this->verifier = Injector::inst()->get(RecaptchaV3Verifier::class);
        }
        return $this->verifier;
    }

    /**
     * Returns the unique id to use in the customScript requirement
     * @returns string
     */
    public function getUniqueId() : string {
        return "recaptcha_execute_{$this->ID()}";
    }

    /**
     * Set a score for this instance
     */
    public function setScore($score) : self {
        $score = RecaptchaV3TokenResponse::validateScore($score);
        $this->score = $score;
        return $this;
    }

    /**
     * Score for field verification
     */
    public function getScore() : ?float {
        if(is_null($this->score)) {
            // use configured value if none set
            return Config::inst()->get(RecaptchaV3TokenResponse::class, 'score');
        } else {
            return $this->score;
        }
    }

    /**
     * Returns the configured action name for this form field
     * @returns string
     */
    public function getCaptchaAction() : string {
        $prefix = "";
        if(!$this->has_prefixed_action) {
            $prefix = $this->ID() . "/";
        }
        return RecaptchaV3TokenResponse::formatAction($prefix . $this->getExecuteAction());
    }

    /**
     * Require the client-side API script
     */
    protected function requireClientAPIScript() : void {
        $site_key = $this->config()->get('site_key');
        Requirements::javascript(
            $this->config()->get('script_render'). "?render={$site_key}",
            "recaptchav3_api_with_site_key"
        );
    }

    /**
     * Get the requirements for this particular field
     * @returns void
     */
    protected function addRequirements() : void {
        $this->requireClientAPIScript();
        // load the template Javascript for this field
        Requirements::customScript(  $this->actionScript(), $this->getUniqueId() );
    }

    /**
     * The execution script for the action associated with this form
     * grecaptcha.execute() is fired  when a field is focused for the first time then at some time $threshold milliseconds after that
     * Tokens time out after 2 minutes, refreshing the token will assist in reducing token timeouts on longer forms
     * @returns string
     */
    protected function actionScript() : string {
        $site_key = $this->config()->get('site_key');
        $data = [
            'action' => $this->getCaptchaAction()
        ];
        $configuration = json_encode($data, JSON_UNESCAPED_SLASHES);
        $id = $this->ID();
        $field_name = $this->getName();
        // refresh token after 30s
        $threshold = 30000;

        /*
         * when an error occurs and the form is re-loaded with values
         * the user may press submit again with no token sent due to lack of focus()
         * refresh the token right away in that case
         */
        $refresh_on_error = "";

        if(($form = $this->getForm()) && ($errors = $form->getSessionValidationResult()) && !$errors->isValid()) {
            $refresh_on_error = "recaptcha_execute_handler(form);";
        }

        $js = <<<JS
grecaptcha.ready(function() {

    var recaptcha_require_refresh = function(f) {
        try {
            var threshold = {$threshold};
            var iv = 0;
            var dlc = f.dataset.lastcheck;
            if(dlc) {
                iv = Date.now() - dlc;
            }
        } catch (e) {}
        return iv > threshold || f.querySelector('input[name="{$field_name}"]').value == '';
    }

    var recaptcha_execute_handler = function(f) {
        if(!recaptcha_require_refresh(f)) {
            return;
        }
        grecaptcha.execute(
            '{$site_key}',
            {$configuration}
        ).then(
            function(token) {
                f.querySelector('input[name="{$field_name}"]').value = token;
                f.setAttribute('data-lastcheck', Date.now());
            }
        ).catch(
            function(fail) {
                console.warn(fail);
            }
        );
    };
    var elm = document.getElementById('{$id}');
    if(!elm) {
        return;
    }
    var form = elm.form;
    {$refresh_on_error}
    for (i = 0; i < form.elements.length; i++) {
        if(form.elements[i].type != 'submit') {
            form.elements[i].addEventListener('focus', function(evt) {
                recaptcha_execute_handler(this.form);
            });
        }
    }
});
JS;
        return $js;
    }

}

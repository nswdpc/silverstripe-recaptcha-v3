<?php

namespace NSWDPC\SpamProtection;

use Silverstripe\Forms\HiddenField;
use SilverStripe\View\Requirements;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Core\Config\Config;
use SilverStripe\Control\Controller;

/**
 * Provides a Recaptcha Form Field for use in forms
 * When the action is fired, the token retrieved from Recaptcha is added to the hidden value
 * When the form field is validated after submission, the token is verified
 * @author James <james.ellis@dpc.nsw.gov.au>
 */
class RecaptchaV3Field extends HiddenField {

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
     * Per instance execute_action
     * @param string
     */
    private $field_execute_action = "";

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
     * Score for this field, if not provided, the configuration value will be used
     */
    private $score = null;

    /**
     * If the action is already prefixed, don't auto-prefix it with the field ID
     */
    private $has_prefixed_action = false;

    /**
     * Badge display options: empty string, 'form' or 'page'
     * If page it is up to you to to include NSWDPC/SpamProtection/PageBadge in your template in the appropriate location
     * See: https://developers.google.com/recaptcha/docs/faq#id-like-to-hide-the-recaptcha-badge.-what-is-allowed
     * @param string
     */
    private static $badge_display = "";


    const BADGE_DISPLAY_DEFAULT = '';// use the reCAPTCHAv3 library default (fixed bottom right)
    const BADGE_DISPLAY_FORM = 'form';// display the badge text in the form, above the actions
    const BADGE_DISPLAY_PAGE = 'page';// display the badge text in the page somewhere


    public function __construct($name, $title = null, $value = null)
    {
        parent::__construct($name, $title, $value);
    }

    /**
     * @returns string
     */
    public function getSiteKey()
    {
        return $this->config()->get('site_key');
    }

    /**
     * Returns a specific field holder template, for instance we may want to add some
     * buzz about the form being protected by Recaptcha, some links to assistance pages
     *
     * @param array $properties
     *
     * @return DBHTMLText
     */
    public function FieldHolder($properties = array())
    {
        $context = $this;

        $this->extend('onBeforeRenderHolder', $context, $properties);

        if (count($properties)) {
            $context = $this->customise($properties);
        }

        return $context->renderWith($this->getFieldHolderTemplates());
    }


    /**
     * Returns the field, sets requirements for this form
     * @param array $properties
     * @return string
     */
    public function Field($properties = []) {
        $field = parent::Field($properties);
        $this->addRequirements();
        return $field;
    }

    /**
     * Override the execute action configuration
     * @returns RecaptchaV3Field
     */
    public function setExecuteAction($action, $is_prefixed = false) {
        $this->field_execute_action = $action;
        $this->has_prefixed_action = $is_prefixed;
        return $this;
    }

    /**
     * Get the execution action for this field, if none is set use configuration
     * @returns string
     */
    public function getExecuteAction() {
        return $this->field_execute_action  ?
                $this->field_execute_action :
                $this->config()->get('execute_action');
    }

    /**
     * Returns the configured action name for this form
     * @returns string
     */
    public function getRecaptchaAction() {
        $prefix = "";
        if(!$this->has_prefixed_action) {
            $prefix = $this->ID() . "/";
        }
        return TokenResponse::formatAction($prefix . $this->getExecuteAction());
    }

    /**
     * Returns the unique id to use in the customScript requirement
     * @returns string
     */
    public function getUniqueId() {
        return "recaptcha_execute_{$this->ID()}";
    }

    /**
     * Set a score for this instance
     */
    public function setScore($score) {
        $score = TokenResponse::validateScore($score);
        $this->score = $score;
        return $this;
    }

    /**
     * Score for field verification
     */
    public function getScore() {
        if(is_null($this->score)) {
            // use configured value if none set
            return Config::inst()->get(TokenResponse::class, 'score');
        } else {
            return $this->score;
        }
    }

    /**
     * Get the requirements for this particular field
     * @returns void
     */
    protected function addRequirements() {
        $site_key = $this->config()->get('site_key');
        Requirements::javascript($this->config()->get('script_render'). "?render={$site_key}", "recaptchav3_api_with_site_key");
        // load the template Javascript for this field
        Requirements::customScript(  $this->actionScript(), $this->getUniqueId() );
    }

    /**
     * The execution script for the action associated with this form
     * grecaptcha.execute() is fired  when a field is focused for the first time then at some time $threshold milliseconds after that
     * Tokens time out after 2 minutes, refreshing the token will assist in reducing token timeouts on longer forms
     * @returns string
     */
    protected function actionScript() {
        $site_key = $this->config()->get('site_key');
        $data = [
            'action' => $this->getRecaptchaAction()
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

    /**
     * Store data from the TokenResponse model in session
     * This will be cleared when Form::clearFormState() is called as it uses .data
     */
    protected function storeResponseToSession($token, TokenResponse $response) {
        $request = Controller::curr()->getRequest();
        $session = $request->getSession();
        $data = [
            'token' => $token,
            'score' => $response->getResponseScore(),
            'hostname' => $response->getResponseHostname(),
            'action' => $response->getResponseAction()
        ];
        $session->set( $this->config()->get('session_key'), $data);
    }

    /**
     * Remove any previous session data
     */
    protected function clearSessionResponse($session = null) {
        $session = $session ?? Controller::curr()->getRequest()->getSession();
        $session_key = $this->config()->get('session_key');
        $session->clear( $session_key );
    }

    /**
     * Get response from session
     */
    public function getResponseFromSession($key = "") {
        $request = Controller::curr()->getRequest();
        $session = $request->getSession();
        $session_key = $this->config()->get('session_key');
        // store score for this token to session
        $data = $session->get( $session_key );//
        // clear session once retrieved
        $this->clearSessionResponse($session);
        if(isset($data[$key])) {
            return $data[$key];
        } else {
            return $data;
        }
    }

    /**
     * Validate the field
     * @see https://developers.google.com/recaptcha/docs/verify#error_code_reference
     *
     * @param Validator $validator
     * @return bool
     */
    public function validate($validator)
    {
        try {
            // clear previous attempts
            $this->clearSessionResponse();

            $message = '';
            // the token set by the script in executionScript()
            $token = $this->Value();
            // no token submitted with form
            if(!$token) {
                throw new \Exception( "No token" );
            }
            $action = $this->getRecaptchaAction();
            $verifier = new Verifier();
            $success = false;
            $response = $verifier->check($token, $this->getScore(), $action);
            // handle the response when it is a {@link NSWDPC\SpamProtection\TokenResponse}
            if($response instanceof TokenResponse) {
                // successful verification
                if($response->isValid()) {
                    // store token response score
                    $this->storeResponseToSession($token, $response);
                    // all good
                    $this->setSubmittedValue("");
                    return true;
                }
                // timeout
                if($response->isTimeout()) {
                    // > timeout to submit form
                    throw new RecaptchaVerificationException( _t( 'NSWDPC\SpamProtection.TOKEN_TIMEOUT', 'Please check the information provided and submit the form again.') );
                }
                throw new RecaptchaVerificationException( _t( 'NSWDPC\SpamProtection.TOKEN_POSSIBLE_SPAM', 'We have detected that the form may be a spam submission. Please try to submit the form again.') );
            }
            // general failure
            throw new \Exception("Verification failed - no/bad response from verify API");
        } catch (RecaptchaVerificationException $e) {
            // catch actual verification fails
            $message = $e->getMessage();
        } catch (\Exception $e) {
            // TODO: log failures on this general exception
            // set a general error
            $message = _t( 'NSWDPC\SpamProtection.TOKEN_VERIFICATION_GENERAL_ERROR', 'Sorry, the form submission failed. You may like to try again.');
        }
        // set error on form
        $this->getForm()->sessionError( $message );
        $validator->validationError( $this->getName(), $message, ValidationResult::TYPE_ERROR );
        $this->setSubmittedValue("");
        // fail validation
        return false;
    }

    /**
     * Return some information for templates to display the RecaptchaV3Badge
     * Returns an empty string, 'form' or 'page'
     */
    public function ShowRecaptchaV3Badge() : string {
        $displayOption = $this->config()->get('badge_display');
        switch($displayOption) {
            case self::BADGE_DISPLAY_FORM:
            case self::BADGE_DISPLAY_PAGE:
                $css = ".grecaptcha-badge { visibility: hidden; }";
                Requirements::customCSS($css, 'recaptcha_badge_hide');
                break;
            case self::BADGE_DISPLAY_DEFAULT:
            default:
                $displayOption = '';
                break;
        }
        return $displayOption;
    }

}

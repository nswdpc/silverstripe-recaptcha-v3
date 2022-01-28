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

    use HasVerifier;

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
     * Tag used to find the rule for validation
     * @var string
     */
    protected $recaptchaV3RuleTag = '';

    /**
     * Rule used for validation
     * @var string
     */
    protected $rule;

    /**
     * When true, a non-enabled RecaptchaV3Rule record will be created with a tag matching
     * the recaptchaV3RuleTag value assigned to this field
     * @var bool
     */
    private static $auto_create_rule = false;


    public function __construct($name, $title = null, $value = null)
    {
        parent::__construct($name, $title, $value);
    }

    /**
     * @inheritdoc
     *
     * Sets the tag to be used for rule retrieval.
     * If no tag/rule is found, the system default settings are used.
     *
     * Note: if your form  modifies the form name after initial construction
     * and then calls setForm() again the tag will change. You can work around this
     * by returning an unchanging tag in a Form method 'getRecaptchaV3Tag'
     *
     * If the recaptchav3 action changes between loading the form and submitting the form,
     * then field validation will fail (see TokenResponse::failOnAction() method)
     *
     * See {@link self::getRecaptchaV3Rule()}
     */
    public function setForm($form)
    {
        Logger::log("RecaptchaV3Field::setForm() called", "DEBUG");
        $this->setRecaptchaV3RuleTag( $form->getRecaptchaV3RuleTag() );
        return parent::setForm($form);
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
     * If a rule is present, this value is used
     * @return string
     */
    public function getRecaptchaAction() {
        if($rule = $this->getRecaptchaV3Rule()) {
            $action = $rule->Action;
        } else {
            $prefix = "";
            if(!$this->has_prefixed_action) {
                $prefix = $this->ID() . "/";
            }
            $action = $prefix . $this->getExecuteAction();
        }
        return TokenResponse::formatAction($action);
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
        if($rule = $this->getRecaptchaV3Rule()) {
            // a rule score is an int between 0 and 100
            return round($rule->Score / 100, 2);
        } else if(is_null($this->score)) {
            // use configured value if none set
            return Config::inst()->get(TokenResponse::class, 'score');
        } else {
            return $this->score;
        }
    }

    /**
     * Set the tag to use on this field.
     * This is automatically set by the RecaptchaV3SpamProtector::getFormField
     * when it calls self::setForm()
     * @return self
     */
    public function setRecaptchaV3RuleTag(string $tag) : self {
        if($this->recaptchaV3RuleTag && ($tag != $this->recaptchaV3RuleTag)) {
            // invalidate the rule
            Logger::log("RecaptchaV3Field::setRecaptchaV3RuleTag invalidating rule", "DEBUG");
            $this->rule = null;
        }
        $this->recaptchaV3RuleTag = $tag;
        Logger::log("RecaptchaV3Field::setRecaptchaV3RuleTag tag={$tag}", "DEBUG");
        return $this;
    }

    /**
     * Return a rule defined by the tag set on this field
     * @return RecaptchaV3Rule|null
     */
    public function getRecaptchaV3Rule() {
        if(!$this->rule) {
            if($tag = $this->recaptchaV3RuleTag) {
                Logger::log("getRecaptchaV3Rule searching for rule..", "DEBUG");
                $this->rule = RecaptchaV3Rule::getRuleByTag($tag);
            }

            if($tag && !$this->rule && $this->config()->get('auto_create_rule') ) {
                Logger::log("getRecaptchaV3Rule auto create from tag={$tag}", "DEBUG");
                // create from tag but do not enable it
                // for inspection by site owner who can enabled it manually
                RecaptchaV3Rule::createFromTag($tag, false);
            }
        }
        if($this->rule) {
            Logger::log("getRecaptchaV3Rule found rule #{$this->rule->ID}", "DEBUG");
        }
        return $this->rule;
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

        if(($errors = $this->getForm()->getSessionValidationResult()) && !$errors->isValid()) {
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
            'score' => $response->getResponseScore(),// the verification score from the API
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
     * Return the message when possible spam/bot found
     */
    public static function getMessagePossibleSpam() : string {
        return _t(
            'NSWDPC\SpamProtection.TOKEN_POSSIBLE_SPAM',
            'We have detected that the form may be a spam submission. Please try to submit the form again.'
        );
    }

    /**
     * Return the message when general failure occurs
     */
    public static function getMessageGeneralFailure() : string {
        return _t(
            'NSWDPC\SpamProtection.TOKEN_VERIFICATION_GENERAL_ERROR',
            'Sorry, the form submission failed. You may like to try again.'
        );
    }

    /**
     * Return the message when a timeout occurs
     */
    public static function getMessageTimeout() : string {
        return _t(
            'NSWDPC\SpamProtection.TOKEN_TIMEOUT',
            'Please check the information provided and submit the form again.'
        );
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

            $rule = $this->getRecaptchaV3Rule();
            $action = $this->getRecaptchaAction();
            $verifier = $this->getVerifier();
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
                } else if($response->isTimeout()) {
                    // on timeout always prompt for revalidation, in order to get a valid result to inspect
                    throw new RecaptchaVerificationException( self::getMessageTimeout() );
                } else if( $rule ) {
                    // Work out what action to take
                    switch($rule->ActionToTake) {
                        case RecaptchaV3Rule::TAKE_ACTION_ALLOW:
                        case RecaptchaV3Rule::TAKE_ACTION_CAUTION:
                            Logger::log("RecaptchaV3 verification failed. Allowing as rule '{$rule->Tag}' sets {$rule->ActionToTake}", "NOTICE");
                            return true;
                            break;
                        default:
                            throw new RecaptchaVerificationException( self::getMessagePossibleSpam() );
                            break;
                    }
                } else {
                    // No rule, fall back to BLOCK (prompt to resubmit)
                    throw new RecaptchaVerificationException( self::getMessagePossibleSpam() );
                }
                // end - TokenResponse handling

            } else {
                // general failure
                throw new \Exception("Verification failed - no/bad response from verify API");
            }

        } catch (RecaptchaVerificationException $e) {
            // catch actual verification fails
            $message = $e->getMessage();
        } catch (\Exception $e) {
            // set a general error
            Logger::log("RecaptchaV3Field: Exception={$e->getMessage()}", "INFO");
            $message = self::getMessageGeneralFailure();
        }
        // set error on form
        $this->getForm()->sessionError( $message );
        $validator->validationError( $this->getName(), $message, ValidationResult::TYPE_ERROR );
        $this->setSubmittedValue("");
        // fail validation
        return false;
    }

}

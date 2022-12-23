<?php

namespace NSWDPC\SpamProtection;

use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\ValidationException;
use SilverStripe\ORM\ValidationResult;

/**
 * Common methods for Captcha FormField instances
 * @author James
 */
trait CaptchaSupport {

    /**
     * Score for this field, if not provided, the configuration value will be used
     * @var float|null
     */
    protected $score = null;

    /**
     * If the action is already prefixed, don't auto-prefix it with the field ID
     */
    protected $has_prefixed_action = false;

    /**
     * Per instance execute_action
     * @param string
     */
    protected $field_execute_action = "";

    /**
     * @returns string
     */
    public function getSiteKey() : ?string
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
     * Implementations add their own requirements for loading a JS API script and any CSS required
     */
    public function addRequirements() : void {

    }

    /**
     * The implementation provides its own per instance script handling
     * This is used for explicit per-field event handling
     */
    protected function actionScript() : string {
        return '';
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
     * @returns self
     */
    public function setExecuteAction(string $action, bool $is_prefixed = false) : self {
        $this->field_execute_action = $action;
        $this->has_prefixed_action = $is_prefixed;
        return $this;
    }

    /**
     * Get the execution action for this field, if none is set use configuration
     * @returns string
     */
    public function getExecuteAction() : string {
        return $this->field_execute_action  ?
                $this->field_execute_action :
                $this->config()->get('execute_action');
    }

    /**
     * Returns the unique id to use in the customScript requirement
     * @returns string
     */
    public function getUniqueId() : string {
        return "captcha_execute_{$this->ID()}";
    }

    /**
     * Set a score for this instance, override in implementation
     * if score is supported
     */
    public function setScore(float $score = null) : self {
        $this->score = null;
        return $this;
    }

    /**
     * Score for field verification, override in implementation
     * if score is supported
     */
    public function getScore() : ?float {
        return $this->score;
    }

    /**
     * Returns the configured action name for this field, override in implementation
     * @returns string
     */
    public function getCaptchaAction() : string {
        return "";
    }

    /**
     * This method is retained for BC
     */
    public function getRecaptchaAction() : string {
        return $this->getCaptchaAction();
    }

    /**
     * Stores data from the TokenResponse model in session, for later analysis, only if a response is valid
     * This will be cleared when Form::clearFormState() is called as it uses .data
     */
    protected function storeResponseToSession($token, TokenResponse $response) : void {
        $request = Controller::curr()->getRequest();
        $session = $request->getSession();
        $data = [
            'token' => $token,
            'score' => $response->getResponseScore(),// @var float|null
            'hostname' => $response->getResponseHostname(),
            'action' => $response->getResponseAction()
        ];
        $session->set( $this->config()->get('session_key'), $data);
    }

    /**
     * Remove any previous session data
     */
    protected function clearSessionResponse($session = null) : void {
        $session = $session ?? Controller::curr()->getRequest()->getSession();
        $session_key = $this->config()->get('session_key');
        $session->clear( $session_key );
    }

    /**
     * Get response from session
     * @return mixed
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
     * Return the token value, for sending to the verification endpoint
     */
    public function getTokenValue() {
        return $this->Value();
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
            $token = $this->getTokenValue();
            // no token submitted with form
            if(!$token) {
                throw new \Exception( "CaptchaSupport::validate() - no token found" );
            }

            $action = $this->getCaptchaAction();
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
                }
                // timeout
                if($response->isTimeout()) {
                    // > timeout to submit form
                    throw new VerificationException( static::getMessageTimeout() );
                }
                throw new VerificationException( static::getMessagePossibleSpam() );
            }
            // general failure
            throw new \Exception("Verification failed - no/bad response from verify API");
        } catch (VerificationException $e) {
            // catch actual verification fails
            $message = $e->getMessage();
            Logger::log("VerificationException..." . $e->getMessage() );
        } catch (\Exception $e) {
            // set a general error
            $message = static::getMessageGeneralFailure();
            Logger::log("General exception..." . $e->getMessage(), "NOTICE" );
        }
        // set error on form
        $this->getForm()->sessionError( $message );
        $validator->validationError( $this->getName(), $message, ValidationResult::TYPE_ERROR );
        $this->setSubmittedValue("");
        // fail validation
        return false;
    }

}

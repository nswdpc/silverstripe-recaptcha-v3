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
     * Tag used to find the rule for validation
     * @var string
     */
    protected $recaptchaV3RuleTag = '';

    /**
     * Rule used for validation
     * @var RecaptchaV3Rule|null
     */
    protected $rule = null;

    /**
     * When true, a non-enabled RecaptchaV3Rule record will be created with a tag matching
     * the recaptchaV3RuleTag value assigned to this field
     * @var bool
     */
    private static $auto_create_rule = false;


    /**
     * Minimum refresh time for getting an updated/new token value
     * @var int milliseconds
     */
    protected $minRefreshTime = 30000;

    /**
     * @inheritdoc
     * Return rule attribute for visual validation
     */
    public function getDefaultAttributes($attributes = null) : array
    {
        $defaultAttributes = parent::getDefaultAttributes($attributes);
        $rule = $this->getRecaptchaV3Rule();
        if ($rule && $rule->exists()) {
            $defaultAttributes['data-rule'] = $rule->ID;
        }
        return $defaultAttributes;
    }

    /**
     * @inheritdoc
     *
     * Automatically sets the tag to be used for rule retrieval if none has been set on the field
     * See {@link FormExtension::getRecaptchaV3RuleTag()}
     *
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
        if (!$this->recaptchaV3RuleTag) {
            $this->setRecaptchaV3RuleTag($form->getRecaptchaV3RuleTag());
        }
        return parent::setForm($form);
    }

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
        if ($rule = $this->getRecaptchaV3Rule()) {
            $action = $rule->Action;
        } else {
            $action = $this->field_execute_action  ?
                    $this->field_execute_action :
                    $this->config()->get('execute_action');
            $prefix = "";
            if (!$this->has_prefixed_action) {
                $prefix = $this->ID() . self::ACTION_DELIMITER;
            }
            $action = $prefix . $action;
        }
        return $action;
    }

    /**
     * Returns the configured action name for this field, override in implementation
     * @returns string
     */
    public function getCaptchaAction() : string {
        throw new \RuntimeException("Implementations using this trait must implement the getCaptchaAction method");
    }

    /**
     * @deprecated this method is preserved for BC, use getExecuteAction instead
     */
    public function getRecaptchaAction() : string {
        return $this->getExecuteAction();
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
     * Set the tag to use on this field.
     * This is automatically set by the RecaptchaV3SpamProtector::getFormField
     * when it calls self::setForm()
     */
    public function setRecaptchaV3RuleTag(string $tag) : self
    {
        if ($this->recaptchaV3RuleTag && ($tag != $this->recaptchaV3RuleTag)) {
            // invalidate the rule as the tag changed
            $this->rule = null;
        }
        $this->recaptchaV3RuleTag = $tag;
        return $this;
    }

    /**
     * Return a rule defined by the tag set on this field
     */
    public function getRecaptchaV3Rule() : ?RecaptchaV3Rule
    {
        $tag = "";
        if (!$this->rule) {
            if ($tag = $this->recaptchaV3RuleTag) {
                $this->rule = RecaptchaV3Rule::getRuleByTag($tag);
            }

            if ($tag && !$this->rule && $this->config()->get('auto_create_rule')) {
                // create from tag but do not enable it
                // for inspection by site owner who can enabled it manually
                RecaptchaV3Rule::createFromTag($tag, false);
            }
        }
        return $this->rule;
    }

    /**
     * Update the minimum refresh time, after which a token can be replaced with a new one
     * if the relevant event(s) are called
     * @param int $minRefreshTime milliseconds 5000 = 5s
     */
    public function setMinRefreshTime(int $minRefreshTime) : self {
        if($minRefreshTime > 0) {
            $this->minRefreshTime = $minRefreshTime;
        }
        return $this;
    }

    /**
     * Get the refresh time for the token
     */
    public function getMinRefreshTime() : int {
        return $this->minRefreshTime;
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
     * Store data from the TokenResponse model in session
     * This will be cleared when Form::clearFormState() is called as it uses .data
     */
    protected function storeResponseToSession($token, TokenResponse $response) : void
    {
        $request = Controller::curr()->getRequest();
        $session = $request->getSession();
        $data = [
            'token' => $token,
            'score' => $response->getResponseScore(),// the verification score from the API
            'threshold' => $response->getScore(),// the threshold set for the test
            'hostname' => $response->getResponseHostname(),
            'action' => $response->getResponseAction()
        ];
        $session->set($this->config()->get('session_key'), $data);
    }

    /**
     * Remove any previous session data
     */
    protected function clearSessionResponse($session = null) : void
    {
        $session = $session ?? Controller::curr()->getRequest()->getSession();
        $session_key = $this->config()->get('session_key');
        $session->clear($session_key);
    }

    /**
     * Get response from session
     * @return mixed
     */
    public function getResponseFromSession(string $key = "")
    {
        $request = Controller::curr()->getRequest();
        $session = $request->getSession();
        $session_key = $this->config()->get('session_key');
        // store score for this token to session
        $data = $session->get($session_key);//
        // clear session once retrieved
        $this->clearSessionResponse($session);
        if (isset($data[$key])) {
            return $data[$key];
        } else {
            return $data;
        }
    }

    /**
     * Return the message when possible spam/bot found
     */
    public static function getMessagePossibleSpam() : string
    {
        return _t(
            'NSWDPC\SpamProtection.TOKEN_POSSIBLE_SPAM',
            'We have detected that the form may be a spam submission. Please try to submit the form again.'
        );
    }

    /**
     * Return the message when general failure occurs
     */
    public static function getMessageGeneralFailure() : string
    {
        return _t(
            'NSWDPC\SpamProtection.TOKEN_VERIFICATION_GENERAL_ERROR',
            'Sorry, the form submission failed. You may like to try again.'
        );
    }

    /**
     * Return the message when a timeout occurs
     */
    public static function getMessageTimeout() : string
    {
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

            $rule = $this->getRecaptchaV3Rule();
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
                    TokenResponse::logStat("isValid", true);
                    return true;
                } elseif ($response->isTimeout()) {
                    // > timeout to submit form
                    throw new VerificationException( static::getMessageTimeout() );
                } elseif ($rule) {
                    // Work out what action to take
                    switch ($rule->ActionToTake) {
                        case RecaptchaV3Rule::TAKE_ACTION_ALLOW:
                            TokenResponse::logStat("rule", ["fail" => true, "rule" => $rule->ID, "takeaction" => RecaptchaV3Rule::TAKE_ACTION_ALLOW]);
                            return true;
                        case RecaptchaV3Rule::TAKE_ACTION_CAUTION:
                            // Allow an extension to throw a RecaptchaVerificationException or continue
                            $this->extend('recaptchaFailWithCaution', $rule, $response);
                            TokenResponse::logStat("rule", ["fail" => true, "rule" => $rule->ID, "takeaction" => RecaptchaV3Rule::TAKE_ACTION_CAUTION]);
                            return true;
                        default:
                            throw new VerificationException(self::getMessagePossibleSpam());
                            break;
                    }
                } else {
                    // No rule, fall back to BLOCK (prompt to resubmit)
                    TokenResponse::logStat("default", "block");
                    throw new VerificationException( static::getMessagePossibleSpam() );
                }
            } else {
                // general failure
                throw new \Exception("Verification failed - no/bad response from verify API");
            }
        } catch (VerificationException $e) {
            // catch actual verification fails
            $message = $e->getMessage();
            Logger::log("VerificationException..." . $e->getMessage() );
        } catch (\Exception $e) {
            // set a general error
            $message = static::getMessageGeneralFailure();
            Logger::log("General exception..." . $e->getMessage(), "NOTICE" );
        }
        // create a form-wide validation error
        $validationResult = $validator->getResult();
        $validationResult->addError($message, ValidationResult::TYPE_ERROR, self::VALIDATION_ERROR_CODE);
        $this->setSubmittedValue("");
        Logger::log("Failed verification: " . $message, "INFO");
        // fail validation
        return false;
    }

}

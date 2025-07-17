<?php

namespace NSWDPC\SpamProtection;

use SilverStripe\Forms\HiddenField;
use SilverStripe\View\Requirements;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\Core\Config\Config;
use SilverStripe\Control\Controller;

/**
 * Provides a Recaptcha Form Field for use in forms
 * When the action is fired, the token retrieved from Recaptcha is added to the hidden value
 * When the form field is validated after submission, the token is verified
 * @author James <james.ellis@dpc.nsw.gov.au>
 */
class RecaptchaV3Field extends HiddenField
{
    use HasVerifier;

    /**
     * @var string
     * See validate()
     */
    public const VALIDATION_ERROR_CODE = "FORM_RECAPTCHAV3";

    /**
     * Site key, configured in project
     */
    private static string $site_key = '';

    /**
     * Script to use to load recaptcha API
     */
    private static string $script_render = "https://www.google.com/recaptcha/api.js";

    /**
     * Default action name to use
     */
    private static string $execute_action = "submit";

    /**
     * Used as fallback value for default, it specified/configured value is not valid
     * @var string
    */
    public const DEFAULT_ACTION = 'submit';

    /**
     * Per instance execute_action
     */
    private string $field_execute_action = "";

    /**
     * Session key for storing recaatcha response
     */
    private static string $session_key = "RecaptchaV3";

    /**
     * Field holder template to use
     */
    protected $fieldHolderTemplate = "RecaptchaV3Field_holder";


    /**
     * Score for this field, if not provided, the configuration value will be used
     */
    private ?float $score = null;

    /**
     * If the action is already prefixed, don't auto-prefix it with the field ID
     */
    private bool $has_prefixed_action = false;

    /**
     * Tag used to find the rule for validation
     * @var string
     */
    protected $recaptchaV3RuleTag = '';

    /**
     * Rule used for validation
     * @var RecaptchaV3Rule|null
     */
    protected $rule;

    /**
     * When true, a non-enabled RecaptchaV3Rule record will be created with a tag matching
     * the recaptchaV3RuleTag value assigned to this field
     */
    private static bool $auto_create_rule = false;


    /**
     * Minimum refresh time for getting an updated/new token value
     * @var int milliseconds
     */
    protected $minRefreshTime = 30000;


    /**
     * @inheritdoc
     */
    public function __construct($name, $title = null, $value = null)
    {
        parent::__construct($name, $title, $value);
    }

    /**
     * @inheritdoc
     * Return rule attribute for visual validation
     */
    #[\Override]
    public function getDefaultAttributes(): array
    {
        $defaultAttributes = parent::getDefaultAttributes();
        $rule = $this->getRecaptchaV3Rule();
        if ($rule && $rule->exists()) {
            $defaultAttributes['data-rule'] = $rule->ID;
        }

        return $defaultAttributes;
    }

    /**
     * Get default action, either from configuration or the fallback class constant
     */
    public static function getDefaultAction(): string
    {
        $action = self::config()->get('execute_action');
        if (TokenResponse::isEmptyAction($action)) {
            $action = self::DEFAULT_ACTION;
        }

        return $action;
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
    #[\Override]
    public function setForm($form)
    {
        if (!$this->recaptchaV3RuleTag) {
            $this->setRecaptchaV3RuleTag($form->getRecaptchaV3RuleTag());
        }

        return parent::setForm($form);
    }

    /**
     * Get the configured site key
     */
    public function getSiteKey(): ?string
    {
        return $this->config()->get('site_key');
    }

    /**
     * Returns a specific field holder template, for instance we may want to add some
     * buzz about the form being protected by Recaptcha, some links to assistance pages
     *
     * @inheritdoc
     */
    #[\Override]
    public function FieldHolder($properties = [])
    {
        $context = $this;

        $this->extend('onBeforeRenderHolder', $context, $properties);

        if (count($properties) !== 0) {
            $context = $this->customise($properties);
        }

        return $context->renderWith($this->getFieldHolderTemplates());
    }


    /**
     * Returns the field, sets requirements for this form
     * @inheritdoc
     */
    #[\Override]
    public function Field($properties = [])
    {
        $field = parent::Field($properties);
        $this->addRequirements();
        return $field;
    }

    /**
     * Override the execute action configuration
     */
    public function setExecuteAction(string $action, bool $is_prefixed = false): self
    {
        $this->field_execute_action = $action;
        $this->has_prefixed_action = $is_prefixed;
        return $this;
    }

    /**
     * Get the execution action for this field, if none is set use configuration
     */
    public function getExecuteAction(): string
    {
        return $this->field_execute_action !== '' ? $this->field_execute_action : self::getDefaultAction();
    }

    /**
     * Returns the configured action name for this form
     * If a rule is present, this value is used
     */
    public function getRecaptchaAction(): string
    {
        if (($rule = $this->getRecaptchaV3Rule()) instanceof \NSWDPC\SpamProtection\RecaptchaV3Rule) {
            $action = $rule->Action;
        } else {
            $prefix = "";
            if (!$this->has_prefixed_action) {
                $prefix = $this->ID() . "/";
            }

            $action = $prefix . $this->getExecuteAction();
        }

        return TokenResponse::formatAction($action);
    }

    /**
     * Returns the unique id to use in the customScript requirement
     */
    public function getUniqueId(): string
    {
        return "recaptcha_execute_{$this->ID()}";
    }

    /**
     * Set a score for this instance
     */
    public function setScore(float $score): self
    {
        $score = TokenResponse::validateScore($score);
        $this->score = $score;
        return $this;
    }

    /**
     * Score for field verification
     */
    public function getScore(): float
    {
        if (($rule = $this->getRecaptchaV3Rule()) instanceof \NSWDPC\SpamProtection\RecaptchaV3Rule) {
            // a rule score is an int between 0 and 100
            return round($rule->Score / 100, 2);
        } elseif (is_null($this->score)) {
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
     */
    public function setRecaptchaV3RuleTag(string $tag): self
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
    public function getRecaptchaV3Rule(): ?RecaptchaV3Rule
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
    public function setMinRefreshTime(int $minRefreshTime): self
    {
        if ($minRefreshTime > 0) {
            $this->minRefreshTime = $minRefreshTime;
        }

        return $this;
    }

    /**
     * Get the refresh time for the token
     */
    public function getMinRefreshTime(): int
    {
        return $this->minRefreshTime;
    }

    /**
     * Get the requirements for this particular field
     */
    protected function addRequirements(): void
    {
        $site_key = $this->config()->get('site_key');
        Requirements::javascript($this->config()->get('script_render'). "?render={$site_key}");
        // the handler script
        Requirements::javascript('nswdpc/silverstripe-recaptcha-v3:client/static/js/handler.js');
        // load the template Javascript for this field
        Requirements::customScript($this->actionScript(), $this->getUniqueId());
    }

    /**
     * The execution script for the action associated with this form
     * grecaptcha.execute() is fired  when a field is focused for the first time then at some time $threshold milliseconds after that
     * Tokens time out after 2 minutes, refreshing the token will assist in reducing token timeouts on longer forms
     * @returns string
     */
    protected function actionScript(): string
    {
        $siteKey = $this->config()->get('site_key');
        $configuration = [
            'action' => $this->getRecaptchaAction()
        ];

        $minRefreshTime = $this->getMinRefreshTime();

        /*
         * when an error occurs and the form is re-loaded with values
         * the user may press submit again with no token sent due to no events being fired
         * refresh the token right away in that case
         */
        $refreshOnError = "";
        if (($form = $this->getForm()) && ($errors = $form->getSessionValidationResult()) && !$errors->isValid()) {
            $refreshOnError = ".execute()";
        }

        $options = json_encode([
            'id' => $this->ID(),
            'siteKey' => $siteKey,
            'configuration' => $configuration,
            'threshold' => $minRefreshTime
        ]);

        if ($siteKey) {
            $js = <<<JS
            grecaptcha.ready(function() {
                try {
                    let handler = new RecaptchaV3Handler();
                    handler.init({$options}).bindEvents(){$refreshOnError};
                } catch (e) {
                    console.warn(e);
                }
            });
            JS;
        } else {
            $js = "console.warn('no site key configured');";
        }

        return $js;
    }

    /**
     * Store data from the TokenResponse model in session
     * This will be cleared when Form::clearFormState() is called as it uses .data
     */
    protected function storeResponseToSession($token, TokenResponse $response): void
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
    protected function clearSessionResponse($session = null): void
    {
        $session ??= Controller::curr()->getRequest()->getSession();
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
    public static function getMessagePossibleSpam(): string
    {
        return _t(
            'NSWDPC\SpamProtection.TOKEN_POSSIBLE_SPAM',
            'We have detected that the form may be a spam submission. Please try to submit the form again.'
        );
    }

    /**
     * Return the message when general failure occurs
     */
    public static function getMessageGeneralFailure(): string
    {
        return _t(
            'NSWDPC\SpamProtection.TOKEN_VERIFICATION_GENERAL_ERROR',
            'Sorry, the form submission failed. You may like to try again.'
        );
    }

    /**
     * Return the message when a timeout occurs
     */
    public static function getMessageTimeout(): string
    {
        return _t(
            'NSWDPC\SpamProtection.TOKEN_TIMEOUT',
            'Please check the information provided and submit the form again.'
        );
    }

    /**
     * Validate the field
     * @see https://developers.google.com/recaptcha/docs/verify#error_code_reference
     * @inheritdoc
     */
    #[\Override]
    public function validate(): ValidationResult
    {
        try {

            $validator = parent::validate();

            // clear previous attempts
            $this->clearSessionResponse();
            $message = '';
            // the token set by the script in executionScript()
            $token = $this->getValue();
            // no token submitted with form
            if (!$token) {
                throw new \Exception("No token for this field ({$this->getName()})");
            }

            $rule = $this->getRecaptchaV3Rule();
            $action = $this->getRecaptchaAction();
            $verifier = $this->getVerifier();
            $response = $verifier->check($token, $this->getScore(), $action);

            // handle the response when it is a {@link NSWDPC\SpamProtection\TokenResponse}
            if ($response instanceof TokenResponse) {
                // successful verification
                if ($response->isValid()) {
                    // store token response score
                    $this->storeResponseToSession($token, $response);
                    // all good
                    $this->setSubmittedValue("");
                    TokenResponse::logStat("isValid", true);
                    return $validator;
                } elseif ($response->isTimeout()) {
                    // on timeout always prompt for revalidation, in order to get a valid result to inspect
                    throw new RecaptchaVerificationException(self::getMessageTimeout());
                } elseif ($rule instanceof \NSWDPC\SpamProtection\RecaptchaV3Rule) {
                    // Work out what action to take
                    switch ($rule->ActionToTake) {
                        case RecaptchaV3Rule::TAKE_ACTION_ALLOW:
                            TokenResponse::logStat("rule", ["fail" => true, "rule" => $rule->ID, "takeaction" => RecaptchaV3Rule::TAKE_ACTION_ALLOW]);
                            return $validator;
                        case RecaptchaV3Rule::TAKE_ACTION_CAUTION:
                            // Allow an extension to throw a RecaptchaVerificationException or continue
                            $this->extend('recaptchaFailWithCaution', $rule, $response);
                            TokenResponse::logStat("rule", ["fail" => true, "rule" => $rule->ID, "takeaction" => RecaptchaV3Rule::TAKE_ACTION_CAUTION]);
                            return $validator;
                        default:
                            throw new RecaptchaVerificationException(self::getMessagePossibleSpam());
                    }
                } else {
                    // No rule, fall back to BLOCK (prompt to resubmit)
                    TokenResponse::logStat("default", "block");
                    throw new RecaptchaVerificationException(self::getMessagePossibleSpam());
                }

                // end - TokenResponse handling
            } else {
                // general failure
                TokenResponse::logStat("tokenresponse", false);
                throw new \Exception("Verification failed - no/bad response from verify API");
            }
        } catch (RecaptchaVerificationException $e) {
            // catch actual verification fails
            $message = $e->getMessage();
            Logger::log("RecaptchaV3 failed verification: " . $message, "INFO");
        } catch (\Exception $e) {
            // set a general error
            Logger::log("RecaptchaV3 general error: " . $e->getMessage(), "NOTICE");
            $message = self::getMessageGeneralFailure();
        }

        // create a form-wide validation error
        $validator->addError($message, ValidationResult::TYPE_ERROR, self::VALIDATION_ERROR_CODE);
        $this->setSubmittedValue("");
        // fail validation
        return $validator;
    }
}

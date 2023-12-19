<?php

namespace NSWDPC\SpamProtection;

use Silverstripe\Core\Config\Config;
use Silverstripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\SpamProtection\SpamProtector;
use SilverStripe\View\ArrayData;
use SilverStripe\View\Requirements;
use SilverStripe\View\TemplateGlobalProvider;

/**
 * Spam protector class, when set and you call $form->enableSpamProtection()
 * The RecaptchaV3Field will be added to the form
 * @author James Ellis <james.ellis@dpc.nsw.gov.au>
 */
class RecaptchaV3SpamProtector implements SpamProtector, TemplateGlobalProvider
{
    use Configurable;

    /**
     * @var int
     */
    private static $steps = 5;

    /**
     * @var int
     */
    private static $default_name = "recaptcha_protector";

    /**
     * @var string
     */
    protected $execute_action = "";//default execute action

    /**
     * @var null|int
     */
    protected $threshold = null;//default threshold (use config value)

    /**
     * Badge display options: empty string, 'form' or 'page'
     * If page it is up to you to to include NSWDPC/SpamProtection/PageBadge in your template in the appropriate location
     * See: https://developers.google.com/recaptcha/docs/faq#id-like-to-hide-the-recaptcha-badge.-what-is-allowed
     * @param string
     */
    private static $badge_display = "";

    const BADGE_DISPLAY_DEFAULT = '';// use the reCAPTCHAv3 library default (fixed bottom right)
    const BADGE_DISPLAY_FIELD = 'field';// display the badge text in the form, above the actions
    const BADGE_DISPLAY_FORM = 'form';// badge is displayed in form. NB: requires custom form template
    const BADGE_DISPLAY_PAGE = 'page';// display the badge text in the page somewhere

    /**
     * Used as fallback value for default, it specified value is not valid
     * @deprecated use
     * @var int
    */
    const DEFAULT_THRESHOLD = 50;

    /*
     * Return the RecaptchaV3Field instance to use for this form
     * @return RecaptchaV3Field
     */
    protected function getRecaptchaV3Field($name = null, $title = null, $value = null) : RecaptchaV3Field
    {
        $field = Injector::inst()->createWithArgs(
            RecaptchaV3Field::class,
            ['name' => $name, 'title' => $title, 'value' => $value]
        );
        return $field;
    }

    /**
     * Return the field for the spam protector
     * @return RecaptchaV3Field
     */
    public function getFormField($name = null, $title = null, $value = null) : RecaptchaV3Field
    {
        if (!$name) {
            $name = $this->default_name;
        }
        // Get the spam protection field to use
        $field = $this->getRecaptchaV3Field($name, $title, $value);

        // check if the threshold provided is in bounds
        if(!self::isValidThreshold($this->threshold)) {
            $this->threshold = self::getDefaultThreshold();
        }
        $field->setScore(round(($this->threshold / 100), 2)); // format for the reCAPTCHA API 0.00->1.00
        $field->setExecuteAction($this->execute_action, true);
        return $field;
    }

    /**
     * Provide a way to modify score and threshold from the 'mapping' option
     * provided by {@link FormSpamProtectionExtension::enableSpamProtection}
     * These values can be overridden by an enabled RecaptchaV3Rule matching the FormName the field is in
     * @param mixed $fieldMapping
     * @return void
     */
    public function setFieldMapping($fieldMapping) : void
    {
        if (isset($fieldMapping['recaptchav3_options']['threshold'])) {
            // expected is an integer between 0 and 100
            $this->threshold = intval($fieldMapping['recaptchav3_options']['threshold']);
        }

        if (isset($fieldMapping['recaptchav3_options']['action'])) {
            // expected string, the action for analytics
            $this->execute_action = strval($fieldMapping['recaptchav3_options']['action']);
        }
    }

    /**
     * Based on the value set in configuration for {@link TokenResponse}, return a threshold based on that
     * If the configured value is out of bounds, the value of 70 is returned
     * @return int between 0 and 100 from configuration
     */
    public static function getDefaultThreshold() : int
    {
        // returns a float between 0 and 1.0
        $threshold = TokenResponse::getDefaultScore();
        // convert to int
        $threshold = $threshold * 100;
        // round to the number of steps expected here
        $steps = Config::inst()->get(self::class, 'steps');
        $threshold = round($threshold / $steps) * $steps;
        if (!self::isValidThreshold($threshold)) {
            // configuration value is out of bounds
            $threshold = self::DEFAULT_THRESHOLD;
        }
        return intval($threshold);
    }

    /**
     * Check if a threshold is valid
     */
    public static function isValidThreshold(?int $threshold) : bool {
        return !is_null($threshold) && $threshold >=0 && $threshold <= 100;
    }

    /**
     * Return range of allowed thresholds for use in forms
     * The values returned are a percentage - 100 = all, 0 = none
     * @return array
     */
    public static function getRange() : array
    {
        $min = 0;
        $max = 100;
        $steps = Config::inst()->get(self::class, 'steps');
        $default = self::getDefaultThreshold();
        $i = 5;
        $range = [];
        $range [ $min ] = $min . " (" . _t(__CLASS__ . ".BLOCK_LESS", "block less") . ")";
        while ($i < $max) {
            $value = $i;
            if ($i == $default) {
                $value .= " (" . _t(__CLASS__ . ".DEFAULT_FOR_THIS_SITE", "default for this website") . ")";
            }
            $range[ $i ] = $value;
            $i += $steps;
        }
        $range [ $max ] = $max . " (" . _t(__CLASS__ . ".BLOCK_MORE", "block more") . ")";
        return $range;
    }

    /**
     * Return an HTML list explaining response scores
     */
    public static function getRuleSummary() : string
    {
        return "<h3>" . _t('NSWDPC\SpamProtection.SCORE_EXAMPLE_HEADER', 'Verification score guide') . "</h3>"
        . "<ul>"
        . "<li>10: " . _t('NSWDPC\SpamProtection.SCORE_10', 'Very likely a bot/automated request') . "</li>"
        . "<li>30: " . _t('NSWDPC\SpamProtection.SCORE_30', 'Likely a bot/automated request') . "</li>"
        . "<li>70: " . _t('NSWDPC\SpamProtection.SCORE_70', 'Likely a non-automated request') . "</li>"
        . "<li>90: " . _t('NSWDPC\SpamProtection.SCORE_90', 'Very likely a non-automated request') . "</li>"
        . "</ul>";
    }


    /**
     * Get a dropdown field to allow user-selection of a score for a form
     */
    public static function getRangeField($name, $value = null) : DropdownField
    {
        return DropdownField::create(
            $name,
            _t(
                'NSWDPC\SpamProtection.SCORE_THRESHOLD_TITLE',
                'Set a form spam protection threshold'
            ),
            self::getRange(),
            $value
        )->setDescription(
            _t(
                'NSWDPC\SpamProtection.SCORE_THRESHOLD_DESCRIPTION',
                'Any requests receiving a verification score below the value selected will be blocked.'
            )
        );
    }

    /**
     * Get a CompositeField explaining more about the threshold selection
     */
    public static function getRangeCompositeField($name, $value = null) : CompositeField
    {
        return CompositeField::create(
            self::getRangeField($name, $value),
            LiteralField::create(
                'ThresholdCompositeLiteral',
                self::getRuleSummary()
            )
        )->setTitle(
            _t(
                'NSWDPC\SpamProtection.SCORE_COMPOSITE_TITLE',
                'Form spam threshold handling'
            )
        );
    }

    /**
     * Get a text field to allow user entry of an action for a form
     */
    public static function getActionField($name, $value = null) : TextField
    {
        return TextField::create(
            $name,
            _t('NSWDPC\SpamProtection.ACTION_HUMAN', 'Set a custom action'),
            $value
        )->setDescription(
            _t(
                'NSWDPC\SpamProtection.ACTION_DESCRIPTION',
                'This is used for analytics and trend analysis. '
                . ' Allowed characters are \'a-z 0-9 /\' '
                . 'and it may not be personally identifiable'
            )
        );
    }

    /**
     * @inheritdoc
     */
    public static function get_template_global_variables()
    {
        return [
            'ReCAPTCHAv3PrivacyInformation' => 'get_privacy_information',
            'ReCAPTCHAv3BadgeDisplay' => 'get_badge_display',
        ];
    }

    /**
     * Return the privacy information from a template
     */
    public static function get_privacy_information()
    {
        $displayOption = self::config()->get('badge_display');
        $value = '';
        switch ($displayOption) {
            case self::BADGE_DISPLAY_FORM:
            case self::BADGE_DISPLAY_FIELD:
            case self::BADGE_DISPLAY_PAGE:
                self::hideBadge();
                return ArrayData::create([
                    'DisplayOption' => $displayOption
                ])->renderWith('NSWDPC/SpamProtection/PrivacyInformation');
                break;
            case self::BADGE_DISPLAY_DEFAULT:
            default:
                // reCAPTCHAv3 handles badge display
                return '';
                break;
        }
    }

    /**
     * Return some information for templates to display the RecaptchaV3Badge
     * Returns an empty string, 'field', 'form' or 'page'
     */
    public static function get_badge_display() : string
    {
        return self::config()->get('badge_display');
    }

    /**
     * Hide badge via custom css
     */
    public static function hideBadge() : void
    {
        $css = ".grecaptcha-badge { visibility: hidden; }";
        Requirements::customCSS($css, 'recaptcha_badge_hide');
    }
}

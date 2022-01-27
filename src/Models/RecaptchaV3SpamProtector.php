<?php

namespace NSWDPC\SpamProtection;

use Silverstripe\Core\Config\Config;
use Silverstripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\SpamProtection\SpamProtector;

/**
 * Spam protector class, when set and you call $form->enableSpamProtection()
 * The RecaptchaV3Field will be added to the form
 * @author James Ellis <james.ellis@dpc.nsw.gov.au>
 */
class RecaptchaV3SpamProtector implements SpamProtector
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
    protected $execute_action = "autoprotection/submit";//default execute action

    /**
     * @var string
     */
    protected $threshold = -1;//default threshold (use config value)

    /**
     * Return the field for the spam protector
     * @return RecaptchaV3Field
     */
    public function getFormField($name = null, $title = null, $value = null)
    {
        if(!$name) {
            $name = $this->default_name;
        }
        $field = Injector::inst()->createWithArgs(
                RecaptchaV3Field::class,
                ['name' => $name, 'title' => $title, 'value' => $value]
        );

        // check if the threshold provided is in bounds
        if($this->threshold < 0 || $this->threshold > 100) {
            $this->threshold = self::getDefaultThreshold();
        }
        $field->setScore( round( ($this->threshold / 100), 2) ); // format for the reCAPTCHA API 0.00->1.00
        $field->setExecuteAction( $this->execute_action, true);
        return $field;
    }

    /**
     * In the RecaptchaV3 field, we use setFieldMapping to assign values to
     * the field prior to getFormField being called
     * @param mixed $fieldMapping
     * @return void
     */
    public function setFieldMapping($fieldMapping) {
        if(isset($fieldMapping['recaptchav3_options']['threshold'])) {
            // expected is an integer between 0 and 100
            $this->threshold = intval($fieldMapping['recaptchav3_options']['threshold']);
        }

        if(isset($fieldMapping['recaptchav3_options']['action'])) {
            // expected string, the action for analytics
            $this->execute_action = strval($fieldMapping['recaptchav3_options']['action']);
        }
    }

    /**
     * Based on the value set in configuration for {@link TokenRespone}, return a threshold based on that
     * If the configured value is out of bounds, the value of 70 is returned
     * @return int between 0 and 100 from configuration
     */
    public static function getDefaultThreshold() {
        // returns a float between 0 and 1.0
        $threshold = TokenResponse::getDefaultScore();
        // convert to int
        $threshold = $threshold * 100;
        // round to the number of steps expected here
        $steps = Config::inst()->get(self::class, 'steps');
        $threshold = round( $threshold / $steps) * $steps;
        if($threshold < 0 || $threshold > 100) {
            // configuration value is out of bounds
            $threshold = 70;
        }
        return $threshold;
    }

    /**
     * Return range of allowed thresholds for use in forms
     * The values returned are a percentage - 100 = all, 0 = none
     * @return array
     */
    public static function getRange() {
        $min = 0;
        $max = 100;
        $steps = Config::inst()->get(self::class, 'steps');
        $default = self::getDefaultThreshold();
        $i = 5;
        $range = [];
        $range [ $min ] = $min . " (" . _t(__CLASS__ . ".BLOCK_LESS", "block less") . ")";
        while($i < $max) {
            $value = $i;
            if($i == $default) {
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
    public static function getRuleSummary() : string {
        return "<ul>"
        . "<li>10: " . _t('NSWDPC\SpamProtection.SCORE_10','Very likely a bot/automated request') . "</li>"
        . "<li>30: " . _t('NSWDPC\SpamProtection.SCORE_30','Likely a bot/automated request') . "</li>"
        . "<li>70: " . _t('NSWDPC\SpamProtection.SCORE_70','Likely a non-automated request') . "</li>"
        . "<li>90: " . _t('NSWDPC\SpamProtection.SCORE_90','Very likely a non-automated request') . "</li>"
        . "</ul>";
    }


    /**
     * Get a dropdown field to allow user-selection of a score for a form
     * @return DropdownField
     */
    public static function getRangeField($name, $value = null) {
        return DropdownField::create(
            $name,
            _t(
                'NSWDPC\SpamProtection.SCORE_THRESHOLD_TITLE',
                'Set a reCAPTCHAv3 threshold. '
                . ' Any requests receiving a verification score below this will be blocked.'
            ),
            self::getRange(),
            $value
        )->setDescription(
            _t(
                'NSWDPC\SpamProtection.SCORE_THRESHOLD_DESCRIPTION',
                "Setting the threshold to 100 will block almost all submissions"
            )
        );
    }

    /**
     * Get a CompositeField explaining more about the threshold selection
     * @return CompositeField
     */
    public static function getRangeCompositeField($name, $value = null) {
        return CompositeField::create(
            self::getRangeField($name, $value),
            HeaderField::create(
                'ThresholdCompositeHeader',
                _t('NSWDPC\SpamProtection.SCORE_EXAMPLE_HEADER', 'Example verification scores')
            ),
            LiteralField::create(
                'ThresholdCompositeLiteral',
                self::getRuleSummary()
            )
        )->setTitle(
            _t(
                'NSWDPC\SpamProtection.SCORE_COMPOSITE_TITLE',
                'reCAPTCHAv3 threshold handling'
            )
        );
    }

    /**
     * Get a text field to allow user entry of an action for a form
     * @return TextField
     */
    public static function getActionField($name, $value = null) {
        return TextField::create(
            $name,
            _t( 'NSWDPC\SpamProtection.ACTION_HUMAN', 'Set a custom action'),
            $value
        )->setDescription(
            _t(
                'NSWDPC\SpamProtection.ACTION_DESCRIPTION',
                'This is used for analytics in the reCAPTCHA console. '
                . ' Allowed characters are \'a-z 0-9 /\' '
                . 'and it may not be personally identifiable'
            )
        );
    }
}

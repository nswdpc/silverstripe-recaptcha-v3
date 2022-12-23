<?php

namespace NSWDPC\SpamProtection;

use Silverstripe\Core\Config\Config;
use Silverstripe\Core\Config\Configurable;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\SpamProtection\SpamProtector;

/**
 * Abstract
 * @author James
 */
abstract class CaptchaSpamProtector implements SpamProtector
{

    use Configurable;

    /**
     * The number of steps in a range selection field
     * eg. 0,5,10...95,100
     * @var int
     */
    private static $steps = 5;

    /**
     * @var string
     */
    private static $default_name = "captcha_protector";

    /**
     * @var string
     */
    protected $execute_action = "submit";//default execute action

    /**
     * @var int
     */
    protected $threshold = -1;//default threshold (use config value)

    /**
     * In the RecaptchaV3 field, we use setFieldMapping to assign values to
     * the field prior to getFormField being called
     * The recaptcha_* naming is retained for BC reasons
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
    public static function getDefaultThreshold() : ?int {
        // returns a float between 0 and 1.0
        $threshold = TokenResponse::getDefaultScore();
        // convert to int
        $threshold = $threshold * 100;
        // round to the number of steps expected here
        $steps = Config::inst()->get(static::class, 'steps');
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
    public static function getRange() : array {
        $min = 0;
        $max = 100;
        $steps = Config::inst()->get(static::class, 'steps');
        $default = static::getDefaultThreshold();
        $i = 5;
        $range = [];
        $range [ $min ] = $min . " (" . _t("NSWDPC\SpamProtection.BLOCK_LESS", "block less") . ")";
        while($i < $max) {
            $value = $i;
            if($i == $default) {
                $value .= " (" . _t("NSWDPC\SpamProtection.DEFAULT_FOR_THIS_SITE", "default for this website") . ")";
            }
            $range[ $i ] = $value;
            $i += $steps;
        }
        $range [ $max ] = $max . " (" . _t("NSWDPC\SpamProtection.BLOCK_MORE", "block more") . ")";
        return $range;
    }

    /**
     * Get a dropdown field to allow user-selection of a score for a form
     * @return DropdownField
     */
    public static function getRangeField(string $name, $value = null) : ?DropdownField  {
        return DropdownField::create(
            $name,
            _t(
                'NSWDPC\SpamProtection.SCORE_HUMAN',
                'Set a threshold. '
                . ' Any submissions receiving a score below this will be blocked.'
            ),
            static::getRange(),
            $value
        )->setDescription(
            _t(
                'NSWDPC\SpamProtection.SCORE_DESCRIPTION_HUMAN',
                "Setting the threshold to 100 will block almost all submissions"
            )
        );
    }

    /**
     * Get a text field to allow user entry of an action for a form
     * @return TextField
     */
    public static function getActionField(string $name, $value = null) : ?TextField {
        return TextField::create(
            $name,
            _t( 'NSWDPC\SpamProtection.ACTION_HUMAN', 'Set a custom action'),
            $value
        )->setDescription(
            _t(
                'NSWDPC\SpamProtection.ACTION_DESCRIPTION',
                'This is used for analytics. It may not be personally identifiable'
            )
        );
    }

}

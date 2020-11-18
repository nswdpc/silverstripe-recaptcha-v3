<?php

namespace NSWDPC\SpamProtection;

use SilverStripe\SpamProtection\SpamProtector;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\DropdownField;

/**
 * Spam protector class, when set and you call $form->enableSpamProtection()
 * The RecaptchaV3Field will be added to the form
 * @author James Ellis <james.ellis@dpc.nsw.gov.au>
 */
class RecaptchaV3SpamProtector implements SpamProtector
{

    protected $default_name = "recaptcha_protector";

    protected $threshold = 70;// default threshold, anything under this is treated as spam
    protected $execute_action = "autoprotection/submit";//default execute action

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

        $default_threshold= 70;
        if($this->threshold < 0 || $this->threshold > 100) {
            $this->threshold = $default_threshold;
        }
        $threshold = round( ($this->threshold / 100), 2);
        $field->setScore( $threshold ); // format for the reCAPTCHA API 0.00->1.00
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
     * Return range of allowed thresholds for use in forms
     * The values returned are a percentage - 100 = all, 0 = none
     * @return array
     */
    public static  function getRange() {
        $min = 0;
        $max = 100;
        $steps = 5;
        $i = 5;
        $range = [];
        $range [ $min ] = $min . " (" . _t(__CLASS__ . ".BLOCK_LESS", "block less") . ")";
        while($i < $max) {
            $range[ $i ] = $i;
            $i += $steps;
        }
        $range [ $max ] = $max . " (" . _t(__CLASS__ . ".BLOCK_MORE", "block more") . ")";
        return $range;
    }

    /**
     * Get a dropdown field to allow user-selection of a score for a form
     * @return DropdownField
     */
    public static function getRangeField($name, $value = null) {
        return DropdownField::create(
            $name,
            _t(
                'NSWDPC\SpamProtection.SCORE_HUMAN',
                'Set a reCAPTCHAv3 threshold. '
                . ' Any submissions receiving a score below this will be blocked.'
            ),
            self::getRange(),
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

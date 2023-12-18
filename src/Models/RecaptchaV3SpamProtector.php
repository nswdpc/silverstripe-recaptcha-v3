<?php

namespace NSWDPC\SpamProtection;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\View\ArrayData;
use SilverStripe\View\Requirements;
use SilverStripe\View\TemplateGlobalProvider;

/**
 * When this is set as the default spam protected and you call $form->enableSpamProtection(),
 * the RecaptchaV3Field will be added to the form
 * This protector provides some specific handling around recaptcha badge display and threshold handling
 * @author James
 */
class RecaptchaV3SpamProtector extends CaptchaSpamProtector implements TemplateGlobalProvider
{

    /**
     * @var string
     */
    private static $default_name = "recaptcha_protector";

    /**
     * @var string
     */
    protected $execute_action = "";//default execute action

    /**
     * @var int
     */
    protected $threshold = -1;//default threshold (use config value)

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

    /*
     * Return the RecaptchaV3Field instance to use for this form
     * @return RecaptchaV3Field
     */
    public function getFormField($name = null, $title = null, $value = null) : RecaptchaV3Field
    {
        $field = Injector::inst()->createWithArgs(
            RecaptchaV3Field::class,
            ['name' => $name, 'title' => $title, 'value' => $value]
        );

        // check if the threshold provided is in bounds
        if($this->threshold < 0 || $this->threshold > 100) {
            $this->threshold = static::getDefaultThreshold();
        }
        $field->setScore( round( ($this->threshold / 100), 2) ); // format for the reCAPTCHA API 0.00->1.00
        $field->setExecuteAction( $this->execute_action, true);
        return $field;
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
                static::hideBadge();
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

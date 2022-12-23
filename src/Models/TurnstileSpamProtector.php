<?php

namespace NSWDPC\SpamProtection;

use SilverStripe\Core\Injector\Injector;

/**
 * Spam protector class for Turnstile.
 * When configured as the default spam protector and you call $form->enableSpamProtection(),
 * the TurnstileField will be added to the form
 * @author James
 */
class TurnstileSpamProtector extends CaptchaSpamProtector
{

    /**
     * @var string
     */
    private static $default_name = "turnstile_protector";

    /**
     * @var string
     */
    protected $execute_action = "autoprotection_submit";//default execute action

    /**
     * Return the field for the spam protector
     * @return TurnstileField
     */
    public function getFormField($name = null, $title = null, $value = null)
    {
        if(!$name) {
            $name = $this->config()->get('default_name');
        }
        $field = Injector::inst()->createWithArgs(
                TurnstileField::class,
                ['name' => $name, 'title' => $title, 'value' => $value]
        );
        return $field;
    }

}

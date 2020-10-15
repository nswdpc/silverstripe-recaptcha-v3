<?php

namespace NSWDPC\SpamProtection;

use SilverStripe\SpamProtection\SpamProtector;
use SilverStripe\Core\Injector\Injector;

/**
 * Spam protector class, when set and you call $form->enableSpamProtection()
 * The RecaptchaV3Field will be added to the form
 * @author James Ellis <james.ellis@dpc.nsw.gov.au>
 */
class RecaptchaV3SpamProtector implements SpamProtector
{

    protected $default_name = "recaptcha_protector";

    public function getFormField($name = null, $title = null, $value = null)
    {
        if(!$name) {
            $name = $this->default_name;
        }
        $field = Injector::inst()->createWithArgs(
                RecaptchaV3Field::class,
                ['name' => $name, 'title' => $title, 'value' => $value]
        );
        return $field;
    }

    /**
     * Unused
     */
    public function setFieldMapping($fieldMapping) {

    }
}

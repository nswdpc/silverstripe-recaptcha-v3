<?php

namespace NSWDPC\SpamProtection;

use Silverstripe\Core\Extension;

/**
 * Get a rule for this form
 * @author James
 */
class FormExtension extends Extension {

    /**
     * Get the tag to use for this form, for the purpose of finding
     * a RecaptchaV3Rule record matching the tag
     * A form can define a method 'getRecaptchaV3Tag' to return a custom tag
     * If that method does not exist or returns an empty string, the return value
     * of Form::FormName() is used
     * The tag is returned in lowercase
     * @return string
     */
    public function getRecaptchaV3RuleTag() : string {
        $tag = '';
        // Allow a form to specify a tag via code
        if( $this->owner->hasMethod('getRecaptchaV3Tag') ) {
            $tag = $this->owner->getRecaptchaV3Tag();
        }
        if(!$tag) {
            Logger::log("FormExtension::getRecaptchaV3RuleTag no getRecaptchaV3Tag, using FormName()", "DEBUG");
            $tag = $this->owner->FormName();
        }
        $tag = strtolower($tag);
        Logger::log("FormExtension::getRecaptchaV3RuleTag tag={$tag}", "DEBUG");
        return $tag;
;    }
}

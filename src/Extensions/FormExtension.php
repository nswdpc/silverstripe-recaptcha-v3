<?php

namespace NSWDPC\SpamProtection;

use SilverStripe\Core\Extension;

/**
 * Get a form spam rule for this form
 * @author James
 * @extends \SilverStripe\Core\Extension<(\SilverStripe\Forms\Form & static)>
 */
class FormExtension extends Extension
{
    /**
     * Get the tag to use for this form, for the purpose of finding
     * a RecaptchaV3Rule record matching the tag
     *
     * A form can define a method 'getRecaptchaV3Tag' to return a custom tag
     *
     * If that method does not exist or returns an empty string, the return value
     * of Form::FormName() is used
     *
     * The tag is returned in lowercase
     */
    public function getRecaptchaV3RuleTag(): string
    {
        $tag = '';
        // Allow a form to specify a tag via code
        if ($this->getOwner()->hasMethod('getRecaptchaV3Tag')) {
            /** @phpstan-ignore method.notFound */
            $tag = $this->getOwner()->getRecaptchaV3Tag();
        }

        if (!$tag) {
            // allow a form to set a tag via config API
            $tag = $this->getOwner()->config()->get('captcha_tag');
        }

        if (!$tag) {
            // fall back to form name
            $tag = $this->getOwner()->FormName();
        }

        return strtolower((string) $tag);
    }
}

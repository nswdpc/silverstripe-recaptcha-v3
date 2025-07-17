<?php

namespace NSWDPC\SpamProtection\Tests;

use NSWDPC\SpamProtection\RecaptchaV3Field;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\View\SSViewer;

/**
 * Test controller containing a form with a rule / tag
 * @author James
 */
class TestRecaptchaV3FormWithRuleController extends Controller implements TestOnly
{

    /**
     * @var string
     */
    protected $template = 'BlankPage';

    private static string $url_segment = 'TestRecaptchaV3FormWithRuleController';

    private static array $allowed_actions = [
        'Form',
        'TestFormSubmissionWithRule',
        'testRecaptchaVerify'
    ];

    const FIELD_VALUE = 'test-field-with-rule';

    /**
     * @return Form
     */
    public function Form()
    {
        return $this->TestFormSubmissionWithRule();
    }

    /**
     * Return the form using the spamprotection extension to create the field
     * @return Form
     */
    public function TestFormSubmissionWithRule()
    {
        $form = Form::create(
            $this,
            "TestFormSubmissionWithRule",
            FieldList::create(),
            FieldList::create(
                FormAction::create("testRecaptchaVerify")
            )
        );

        $options = [
            'name' => 'RecaptchaV3FieldWithRule'
        ];

        // Use FormSpamProtectionExtension
        $form->enableSpamProtection($options);
        $recaptchaField = $form->HiddenFields()->dataFieldByName($options['name']);
        if(!$recaptchaField instanceof RecaptchaV3Field) {
            throw new \UnexpectedValueException("Field is not a RecaptchaV3Field");
        }

        // use the TestVerifier
        $verifier = TestVerifier::create();
        $verifier->setIsHuman(true);

        $recaptchaField->setVerifier($verifier);
        $recaptchaField->setValue(self::FIELD_VALUE);
        return $form;
    }

    /**
     * store data on submission
     */
    public function testRecaptchaVerify($data, $form = null): \SilverStripe\Control\HTTPResponse
    {
        return $this->redirectBack();
    }

    #[\Override]
    public function getViewer($action = null)
    {
        return SSViewer::create($this->template);
    }
}

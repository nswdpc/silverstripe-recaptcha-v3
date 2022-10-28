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
 * Test controller containing a form with action and a single RecaptchaV3Field
 * @author James
 */
class TestRecaptchaV3FormHumanController extends Controller implements TestOnly
{

    /**
     * @var string
     */
    protected $template = 'BlankPage';

    /**
     * @var string
     */
    private static $url_segment = 'TestRecaptchaV3FormHumanController';

    const FIELD_VALUE = 'test-field-for-human';

    /**
     * @var array
     */
    private static $allowed_actions = [
        'Form',
        'RecaptchaV3HumanTestForm',
        'testRecaptchaVerify'
    ];

    /**
     * @return Form
     */
    public function Form()
    {
        return $this->RecaptchaV3HumanTestForm();
    }

    /**
     * @return Form
     */
    public function RecaptchaV3HumanTestForm()
    {

        // Create a mock test verifier
        $verifier = TestVerifier::create();
        $verifier->setIsHuman(true);

        // Create field, set verifier as TestVerifier
        $field = RecaptchaV3Field::create('FunctionalVerificationTestHuman');
        $field->setExecuteAction("humantest/submit", true);
        $field->setVerifier($verifier);
        $field->setValue(self::FIELD_VALUE);

        $form = Form::create(
            $this,
            "RecaptchaV3HumanTestForm",
            FieldList::create(
                $field
            ),
            FieldList::create(
                FormAction::create("testRecaptchaVerify")
            )
        );

        // Ensure rule is correctly set
        $field->setForm($form);

        return $form;
    }

    /**
     * store data on submission
     */
    public function testRecaptchaVerify($data, $form = null)
    {
        return $this->redirectBack();
    }

    public function getViewer($action = null)
    {
        return new SSViewer($this->template);
    }
}

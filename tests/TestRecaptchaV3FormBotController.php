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
 * Test interaction with a simulated bot request
 * @author James
 */
class TestRecaptchaV3FormBotController extends Controller implements TestOnly
{

    /**
     * @var string
     */
    protected $template = 'BlankPage';

    /**
     * @var string
     */
    private static $url_segment = 'TestRecaptchaV3FormBotController';

    /**
     * @var array
     */
    private static $allowed_actions = [
        'Form',
        'RecaptchaV3BotTestForm',
        'testRecaptchaVerify'
    ];

    const FIELD_VALUE = 'test-field-for-bot';

    /**
     * @return Form
     */
    public function Form()
    {
        return $this->RecaptchaV3BotTestForm();
    }

    /**
     * @return Form
     */
    public function RecaptchaV3BotTestForm()
    {

        // Create a mock test verifier
        $verifier = TestVerifier::create();
        $verifier->setIsHuman(false);

        // Create field, set verifier as TestVerifier
        $field = RecaptchaV3Field::create('FunctionalVerificationTestBot');
        $field->setExecuteAction("bottest/submit", true);
        $field->setVerifier($verifier);
        $field->setValue(self::FIELD_VALUE);

        $form = Form::create(
            $this,
            "RecaptchaV3BotTestForm",
            FieldList::create(
                $field
            ),
            FieldList::create(
                FormAction::create("testRecaptchaVerify")
            )
        );

        // Ensure a RecaptchaV3Rule is correctly set
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

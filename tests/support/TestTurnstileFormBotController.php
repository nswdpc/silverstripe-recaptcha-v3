<?php

namespace NSWDPC\SpamProtection\Tests;

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
class TestTurnstileFormBotController extends Controller implements TestOnly
{

    /**
     * @var string
     */
    protected $template = 'BlankPage';

    /**
     * @var string
     */
    private static $url_segment = 'TestTurnstileFormBotController';

    /**
     * @var array
     */
    private static $allowed_actions = [
        'Form',
        'TurnstileBotTestForm',
    ];

    /**
     * @return Form
     */
    public function Form() {
        return $this->TurnstileBotTestForm();
    }

    /**
     * @return Form
     */
    public function TurnstileBotTestForm() {

        // Create a mock test verifier
        $verifier = TestTurnstileVerifier::create();
        $verifier->setIsHuman( false );

        $field = TestTurnstileField::create('FunctionalVerificationTestBot');
        $field->setExecuteAction("bottest_submit", true);
        $field->setVerifier($verifier);

        return Form::create(
            $this,
            "TurnstileBotTestForm",
            FieldList::create(
                $field
            ),
            FieldList::create(
                FormAction::create("testTurnstileVerify")
            )
        );
    }

    /**
     * store data on submission
     */
    public function testTurnstileVerify($data, $form = null)
    {
        return $this->redirectBack();
    }

    public function getViewer($action = null)
    {
        return new SSViewer( $this->template );
    }
}

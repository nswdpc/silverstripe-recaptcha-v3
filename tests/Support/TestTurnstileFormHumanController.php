<?php

namespace NSWDPC\SpamProtection\Tests\Support;

use NSWDPC\SpamProtection\TurnstileField;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\View\SSViewer;

/**
 * Test controller containing a form with action and a single TurnstileField
 * @author James
 */
class TestTurnstileFormHumanController extends Controller implements TestOnly
{

    /**
     * @var string
     */
    protected $template = 'BlankPage';

    /**
     * @var string
     */
    private static $url_segment = 'TestTurnstileFormHumanController';

    /**
     * @var array
     */
    private static $allowed_actions = [
        'Form',
        'TurnstileHumanTestForm'
    ];

    /**
     * @return Form
     */
    public function Form() {
        return $this->TurnstileHumanTestForm();
    }

    /**
     * @return Form
     */
    public function TurnstileHumanTestForm() {

        // Create a mock test verifier
        $verifier = TestTurnstileVerifier::create();
        $verifier->setIsHuman( true );

        $field = TestTurnstileField::create('FunctionalVerificationTestHuman');
        $field->setExecuteAction("humantest_submit", true);
        $field->setVerifier($verifier);

        return Form::create(
            $this,
            "TurnstileHumanTestForm",
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

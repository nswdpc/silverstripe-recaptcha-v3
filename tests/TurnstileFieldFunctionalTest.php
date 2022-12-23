<?php

namespace NSWDPC\SpamProtection\Tests;

use NSWDPC\SpamProtection\Verifier;
use NSWDPC\SpamProtection\TurnstileTokenResponse;
use NSWDPC\SpamProtection\TurnstileField;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\FunctionalTest;

/**
 * Functional test for the TurnstileField
 */
class TurnstileFieldFunctionalTest extends FunctionalTest
{

    protected static $fixture_file = null;

    protected static $use_draft_site = false;

    protected static $disable_themes = true;

    protected static $extra_controllers = [
        TestTurnstileFormHumanController::class,
        TestTurnstileFormBotController::class
    ];

    protected $usesDatabase = false;

    public function testFormSubmissionHuman()
    {
        // validate the controller has the test field and verifier
        $controller = TestTurnstileFormHumanController::create();

        $form = $controller->Form();
        $field = $form->HiddenFields()->fieldByName('FunctionalVerificationTestHuman');

        $this->assertInstanceOf(TestTurnstileField::class, $field, "Field is a TestTurnstileField");

        $this->assertInstanceOf(TestTurnstileVerifier::class,  $field->getVerifier(), "Field verifier is a TestTurnstileVerifier");

        // Submit the form
        $response = $this->get('TestTurnstileFormHumanController');
        $submitResponse = $this->submitForm($form->FormName(), null, []);
        $sessionResponse = $field->getResponseFromSession();

        $this->assertNotEmpty( $sessionResponse );
        $this->assertEquals( $field->Value(), $sessionResponse['token'] );
        $this->assertEquals( 'localhost', $sessionResponse['hostname'] );
        $this->assertEquals( 'test_submit', $sessionResponse['action'] );

    }

}

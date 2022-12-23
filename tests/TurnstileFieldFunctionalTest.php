<?php

namespace NSWDPC\SpamProtection\Tests;

use NSWDPC\SpamProtection\Tests\Support\TestTurnstileField;
use NSWDPC\SpamProtection\Tests\Support\TestTurnstileVerifier;
use NSWDPC\SpamProtection\Tests\Support\TestTurnstileFormHumanController;
use NSWDPC\SpamProtection\Tests\Support\TestTurnstileFormBotController;
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
        $this->assertEquals( 'humantest_submit', $sessionResponse['action'] );

    }

    public function testFormSubmissionBot()
    {
        // validate the controller has the test field and verifier
        $controller = TestTurnstileFormBotController::create();

        $form = $controller->Form();
        $field = $form->HiddenFields()->fieldByName('FunctionalVerificationTestBot');

        $this->assertInstanceOf(TestTurnstileField::class, $field, "Field is a TestTurnstileField");

        $this->assertInstanceOf(TestTurnstileVerifier::class,  $field->getVerifier(), "Field verifier is a TestTurnstileVerifier");

        // Submit the form
        $response = $this->get('TestTurnstileFormBotController');
        $submitResponse = $this->submitForm($form->FormName(), 'action_testTurnstileVerify', []);
        $sessionResponse = $field->getResponseFromSession();

        $this->assertEmpty( $sessionResponse, 'Session response is empty' );

        $this->assertTrue( strpos($submitResponse->getBody(), TurnstileField::getMessagePossibleSpam()) !== false, "Message contains possible spam response" );

    }

}

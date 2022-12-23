<?php

namespace NSWDPC\SpamProtection\Tests;

use NSWDPC\SpamProtection\Verifier;
use NSWDPC\SpamProtection\RecaptchaV3TokenResponse;
use NSWDPC\SpamProtection\RecaptchaV3Field;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\FunctionalTest;

/**
 * Functional test for the RecaptchaV3Field
 */
class RecaptchaV3FieldFunctionalTest extends FunctionalTest
{

    protected static $fixture_file = null;

    protected static $use_draft_site = false;

    protected static $disable_themes = true;

    protected static $extra_controllers = [
        TestRecaptchaV3FormHumanController::class,
        TestRecaptchaV3FormBotController::class
    ];

    protected $usesDatabase = false;

    protected function setUp(): void
    {
        parent::setUp();
        // default 'middle' score
        RecaptchaV3TokenResponse::config()->set('score', 0.5);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function testFormSubmissionHuman()
    {
        // validate the controller has the test field and verifier
        $controller = TestRecaptchaV3FormHumanController::create();

        $form = $controller->Form();
        $field = $form->HiddenFields()->fieldByName('FunctionalVerificationTestHuman');

        $this->assertInstanceOf(TestRecaptchaV3Field::class, $field, "Field is a TestRecaptchaV3Field");

        $this->assertInstanceOf(TestRecaptchaV3Verifier::class,  $field->getVerifier(), "Field verifier is a TestRecaptchaV3Verifier");

        // Submit the form
        $response = $this->get('TestRecaptchaV3FormHumanController');
        $submitResponse = $this->submitForm($form->FormName(), null, []);
        $sessionResponse = $field->getResponseFromSession();

        $this->assertNotEmpty( $sessionResponse );
        $this->assertEquals( $field->Value(), $sessionResponse['token'] );
        $this->assertEquals( TestRecaptchaV3Verifier::RESPONSE_HUMAN_SCORE, $sessionResponse['score'] );
        $this->assertEquals( 'localhost', $sessionResponse['hostname'] );
        $this->assertEquals( 'humantest/submit', $sessionResponse['action'] );

    }

    /**
     * Test a human being blocked
     */
    public function testFormSubmissionFalsePositive()
    {

        RecaptchaV3TokenResponse::config()->set('score', 1);

        // validate the controller has the test field and verifier
        $controller = TestRecaptchaV3FormHumanController::create();

        $form = $controller->Form();
        $field = $form->HiddenFields()->fieldByName('FunctionalVerificationTestHuman');

        $this->assertInstanceOf(TestRecaptchaV3Field::class, $field, "Field is a TestRecaptchaV3Field");

        $this->assertInstanceOf(TestRecaptchaV3Verifier::class,  $field->getVerifier(), "Field verifier is a TestRecaptchaV3Verifier");

        // Submit the form
        $response = $this->get('TestRecaptchaV3FormHumanController');
        $submitResponse = $this->submitForm($form->FormName(), 'action_testRecaptchaVerify', []);
        $sessionResponse = $field->getResponseFromSession();

        $this->assertEmpty( $sessionResponse, 'Session response is empty' );

        $this->assertTrue( strpos($submitResponse->getBody(), RecaptchaV3Field::getMessagePossibleSpam()) !== false, "Message contains possible spam response" );

    }

    public function testFormSubmissionBot()
    {
        // validate the controller has the test field and verifier
        $controller = TestRecaptchaV3FormBotController::create();

        $form = $controller->Form();
        $field = $form->HiddenFields()->fieldByName('FunctionalVerificationTestBot');

        $this->assertInstanceOf(TestRecaptchaV3Field::class, $field, "Field is a TestRecaptchaV3Field");

        $this->assertInstanceOf(TestRecaptchaV3Verifier::class,  $field->getVerifier(), "Field verifier is a TestRecaptchaV3Verifier");

        // Submit the form
        $response = $this->get('TestRecaptchaV3FormBotController');
        $submitResponse = $this->submitForm($form->FormName(), 'action_testRecaptchaVerify', []);
        $sessionResponse = $field->getResponseFromSession();

        $this->assertEmpty( $sessionResponse, 'Session response is empty' );

        $this->assertTrue( strpos($submitResponse->getBody(), RecaptchaV3Field::getMessagePossibleSpam()) !== false, "Message contains possible spam response" );

    }

}

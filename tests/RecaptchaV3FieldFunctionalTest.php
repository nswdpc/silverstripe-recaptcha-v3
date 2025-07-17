<?php

namespace NSWDPC\SpamProtection\Tests;

use NSWDPC\SpamProtection\Verifier;
use NSWDPC\SpamProtection\TokenResponse;
use NSWDPC\SpamProtection\RecaptchaV3Field;
use NSWDPC\SpamProtection\RecaptchaV3Rule;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\FunctionalTest;

/**
 * Functional test for the RecaptchaV3Field
 */
class RecaptchaV3FieldFunctionalTest extends FunctionalTest
{

    /**
     * @inheritdoc
     */
    protected static $fixture_file;

    /**
     * @inheritdoc
     */
    protected static $disable_themes = true;

    /**
     * @inheritdoc
     */
    protected static $extra_controllers = [
        TestRecaptchaV3FormHumanController::class,
        TestRecaptchaV3FormBotController::class
    ];

    /**
     * @inheritdoc
     */
    protected $usesDatabase = false;

    /**
     * @inheritdoc
     */
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        // default 'middle' score
        TokenResponse::config()->set('score', 0.5);
    }

    public function testFormSubmissionHuman(): void
    {
        // validate the controller has the test field and verifier
        $controller = TestRecaptchaV3FormHumanController::create();

        $form = $controller->Form();
        $field = $form->HiddenFields()->fieldByName('FunctionalVerificationTestHuman');

        $this->assertInstanceOf(RecaptchaV3Field::class, $field, "Field is a RecaptchaV3Field");

        $this->assertInstanceOf(TestVerifier::class, $field->getVerifier(), "Field verifier is a TestVerifier");

        // Submit the form
        $response = $this->get('TestRecaptchaV3FormHumanController');

        $needle = '"threshold":' . TestRecaptchaV3FormHumanController::MIN_REFRESH_TIME;
        $this->assertStringContainsString($needle, $response->getBody());

        $this->submitForm($form->FormName(), null, []);
        $sessionResponse = $field->getResponseFromSession();

        $this->assertNotEmpty($sessionResponse);
        $this->assertEquals($field->Value(), $sessionResponse['token']);
        $this->assertEquals(TestVerifier::RESPONSE_HUMAN_SCORE, $sessionResponse['score']);
        $this->assertEquals('localhost', $sessionResponse['hostname']);
        $this->assertEquals('humantest/submit', $sessionResponse['action']);
    }

    /**
     * Test a human being blocked
     */
    public function testFormSubmissionFalsePositive(): void
    {
        TokenResponse::config()->set('score', 1);

        // validate the controller has the test field and verifier
        $controller = TestRecaptchaV3FormHumanController::create();

        $form = $controller->Form();
        $field = $form->HiddenFields()->fieldByName('FunctionalVerificationTestHuman');

        $this->assertInstanceOf(RecaptchaV3Field::class, $field, "Field is a RecaptchaV3Field");

        $this->assertInstanceOf(TestVerifier::class, $field->getVerifier(), "Field verifier is a TestVerifier");

        // Submit the form
        $this->get('TestRecaptchaV3FormHumanController');
        $submitResponse = $this->submitForm($form->FormName(), 'action_testRecaptchaVerify', []);
        $sessionResponse = $field->getResponseFromSession();

        $this->assertEmpty($sessionResponse, 'Session response is empty');

        $this->assertTrue(str_contains($submitResponse->getBody(), RecaptchaV3Field::getMessagePossibleSpam()), "Message contains possible spam response");
    }

    public function testFormSubmissionBot(): void
    {
        // validate the controller has the test field and verifier
        $controller = TestRecaptchaV3FormBotController::create();

        $form = $controller->Form();
        $field = $form->HiddenFields()->fieldByName('FunctionalVerificationTestBot');

        $this->assertInstanceOf(RecaptchaV3Field::class, $field, "Field is a RecaptchaV3Field");

        $this->assertInstanceOf(TestVerifier::class, $field->getVerifier(), "Field verifier is a TestVerifier");

        // Submit the form
        $this->get('TestRecaptchaV3FormBotController');
        $submitResponse = $this->submitForm($form->FormName(), 'action_testRecaptchaVerify', []);
        $sessionResponse = $field->getResponseFromSession();

        $this->assertEmpty($sessionResponse, 'Session response is empty');

        $this->assertTrue(str_contains($submitResponse->getBody(), RecaptchaV3Field::getMessagePossibleSpam()), "Message contains possible spam response");
    }
}

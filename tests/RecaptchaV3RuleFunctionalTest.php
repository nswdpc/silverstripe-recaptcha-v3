<?php

namespace NSWDPC\SpamProtection\Tests;

use NSWDPC\SpamProtection\FormExtension;
use NSWDPC\SpamProtection\Verifier;
use NSWDPC\SpamProtection\TokenResponse;
use NSWDPC\SpamProtection\RecaptchaV3Field;
use NSWDPC\SpamProtection\RecaptchaV3Rule;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Forms\Form;
use SilverStripe\SpamProtection\Extension\FormSpamProtectionExtension;

/**
 * Functional test for the RecaptchaV3Rule
 */
class RecaptchaV3RuleFunctionalTest extends FunctionalTest
{

    protected static $fixture_file = null;

    protected static $disable_themes = true;

    protected static $extra_controllers = [
        TestRecaptchaV3FormWithRuleController::class
    ];

    protected static $required_extensions = [
        Form::class => [
            FormExtension::class,
            FormSpamProtectionExtension::class
        ]
    ];

    protected $usesDatabase = true;

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * Test a form submission using a rule for verification
     */
    public function testFormSubmissionWithRule()
    {

        // Create a rule for this form
        $rule = RecaptchaV3Rule::create([
            'Tag' => 'form_testformsubmissionwithrule',
            'Action' => 'functionaltest/ruleassociation',
            'ActionToTake' => RecaptchaV3Rule::TAKE_ACTION_CAUTION,
            'Score' => 72,
            'AutoCreated' => 0,
            'Enabled' => 1
        ]);
        $rule->write();

        // validate the controller has the test field and verifier
        $controller = TestRecaptchaV3FormWithRuleController::create();

        $form = $controller->Form();
        // the field created for the test
        $field = $form->HiddenFields()->fieldByName('RecaptchaV3FieldWithRule');
        $ruleUsed = $field->getRecaptchaV3Rule();
        $this->assertInstanceOf(RecaptchaV3Rule::class, $ruleUsed, "Rule is a RecaptchaV3Rule");
        $this->assertEquals($rule->ID, $ruleUsed->ID, "Rule is the rule created");

        $this->assertInstanceOf(TestVerifier::class,  $field->getVerifier(), "Field verifier is a TestVerifier");

        // Submit the form
        $response = $this->get('TestRecaptchaV3FormWithRuleController');
        $submitResponse = $this->submitForm($form->FormName(), 'action_testRecaptchaVerify', []);
        $sessionResponse = $field->getResponseFromSession();

        $this->assertNotEmpty( $sessionResponse, 'Session response is not empty' );
        $this->assertEquals( $field->Value(), $sessionResponse['token'] );
        $this->assertEquals( TestVerifier::RESPONSE_HUMAN_SCORE, $sessionResponse['score'] );
        $this->assertEquals( 'localhost', $sessionResponse['hostname'] );
        $this->assertEquals( $rule->Action, $sessionResponse['action'] );
        $this->assertEquals( round($rule->Score / 100, 2), $sessionResponse['threshold'] );
    }

}

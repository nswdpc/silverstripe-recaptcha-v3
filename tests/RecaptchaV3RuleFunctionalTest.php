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

    /**
     * @inheritdoc
     */
    protected static $fixture_file = null;

    /**
     * @inheritdoc
     */
    protected static $disable_themes = true;

    /**
     * @inheritdoc
     */
    protected static $extra_controllers = [
        TestRecaptchaV3FormWithRuleController::class
    ];

    /**
     * @inheritdoc
     */
    protected static $required_extensions = [
        Form::class => [
            FormExtension::class,
            FormSpamProtectionExtension::class
        ]
    ];

    /**
     * @inheritdoc
     */
    protected $usesDatabase = true;

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

        $template = $field->Field()->RAW();
        $this->assertStringContainsString("data-rule=\"{$rule->ID}\"", $template);

        $this->assertInstanceOf(TestVerifier::class, $field->getVerifier(), "Field verifier is a TestVerifier");

        // Submit the form
        $response = $this->get('TestRecaptchaV3FormWithRuleController');
        $submitResponse = $this->submitForm($form->FormName(), 'action_testRecaptchaVerify', []);
        $sessionResponse = $field->getResponseFromSession();

        $this->assertNotEmpty($sessionResponse, 'Session response is not empty');
        $this->assertEquals($field->Value(), $sessionResponse['token']);
        $this->assertEquals(TestVerifier::RESPONSE_HUMAN_SCORE, $sessionResponse['score']);
        $this->assertEquals('localhost', $sessionResponse['hostname']);
        $this->assertEquals($rule->Action, $sessionResponse['action']);
        $this->assertEquals(round($rule->Score / 100, 2), $sessionResponse['threshold']);
    }
}

<?php

namespace NSWDPC\SpamProtection\Tests;

use NSWDPC\SpamProtection\RecaptchaV3SpamProtector;
use NSWDPC\SpamProtection\RecaptchaV3Field;
use NSWDPC\SpamProtection\Verifier;
use NSWDPC\SpamProtection\TokenResponse;
use SilverStripe\Dev\SapphireTest;

/**
 * Test the RecaptchaV3Field
 * @author James
 */
class RecaptchaV3FieldTest extends SapphireTest
{

    /**
     * @inheritdoc
     */
    protected $usesDatabase = false;

    /**
     * @inheritdoc
     */
    public function setUp() : void
    {
        parent::setUp();
    }

    /**
     * Get a test field from the RecaptchaV3SpamProtector
     */
    public function testField()
    {
        $protector = new RecaptchaV3SpamProtector();
        $executeAction = 'prefix/testaction';
        $threshold = 55;
        $fieldMapping = [];
        $fieldMapping['recaptchav3_options']['threshold'] = $threshold;
        $fieldMapping['recaptchav3_options']['action'] = $executeAction;
        $protector->setFieldMapping($fieldMapping);
        $field = $protector->getFormField('Test', 'Test');

        $this->assertInstanceOf(RecaptchaV3Field::class, $field, 'Field is a RecaptchaV3Field');
        $this->assertEquals($executeAction, $field->getRecaptchaAction(), 'Execute action is correct');
        $this->assertEquals(round($threshold / 100, 2), $field->getScore(), "Score is correct");
    }

    public function testMinRefreshTime()
    {
        $field = RecaptchaV3Field::create('TestMinRefreshTime', 'Min Refresh Time Test');
        $expected = 2000;
        $field->setMinRefreshTime($expected);
        $this->assertEquals($expected, $field->getMinRefreshTime());
    }

    public function testInvalidMinRefreshTime()
    {
        $field = RecaptchaV3Field::create('TestInvalidMinRefreshTime', 'Invalid Min Refresh Time Test');
        $expected = $field->getMinRefreshTime();
        $field->setMinRefreshTime(-1000);
        $this->assertEquals($expected, $field->getMinRefreshTime());
    }

    /**
     * Test the execute action handling on the field, with/without prefix
     */
    public function testExecuteAction()
    {
        $expectedAction = "test/action";
        $field = RecaptchaV3Field::create('TestExecuteAction', 'Execute action test');
        $field->setExecuteAction($expectedAction, true);
        $executeAction = $field->getExecuteAction();
        $this->assertEquals($expectedAction, $executeAction);

        $recaptchaAction = $field->getRecaptchaAction();
        $this->assertEquals($expectedAction, $recaptchaAction);

        $expectedAction = "unprefixedaction";
        $field = RecaptchaV3Field::create('TestExecuteAction', 'Execute action test without prefix');
        $field->setExecuteAction($expectedAction, false);
        $executeAction = $field->getExecuteAction();
        $this->assertEquals($expectedAction, $executeAction);

        $recaptchaAction = $field->getRecaptchaAction();

        $this->assertEquals($field->ID() . "/" . $expectedAction, $recaptchaAction);
    }

    public function testUniqueID()
    {
        $field = RecaptchaV3Field::create('TestUniqID', 'UniqID test');
        $this->assertEquals("recaptcha_execute_TestUniqID", $field->getUniqueId());
    }

    public function testSetScore()
    {
        $field = RecaptchaV3Field::create('TestSetScore', 'Set score test');
        $field->setScore(0.7);
        $score = $field->getScore();
        $this->assertEquals(0.7, $score);

        try {
            $field->setScore(2.3);
        } catch (\Exception $e) {
            $this->assertEquals("Score should not be > 1", $e->getMessage());
        }

        try {
            $field->setScore(-0.1);
        } catch (\Exception $e) {
            $this->assertEquals("Score should not be < 0", $e->getMessage());
        }

    }

    public function testVerifier()
    {
        $field = RecaptchaV3Field::create('TestVerifier', 'Test verifier');
        $this->assertEquals(Verifier::class, get_class($field->getVerifier()));
    }
}

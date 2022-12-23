<?php

namespace NSWDPC\SpamProtection\Tests;

use NSWDPC\SpamProtection\TurnstileField;
use NSWDPC\SpamProtection\TurnstileVerifier;
use NSWDPC\SpamProtection\TurnstileTokenResponse;
use SilverStripe\Dev\SapphireTest;

/**
 * Test the TurnstileField
 * @author James
 */
class TurnstileFieldTest extends SapphireTest
{

    protected $usesDatabase = false;

    /**
     * Test the execute action handling on the field, with/without prefix
     */
    public function testExecuteAction()
    {
        $expectedAction = "test_action";
        $field = TurnstileField::create('TestExecuteAction', 'Execute action test');
        $field->setExecuteAction($expectedAction, true);
        $executeAction = $field->getExecuteAction();
        $this->assertEquals($expectedAction, $executeAction);

        $captchaAction = $field->getCaptchaAction();
        $this->assertEquals($expectedAction, $captchaAction);

        $expectedAction = "unprefixedaction";
        $field = TurnstileField::create('TestExecuteAction', 'Execute action test without prefix');
        $field->setExecuteAction($expectedAction, false);
        $executeAction = $field->getExecuteAction();
        $this->assertEquals($expectedAction, $executeAction);

        $captchaAction = $field->getCaptchaAction();
        $expectedCaptchaAction = substr($field->ID() . "_" . $expectedAction, 0, 32);
        $this->assertEquals( $expectedCaptchaAction, $captchaAction);

    }

    public function testUniqueID() {
        $field = TurnstileField::create('TestUniqID', 'UniqID test');
        $this->assertEquals("captcha_execute_TestUniqID", $field->getUniqueId());
    }

    public function testSetScore() {
        $field = TurnstileField::create('TestSetScore', 'Set score test');
        $field->setScore(0.7);
        $score = $field->getScore();
        $this->assertNull($score);
    }

    public function testVerifier() {
        $field = TurnstileField::create('TestVerifier', 'Test verifier');
        $this->assertEquals(TurnstileVerifier::class, get_class($field->getVerifier()));
    }


}

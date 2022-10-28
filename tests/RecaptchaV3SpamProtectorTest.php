<?php

namespace NSWDPC\SpamProtection\Tests;

use NSWDPC\SpamProtection\RecaptchaV3Rule;
use NSWDPC\SpamProtection\RecaptchaV3SpamProtector;
use NSWDPC\SpamProtection\RecaptchaV3Field;
use NSWDPC\SpamProtection\Verifier;
use NSWDPC\SpamProtection\TokenResponse;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\TextField;

/**
 * Test the RecaptchaV3SpamProtector handling
 * @author James
 */
class RecaptchaV3SpamProtectorTest extends SapphireTest
{
    protected $usesDatabase = false;

    public function testDefaultThreshold()
    {
        TokenResponse::config()->set('score', 0.7);
        $score = TokenResponse::getDefaultScore();
        $default = RecaptchaV3SpamProtector::getDefaultThreshold();
        $score = $score * 100;
        $this->assertEquals($score, $default, "Default should equal {$score}");
    }

    public function testRange()
    {
        $range = RecaptchaV3SpamProtector::getRange();
        $this->assertEquals(21, count($range));
    }
    /**
     * Test the range field return value
     */
    public function testRangeField()
    {
        $field = RecaptchaV3SpamProtector::getRangeField(100, 'test');
        $this->assertInstanceOf(DropdownField::class, $field);
    }

    /**
     * Test the range composite field return value
     */
    public function testRangeCompositeField()
    {
        $field = RecaptchaV3SpamProtector::getRangeCompositeField(100, 'test');
        $this->assertInstanceOf(CompositeField::class, $field);
    }

    /**
     * Test the custom action field
     */
    public function testActionField()
    {
        $field = RecaptchaV3SpamProtector::getActionField('CustomAction', 'Custom action');
        $this->assertInstanceOf(TextField::class, $field);
    }
}

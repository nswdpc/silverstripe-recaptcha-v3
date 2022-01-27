<?php

namespace NSWDPC\SpamProtection\Tests;

use NSWDPC\SpamProtection\RecaptchaV3Rule;
use NSWDPC\SpamProtection\RecaptchaV3SpamProtector;
use NSWDPC\SpamProtection\RecaptchaV3Field;
use NSWDPC\SpamProtection\Verifier;
use NSWDPC\SpamProtection\TokenResponse;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\ValidationException;

/**
 * Test the RecaptchaV3Field
 * @author James
 */
class RecaptchaV3RuleTest extends SapphireTest
{

    protected $usesDatabase = true;

    public function setUp() {
        parent::setUp();
    }

    public function testRecaptchaV3Rule() {

        $rule = RecaptchaV3Rule::create([
            'Tag' => 'testrule',
            'Enabled' => 1,
            'Action' => 'prefix/testrule',
            'ActionToTake' => RecaptchaV3Rule::TAKE_ACTION_BLOCK,
            'Score' => 80
        ]);
        $id = $rule->write();

        $this->assertNotEmpty( $id, 'Rule 1 was created' );

    }

    public function testRecaptchaV3RuleDuplicate() {

        $rule = RecaptchaV3Rule::create([
            'Tag' => 'test2rule',
            'Enabled' => 1,
            'Action' => 'prefix/test2rule',
            'ActionToTake' => RecaptchaV3Rule::TAKE_ACTION_BLOCK,
            'Score' => 80
        ]);
        $id = $rule->write();

        $this->assertNotEmpty( $id, 'Rule 2 was created' );

        try {
            $rule = RecaptchaV3Rule::create([
                'Tag' => 'test2rule',
                'Enabled' => 1,
                'Action' => 'prefix/test2rule',
                'ActionToTake' => RecaptchaV3Rule::TAKE_ACTION_BLOCK,
                'Score' => 80
            ]);
            $id = $rule->write();
        } catch (ValidationException $e) {
            $this->assertEquals(
                _t(
                    "NSWDPC\SpamProtection.TAG_EXISTS_ERROR",
                    "The tag '{tag}' already exists. Please edit it.",
                    [
                        'tag' => $rule->Tag
                    ]
                ),
                $e->getMessage(),
                'Validation exception was found with correct duplicate rule text'
            );
        }

    }

    /**
     * test a rule cannot be empty
     */
    public function testRecaptchaV3RuleEmpty() {

        try {
            $rule = RecaptchaV3Rule::create([
                'Tag' => '',
                'Enabled' => 1,
                'Action' => 'prefix/emptyrule',
                'ActionToTake' => RecaptchaV3Rule::TAKE_ACTION_BLOCK,
                'Score' => 80
            ]);
            $id = $rule->write();
        } catch (ValidationException $e) {
            $this->assertEquals(
                _t(
                    "NSWDPC\SpamProtection.TAG_REQUIRED_FOR_RULE",
                    "This rule requires a tag"
                ),
                $e->getMessage(),
                'Validation exception was found with correct empty rule text'
            );
        }

    }


    public function testRecaptchaV3RulesEnabled() {

        $total = 3;
        for($i = 0; $i < $total; $i++) {

            $rule = RecaptchaV3Rule::create([
                'Tag' => "test{$i}rule",
                'Enabled' => 1,
                'Action' => "prefix/test{$i}rule",
                'ActionToTake' => RecaptchaV3Rule::TAKE_ACTION_BLOCK,
                'Score' => 80
            ]);
            $id = $rule->write();
        }

        $rules = RecaptchaV3Rule::getEnabledRules();

        $this->assertEquals($total, $rules->count(), "Rules={$total}");

    }

    public function testRecaptchaV3RuleByTag() {

        $rule = RecaptchaV3Rule::create([
            'Tag' => "testenabledrule",
            'Enabled' => 1,
            'Action' => "prefix/testenabledrule",
            'ActionToTake' => RecaptchaV3Rule::TAKE_ACTION_BLOCK,
            'Score' => 80
        ]);
        $id = $rule->write();
        $foundRule = RecaptchaV3Rule::getRuleByTag("testenabledrule");
        $this->assertEquals($id, $foundRule->ID, "Rule by tag matches");


        $result = $rule->checkTagExists("testenabledrule");
        $this->assertFalse($result, "checkTagExists returns false when called from existing rule");

    }

    public function testRecaptchaV3SystemTag() {
        $sysTags = [
            'tag1',
            'tag2',
            'tag3',
        ];
        RecaptchaV3Rule::config()->set('system_tags', $sysTags);

        $rule = RecaptchaV3Rule::create([
            'Tag' => "testsystagrule",
            'SelectTag' => 'tag2',
            'Enabled' => 1,
            'Action' => "prefix/testsystagrule",
            'ActionToTake' => RecaptchaV3Rule::TAKE_ACTION_BLOCK,
            'Score' => 80
        ]);
        $id = $rule->write();

        $foundRule = RecaptchaV3Rule::getRuleByTag("tag2");

        $this->assertEquals("tag2", $foundRule->Tag);
    }


}

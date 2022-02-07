<?php

namespace NSWDPC\SpamProtection\Tests;

use NSWDPC\SpamProtection\RecaptchaV3Field;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;

/**
 * Test badge placement
 * @author James
 */
class RecaptchaV3FieldBadgePlacementTest extends SapphireTest
{

    protected $usesDatabase = false;

    public function testDefaultBadgePlacement() {
        Config::modify()->set(RecaptchaV3Field::class, 'badge_display', RecaptchaV3Field::BADGE_DISPLAY_DEFAULT);
        $field = RecaptchaV3Field::create(
            'test_default_badge'
        );
        $displayOption = $field->ShowRecaptchaV3Badge();
        $this->assertEquals(RecaptchaV3Field::BADGE_DISPLAY_DEFAULT, $displayOption, "ShowRecaptchaV3Badge returned empty");

        $template = $field->FieldHolder()->forTemplate();

        $this->assertTrue( strpos($template, "https://policies.google.com/privacy") === false, "Recatpcha policy link not in template");

        $this->assertTrue( strpos($template, "https://policies.google.com/terms") === false, "Recatpcha T&C link not in template");
    }

    public function testFormBadgePlacement() {
        Config::modify()->set(RecaptchaV3Field::class, 'badge_display', RecaptchaV3Field::BADGE_DISPLAY_FORM);
        $field = RecaptchaV3Field::create(
            'test_default_badge'
        );
        $displayOption = $field->ShowRecaptchaV3Badge();
        $this->assertEquals(RecaptchaV3Field::BADGE_DISPLAY_FORM, $displayOption, "ShowRecaptchaV3Badge returned form setting");

        $template = $field->FieldHolder()->forTemplate();

        $this->assertTrue( strpos($template, "https://policies.google.com/privacy") !== false, "Recatpcha policy link in template");

        $this->assertTrue( strpos($template, "https://policies.google.com/terms") !== false, "Recatpcha T&C link in template");
    }

    public function testPageBadgePlacement() {
        Config::modify()->set(RecaptchaV3Field::class, 'badge_display', RecaptchaV3Field::BADGE_DISPLAY_PAGE);
        $field = RecaptchaV3Field::create(
            'test_default_badge'
        );
        $displayOption = $field->ShowRecaptchaV3Badge();
        $this->assertEquals(RecaptchaV3Field::BADGE_DISPLAY_PAGE, $displayOption, "ShowRecaptchaV3Badge returned page setting");

        $template = $field->FieldHolder()->forTemplate();

        $this->assertTrue( strpos($template, "https://policies.google.com/privacy") === false, "Recatpcha policy link not in template");

        $this->assertTrue( strpos($template, "https://policies.google.com/terms") === false, "Recatpcha T&C link not in template");

    }
}

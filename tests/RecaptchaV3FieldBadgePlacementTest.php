<?php

namespace NSWDPC\SpamProtection\Tests;

use NSWDPC\SpamProtection\RecaptchaV3Field;
use NSWDPC\SpamProtection\RecaptchaV3SpamProtector;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;

/**
 * Test badge placement
 * @author James
 */
class RecaptchaV3FieldBadgePlacementTest extends SapphireTest
{

    /**
     * @inheritdoc
     */
    protected $usesDatabase = false;

    public function testDefaultBadgePlacement() {
        Config::modify()->set(RecaptchaV3SpamProtector::class, 'badge_display', RecaptchaV3SpamProtector::BADGE_DISPLAY_DEFAULT);
        $field = RecaptchaV3Field::create(
            'test_default_badge'
        );
        $displayOption = RecaptchaV3SpamProtector::get_badge_display();
        $this->assertEquals(RecaptchaV3SpamProtector::BADGE_DISPLAY_DEFAULT, $displayOption, "ShowRecaptchaV3Badge returned empty");

        $template = $field->FieldHolder()->forTemplate();

        $this->assertTrue( strpos($template, "https://policies.google.com/privacy") === false, "Recaptcha policy link not in template");

        $this->assertTrue( strpos($template, "https://policies.google.com/terms") === false, "Recaptcha T&C link not in template");
    }

    public function testFieldBadgePlacement() {
        Config::modify()->set(RecaptchaV3SpamProtector::class, 'badge_display', RecaptchaV3SpamProtector::BADGE_DISPLAY_FIELD);
        $field = RecaptchaV3Field::create(
            'test_field_badge'
        );
        $displayOption = RecaptchaV3SpamProtector::get_badge_display();
        $this->assertEquals(RecaptchaV3SpamProtector::BADGE_DISPLAY_FIELD, $displayOption, "ShowRecaptchaV3Badge returned field setting");

        $template = $field->FieldHolder()->forTemplate();

        $this->assertTrue( strpos($template, "https://policies.google.com/privacy") !== false, "Recaptcha policy link in template");

        $this->assertTrue( strpos($template, "https://policies.google.com/terms") !== false, "Recaptcha T&C link in template");
    }

    public function testFormBadgePlacement() {
        Config::modify()->set(RecaptchaV3SpamProtector::class, 'badge_display', RecaptchaV3SpamProtector::BADGE_DISPLAY_FORM);
        $field = RecaptchaV3Field::create(
            'test_form_badge'
        );
        $displayOption = RecaptchaV3SpamProtector::get_badge_display();
        $this->assertEquals(RecaptchaV3SpamProtector::BADGE_DISPLAY_FORM, $displayOption, "ShowRecaptchaV3Badge returned page setting");

        $template = $field->FieldHolder()->forTemplate();

        $this->assertTrue( strpos($template, "https://policies.google.com/privacy") === false, "Recaptcha policy link not in template");

        $this->assertTrue( strpos($template, "https://policies.google.com/terms") === false, "Recaptcha T&C link not in template");

    }

    public function testPageBadgePlacement() {
        Config::modify()->set(RecaptchaV3SpamProtector::class, 'badge_display', RecaptchaV3SpamProtector::BADGE_DISPLAY_PAGE);
        $field = RecaptchaV3Field::create(
            'test_page_badge'
        );
        $displayOption = RecaptchaV3SpamProtector::get_badge_display();
        $this->assertEquals(RecaptchaV3SpamProtector::BADGE_DISPLAY_PAGE, $displayOption, "ShowRecaptchaV3Badge returned page setting");

        $template = $field->FieldHolder()->forTemplate();

        $this->assertTrue( strpos($template, "https://policies.google.com/privacy") === false, "Recaptcha policy link not in template");

        $this->assertTrue( strpos($template, "https://policies.google.com/terms") === false, "Recaptcha T&C link not in template");

    }
}

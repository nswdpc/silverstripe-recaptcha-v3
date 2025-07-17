<?php

namespace NSWDPC\SpamProtection;

use SilverStripe\Admin\ModelAdmin;

/**
 * Admin for managing reCAPTCHAv3 configuration records
 * @author james
 */
class RecaptchaV3Admin extends ModelAdmin
{

    private static string $url_segment = 'form-spam';

    private static string $menu_title = 'Form spam';

    private static string $menu_icon_class = 'font-icon-p-shield';

    private static array $managed_models = [
        RecaptchaV3Rule::class
    ];
}

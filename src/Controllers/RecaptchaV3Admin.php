<?php

namespace NSWDPC\SpamProtection;

use SilverStripe\Admin\ModelAdmin;

/**
 * Admin for managing reCAPTCHAv3 configuration records
 * @author james
 */
class RecaptchaV3Admin extends ModelAdmin
{

    /**
     * @var string
     */
    private static $url_segment = 'recaptchav3';

    /**
     * @var string
     */
    private static $menu_title = 'reCAPTCHAv3';

    /**
     * @var string
     */
    private static $menu_icon_class = 'font-icon-cog';

    /**
     * @var array
     */
    private static $managed_models = [
        RecaptchaV3Rule::class
    ];

}

# Rule Management

+ [Index](./001_index.md)

Since v0.3, the module has the ability to add custom form protection rules via an administration section (permission required).

If no custom rules are added or found, the default threshold values are used.

## Adding a rule

1. Go to "Form spam" section in administration area
1. Hit "Add Captcha rule"
1. Select or add a tag
1. Enable the rule (if required)
1. Supply an action - this is a string like a tag used for analytics and trend analysis - example: `submit/accessrequest'
1. Choose an action to take - Block, Caution or Allow. The latter two will log failed requests at notice level and allow the submission
1. Choose a threshold from 0 (block nothing) to 100 (block everything)

❕ Importantly, adding a rule with a tag does not automatically add a captcha field to a form. The form must have a captcha field added to it or use `enableSpamProtection()` via the Silverstripe spam protector to make use of a rule. Adding a captcha field to a form is a decision to be made at a project level.

## Choosing a tag

The module provides the following default tags for selection:

+ lostpassword: used for lost password forms
+ changepassword: used for change password forms
+ login: used for login forms
+ register: used for any registration forms you may have
+ newslettersubscribe: user for any subscription forms you may have

You can enter your own tag name. A tag named `form_formname` will be linked to that form, provided it has spam protection enabled *and* when the recaptcha spam protector is the default spam protector (see below).

### Enable a rule on a form in code

1. Make sure the form has a Recaptcha field added or has called `enableSpamProtection()` when the recaptcha spam protector is the default spam protector.
1. In the administration section, add a rule that matches the name of the form e.g `form_subscribeform` (the lowercase value of the return value form `FormName()`

The following form will use a the values from the rule tagged `form_subscribeform`:

```php
$form = Form::create($controller, 'SubscribeForm', ... );
$form->enableSpamProtection();
```

### Enable a rule on a form in config

1. Make sure the form has a Recaptcha field added or has called `enableSpamProtection()` when the recaptcha spam protector is the default spam protector.
1. Add the configuration to your project, substitute your form class in:

```yml
MyApp\SubscribeForm:
  captcha_tag: appsubscribeform
```
Flush (flush=1) and add a rule with a tag of `appsubscribeform` to the administration section. Any spam protected form using that captcha_tag will use the named rule.

## Automatically creating rules

You can auto create rules for forms with Recaptcha spam protection enabled by changing this config value in a project's YML config file and flush:

```yml
---
Name: 'app-form-spam-protection'
After:
 - '#nswdpc_recaptchav3_spamprotection'
---
NSWDPC\SpamProtection\RecaptchaV3Field:
  # auto create a RecaptchaV3Rule record for the tag assigned to this field
  auto_create_rule: true
```

Any request that creates a form with spam protection enabled will auto create a non-enabled rule using the form name as a tag. Review the tag and modify it as your see fit.

This option is turned off by default, but it allows you to see which forms have spam protection enabled.

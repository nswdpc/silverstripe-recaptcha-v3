# Badge display

+ [Index](./001_index.md)
 
You can place the badge in one of four locations using the `NSWDPC\SpamProtection\RecaptchaV3SpamProtector.badge_display` configuration value:

```yaml
---
Name: 'my-spam-protector-config'
After:
 - '#nswdpc_recaptchav3_spamprotection'
---
NSWDPC\SpamProtection\RecaptchaV3SpamProtector:
  # options are '','field','form','page'
  badge_display: 'field'
```

## Default

Value: empty string

The badge will automatically appear in the lower right corner of the viewport.

## Field

Value: 'field'

The [replacement text](https://developers.google.com/recaptcha/docs/faq#id-like-to-hide-the-recaptcha-badge.-what-is-allowed) is automatically displayed adjacent to the hidden recaptcha input.

Unless you have a custom form template, the privacy information will display before the form actions.

The default badge is hidden using CSS. Calling `$ReCAPTCHAv3PrivacyInformation` in the template hides the default badge.

## Form

Value: 'form'
 
Provided you have a custom form template, the [replacement text](https://developers.google.com/recaptcha/docs/faq#id-like-to-hide-the-recaptcha-badge.-what-is-allowed) can be displayed in the form template, in a location of your choice:

```html
<%-- themes/my-project-theme/templates/SilverStripe/Forms/Includes/Form.ss --%>
<% if $ReCAPTCHAv3BadgeDisplay == 'form' %>
    {$ReCAPTCHAv3PrivacyInformation}
<% end_if %>
```

If you provide specific form templates, this will need to be added to each template, as required.

The default badge is hidden using CSS. Calling `$ReCAPTCHAv3PrivacyInformation` in the template hides the default badge.

### Direct include

You can also `<% include NSWDPC/SpamProtection/FormBadge %>` in a form template.

## Page

Value: 'page'

No badge or text is displayed. It's up to you to "include the reCAPTCHA branding visibly in the user flow" in your theme's page template.

Example:
```html
<footer>
<% if $ReCAPTCHAv3BadgeDisplay == 'page' %>
    {$ReCAPTCHAv3PrivacyInformation}
<% end_if %>
</footer>
```

The default badge is hidden using CSS. Calling `$ReCAPTCHAv3PrivacyInformation` in the template hides the default badge.

### Direct include

You can also `<% include NSWDPC/SpamProtection/PageBadge %>` in a page template.


[Back to the index](./001_index.md)

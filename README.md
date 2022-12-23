# Silverstripe captcha verification

This module handles verification of the quality of website interactions using reCAPTCHA compatible implementations - currently Google's reCAPTCHAv3 and Cloudflare's Turnstile services.

The module provides form fields that can be added to a form manually or added automatically via a Spam Protector configuration.

## Installation

Install via composer

```shell
composer require nswdpc/silverstripe-recaptcha-v3
```

## Requirements

See [composer.json](./composer.json), but in summary:

+ silverstripe/framework ^4
+ silverstripe/spamprotection ^3

## Further documentation and configuration examples

[Further documentation](docs/en/001_index.md) for tips on usage

## Form spam protection

### Manually

Add the field to the form as you would any other `FormField`:

```php
use NSWDPC\SpamProtection;

// ... create a $form

// Add a RecaptchaV3 field
$field = RecaptchaV3Field::create( 'MyRecaptchaField' );
$form->Fields()->push( $field );


// or Turnstile field
$field = TurnstileField::create( 'MyTurnstileField' );
$form->Fields()->push( $field );

```

### Via the Silverstripe Spam Protector API

There are two spam protectors available:

- `RecaptchaV3SpamProtector` adds Google's reCAPTCHAv3 as the default spam protector
- `TurnstileSpamProtector` adds Cloudflare's Turnstile as the default spam protector

Read the [silverstripe/spamprotection documentation](https://github.com/silverstripe/silverstripe-spamprotection#configuring) for more information on how this works.

### Using a field for silverstripe/userforms

The module [https://github.com/nswdpc/silverstripe-recaptcha-v3-userforms](nswdpc/silverstripe-recaptcha-v3-userforms) provides editable form fields for use in CMS-defined forms.

## License

[BSD-3-Clause](./LICENSE.md)

## Maintainers

+ [dpcdigital@NSWDPC:~$](https://dpc.nsw.gov.au)

## Bugtracker

We welcome bug reports, pull requests and feature requests on the Github Issue tracker for this project.

Please review the [code of conduct](./code-of-conduct.md) prior to opening a new issue.

## Development and contribution

If you would like to make contributions to the module please ensure you raise a pull request and discuss with the module maintainers.

Please review the [code of conduct](./code-of-conduct.md) prior to completing a pull request.

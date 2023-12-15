# Silverstripe captcha verification

This module handles verification of the quality of website interactions using reCAPTCHA compatible implementations - currently Google's reCAPTCHAv3 and Cloudflare's Turnstile services.

The module provides form fields that can be added to a form manually or added automatically via a Spam Protector configuration.

## Features

+ Project-based threshold set via Configuration API
+ Granular rules for protected forms
+ Manage badge placement

## Installation

Install via composer

```shell
composer require nswdpc/silverstripe-recaptcha-v3
```

## Requirements

See [composer.json](./composer.json), but in summary:

+ silverstripe/framework ^4.10.0
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

## Further documentation

+ [Further documentation](docs/en/001_index.md) for tips on usage
+ [Badge placement](docs/en/001_badge_display.md)
+ [Rule management](docs/en/003_rule_management.md)

## Configuration

To use the module, create a reCAPTCHA V3 site at the [reCAPTCHA Admin website](https://www.google.com/recaptcha/admin), once created your will be provided a site and secret key for that site both valid for the domains you add (and their subdomains).

It's a good idea to have site settings split between your environments to isolate the keys. In the reCAPTCHA site settings, add the domain(s) for the environment you are setting up along with any other desired options and Google users you share the project with.

Copy the site/secret key provided to your project configuration, e.g a `spamprotection.yml` file in your project `_config` directory.

```yaml
---
Name: 'my-project-spamprotector'
After:
  - '#nswdpc_recaptchav3_spamprotection'
---
# set RecaptchaV3SpamProtector as the default spam protector
SilverStripe\SpamProtection\Extension\FormSpamProtectionExtension:
  default_spam_protector: 'NSWDPC\SpamProtection\RecaptchaV3SpamProtector'
# RecaptchaV3 configuration values
# TokenResponse is a model to verify responses from the reCAPTCHA v3 API
NSWDPC\SpamProtection\TokenResponse:
  # Set your desired score -> 1 = good, 0 = spam
  score: 0.4
# Verifier is a model to handle token verification
NSWDPC\SpamProtection\Verifier:
  # Your secret key provided in the reCAPTCHA admin site settings area
  secret_key: 'abc123....'
# The field handles setup of the API and token retrieval
NSWDPC\SpamProtection\RecaptchaV3Field:
  ## Your site key provided in the reCAPTCHA admin site settings area
  site_key: 'zyx321.....'
  # Global action prefix for the field
  execute_action: 'submit'
NSWDPC\SpamProtection\RecaptchaV3SpamProtector:
  # Place the reCAPTCHA privacy text adjacent to the hidden input form field
  badge_display: 'field'
```

## Token retrieval

The behaviour triggering a token retrieval from the reCAPTCHAv3 API is a focus() event on the form. This avoids submit event race conditions with e.g frontend form validators.

When the first field in a form is focused, a token will be retrieved. If another field is focused in the form after 30 seconds, the token will be refreshed.

The latest token will be submitted with the form and validated, if it has expired, the visitor will be prompted to check and resubmit the form.

## License

[BSD-3-Clause](./LICENSE.md)

## Maintainers

+ [dpcdigital@NSWDPC:~$](https://dpc.nsw.gov.au)

## Bugtracker

We welcome bug reports, pull requests and feature requests on the Github Issue tracker for this project.

Please review the [code of conduct](./code-of-conduct.md) prior to opening a new issue.

## Security

If you have found a security issue with this module, please email digital[@]dpc.nsw.gov.au in the first instance, detailing your findings.

## Development and contribution

If you would like to make contributions to the module please ensure you raise a pull request and discuss with the module maintainers.

Please review the [code of conduct](./code-of-conduct.md) prior to completing a pull request.

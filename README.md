# Silverstripe reCAPTCHA V3 verification

This module handles verification of the quality of website interactions using reCAPTCHA v3. It contains a form field that can be added to a form manually or added automatically via a Spam Protector configuration.

You can also use the module to verify tokens/actions from non-form interactions with your website.

> The default score for verification is 0.5, you can change this in project configuration

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

See [composer.json](./composer.json)

## Form spam protection

### Manually

Add the field to the form as you would any other `FormField`:

```php
use NSWDPC\SpamProtection;

// ...

$field = RecaptchaV3Field::create( 'MyRecaptchaField' );
$form->Fields()->push( $field );
```

### Spam Protector

To set `RecaptchaV3SpamProtector` as default spam protector, add the following configuration section to your project configuration

```yaml
---
Name: 'project_spamprotection'
After:
  - '#nswdpc_recaptchav3_spamprotection'
---
# set RecaptchaV3SpamProtector as the default spam protector
SilverStripe\SpamProtection\Extension\FormSpamProtectionExtension:
  default_spam_protector: NSWDPC\SpamProtection\RecaptchaV3SpamProtector
```

Then calling `$form->enableSpamProtection()` will add the field to the form automatically. Read the [silverstripe/spamprotection documentation](https://github.com/silverstripe/silverstripe-spamprotection#configuring) for more information on how this works.

### Field for silverstripe/userforms modules

The module `nswdpc/silverstripe-recaptcha-v3-userforms` provides a userforms field

## Further documentation

+ [Further documentation](docs/en/001_index.md) for tips on usage
+ [Badge placement](docs/en/002_badge_display.md)
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

The behaviour triggering a token retrieval from the reCAPTCHAv3 API is a focus() event on the form.
This avoids submit event race conditions with e.g frontend form validators.

> Safari does not support focus event on form elements such as radios and checks. A change() event handler is used to support this.

When the first field in a form is focused, a token will be retrieved. If another field is focused in the form after the configured refresh time, the token will be refreshed.

The latest token will be submitted with the form and validated, if it has expired (tokens have a 2 minute lifetime), the visitor will be prompted to check and resubmit the form with a fresh token.

Token values are verified and give a score by the reCAPTCHAv3 API. Scores that meet the minimum threshold requirements will pass validation.

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

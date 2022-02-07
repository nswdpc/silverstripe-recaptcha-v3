# Silverstripe reCAPTCHA V3 verification

This module handles verification of the quality of website interactions using reCAPTCHA v3. It contains a form field that can be added to a form manually or added automatically via a Spam Protector configuration.

You can also use the module to verify tokens/actions from non-form interactions with your website.

> The default score for verification is 0.7, you can change this in project configuration

## Requirements

See [composer.json](./composer.json) but in summary:

+ silverstripe/framework ^4
+ silverstripe/spamprotection ^3

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

Then calling `$form->enableSpamProtection()` will add the field to the form automatically.

### Field for silverstripe/userforms modules

The module `nswdpc/silverstripe-recaptcha-v3-userforms` provides a userforms field

## Documentation
 * [Further documentation](docs/en/index.md) for tips on usage

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
  # Place the reCAPTCHA badge in the form
  badge_display: 'form'
```

## Token retrieval

The behaviour triggering a token retrieval from the reCAPTCHAv3 API is a focus() event on the form. This avoids submit event race conditions with e.g frontend form validators.

When the first field in a form is focused, a token will be retrieved. If another field is focused in the form after 30 seconds, the token will be refreshed.

The latest token will be submitted with the form and validated, if it has expired, the visitor will be prompted to check and resubmit the form.

## Installation

Install via composer

```
composer require nswdpc/silverstripe-recaptcha-v3
```

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

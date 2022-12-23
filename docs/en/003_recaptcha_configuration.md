# reCAPTCHA configuration

To use the reCAPTCHA field, first create a reCAPTCHA V3 site at the [reCAPTCHA Admin website](https://www.google.com/recaptcha/admin). Once created you will be provided a site and secret key valid for the domains you add.

It's a good idea to have site settings split between your environments to isolate the keys. In the reCAPTCHA site settings, add the domain(s) for the environment you are setting up along with any other desired options plus Google users you share the project with.

Copy the site/secret key provided to your project configuration, e.g a `spamprotection.yml` file in your project `app/_config` directory.

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
NSWDPC\SpamProtection\RecaptchaV3TokenResponse:
  # Set your desired score -> 1 = good, 0 = spam
  # The following will allow all 0.1 and 0.3 scored verifications through
  score: 0.4
# Verifier is a model to handle token verification
NSWDPC\SpamProtection\RecaptchaV3Verifier:
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

The behaviour triggering a token retrieval from the reCAPTCHAv3 API is a focus() event on the form to avoid submit event race conditions with e.g frontend form validators.

When the first field in a form is focused, a token will be retrieved. If another field is focused in the form after 30 seconds, the token will be refreshed.

The latest token will be submitted with the form and validated. If it has expired, the visitor will be prompted to check and resubmit the form.

Tokens have a 2 minute lifetime. Checking a token with an older lifetime will result in a `timeout-or-duplicate` error response

## Further configuration

### Setting a custom score

Set a score per verification/field instance. Any verification response with a score under this value will be considered a good interaction.

```
$field->setScore(3);// will throw an Exception
$field->setScore(-1);// will throw an Exception
$field->setScore('abc');// will throw an Exception
$field->setScore(0.2);// will override the default configuration value
$field->setScore(null);// will use the configuration value
```

[reCAPTCHA Documentation: Interpreting the score](https://developers.google.com/recaptcha/docs/v3#interpreting_the_score)

### Setting a custom execute action

To use a specific action, for analytics, set this via the field instance with an optional prefix.

```php
// setting with a prefix:
$field->setExecuteAction('myaction', 'prefix');
// resulting action:
// prefix/myaction

// Setting with no prefix:
$field->setExecuteAction('myaction');
// resulting action:
// {$field->ID()}/myaction
```
[Valid characters in a reCAPTCHA action are `a-z A-Z 0-9 /`](https://developers.google.com/recaptcha/docs/v3#actions)

## reCAPTCHA badge placement

See [badge display](./002_badge_display.md)

## Controller verification

(reCAPTCHA only)

The module provides a controller to verify tokens and actions beyond a standard form submission.

This can be used to verify non-form actions taken on your site such as clicking a button or loading a page

To verify the token, make a HTTP POST to `/recaptchaverify/check` on your site with the following POST params:

* `token` - the token returned from the grecaptcha.execute() call in Javascript
* `action` - optionally check the action as well to verify that token provided is linked to the same action

You cannot set a score via the controller method.

The controller will respond with an `application/json` content type. The JSON encoded response is as follows:

### Success

```json
{"result":"OK"}
```

Successful verifications will return a 200 response code

### Failure

```json
{"result":"FAIL"}
```

If the client request is bad, the response code will be 400, for server failures it will be 500


## Advanced usage using the Verifier and TokenResponse models

You can use these models if you are rolling your own verification handling. Use the field/controller for examples of use, along with the method documentation.

```php
$verifier = RecaptchaV3Verifier::create();
$result = $verifier->check(
                $token, // required
                $score, // optional 0-1
                $action // optional action
);
if($result === false) {
    // something went wrong
}
if($result->isValid()) {
    // token verified OK
} else {
    /**
     * Failed token check OR
     * Failed action verification OR
     * Failed score comparison
     */
}
```

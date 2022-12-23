# Turnstile configuration

The Turnstile implementation provided uses the implicit client side rendering [mentioned in the Turnstile documentation](https://developers.cloudflare.com/turnstile/get-started/client-side-rendering/).

To use the Turnstile implementation, you must first create a Turnstile project in the Cloudflare dashboard and store the site and secret key provided in configuration.

Turnstile will run in Managed, Non-Interactive, and Invisible mode depending on your selection in the Turnstile control panel on your Cloudflare dashboard. In 'Managed' mode, Turnstile will determine what challenge, if any, to present.

Check the Turnstile FAQs at https://developers.cloudflare.com/turnstile/frequently-asked-questions/ for the latest information.

> There is no score handling in Turnstile, a verification response only states whether the interaction recorded was good or bad.


```yaml
---
Name: 'my-project-spamprotector'
After:
  - 'nswdpc_turnstile_spamprotection'
---
# set TurnstileSpamProtector as the default spam protector
SilverStripe\SpamProtection\Extension\FormSpamProtectionExtension:
  default_spam_protector: 'NSWDPC\SpamProtection\TurnstileSpamProtector'
# Turnstile configuration values
# Verifier is a model to handle token verification
NSWDPC\SpamProtection\TurnstileVerifier:
  # Your secret key provided in the Turnstile admin site settings area
  secret_key: 'abc123....'
# The field handles setup of the API and token retrieval
NSWDPC\SpamProtection\TurnstileField:
  ## Your site key provided in the Turnstile admin site settings area
  site_key: 'zyx321.....'
  # Global action prefix for the field
  execute_action: 'submit'
```

## Token verification

When a form is submitted, the token is verified via the siteverify endpoint.

Tokens can expire after 300s, in which case the form will need to be resubmitted. Tokens can only be verified once.


### Setting a custom execute action

To use a specific action, for analytics, set this via the field instance with an optional prefix.

```php
// setting with a prefix:
$field->setExecuteAction('myaction', 'prefix');
// resulting action:
// prefix_myaction

// Setting with no prefix:
$field->setExecuteAction('myaction');
// resulting action:
// {$field->ID()}_myaction
```

Refer to the Turnstile documentation for allowed characters (currently 32 alphanumeric characters including _ and -).


## Advanced usage using the Verifier and TokenResponse models

You can use these models if you are rolling your own verification handling. Use the field/controller for examples of use, along with the method documentation.

```php
$verifier = TurnstileVerifier::create();
$result = $verifier->check(
                $token, // required
                null, // not supported
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
     * Failed action verification
     */
}
```

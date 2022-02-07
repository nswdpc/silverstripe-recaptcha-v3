# Documentation

> Tokens have a 2 minute lifetime. Checking a token with an older lifetime will result in a `timeout-or-duplicate` error response

## Further configuration

### Setting a custom score

You can configure a default score within the TokenResponse yaml configuration:

In addition, you can set a score per verification/field instance

```
$field->setScore(3);// will throw an Exception
$field->setScore(-1);// will throw an Exception
$field->setScore('abc');// will throw an Exception
$field->setScore(0.2);// will override the default configuration value
$field->setScore(null);// will use the configuration value
```

[reCAPTCHA Documentation: Interpreting the score](https://developers.google.com/recaptcha/docs/v3#interpreting_the_score)

>1.0 is very likely a good interaction, 0.0 is very likely a bot

### Setting a custom execute action prefix

To use an action different to global configuration, on a field instance call the following method, optionally with a prefix value

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
[Valid characters in an action are `a-z A-Z 0-9 /`](https://developers.google.com/recaptcha/docs/v3#actions)

## reCAPTCHA badge placement

You can place the badge in one of three locations using the `NSWDPC\SpamProtection\RecaptchaV3Field.badge_display` configuration value.

1. Empty string (default): The badge will automatically appear in the lower right corner of the viewport
1. `form`: the [replacement text](https://developers.google.com/recaptcha/docs/faq#id-like-to-hide-the-recaptcha-badge.-what-is-allowed) is displayed adjacent to the hidden recaptcha input. The default badge is hidden with CSS.
1. `page`: no badge or text is displayed. It's up to you to "include the reCAPTCHA branding visibly in the user flow". The default badge is hidden with CSS.

## Controller verification

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
$verifier = new Verifier();
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

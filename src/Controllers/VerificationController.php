<?php

namespace NSWDPC\SpamProtection;

use Silverstripe\Control\Director;
use Silverstripe\Control\Controller;
use Silverstripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use GuzzleHttp\Client;


/**
 * This is a verification controller that can be used to check tokens returned
 * from the grecaptcha.execute calls.
 * Note that if you check a token that is already checked you will get
 * a 'timeout-or-duplicate' error
 * This controller can be used for non form submission actions, e.g check a user action
 * @author James <james.ellis@dpc.nsw.gov.au>
 */
class VerificationController extends Controller {

    /**
     * If this is true, this controller is enabled
     */
    private static $enabled = false;

    private static $url_segment = "recaptchaverify";

    private static $allowed_actions = [
        'check' => true
    ];

    public function init() {
        parent::init();
    }

    /**
     * 403 on / requests
     */
    public function index(HTTPRequest $request) {
        $response = new HTTPResponse( json_encode(["result"=>"FAIL"]), 403 );
        $response->addHeader('Content-Type','application/json');
        return $response;
    }

    /**
     * The relative link for this controller
     * @returns string
     */
    public function Link($action = null) {
        return Controller::join_links(
            Director::baseURL(),
            $this->config()->get('url_segment'),
            $action
        );
    }

    /**
     * Handle verification requests
     * check expects a POST with the 'token' and an optional 'action'
     * When the action is present, the returned action from verification must match the provided action
     * When no token is provided, the HTTP response code is 403. On success = 200, On error = 500
     * Tokens time out after 2 minutes
     * @returns string JSON encoded response object with a key of 'result' and a value or 'OK' or 'FAIL'
     */
    public function check(HTTPRequest $request) {
        try {
            // the token is required
            // the controller must be enabled as well
            $token = $request->postVar('token');
            if(!$this->config()->get('enabled') || !$token) {
                // bad request
                $response = new HTTPResponse( json_encode(["result"=>"FAIL"]), 400 );
                $response->addHeader('Content-Type','application/json');
                return $response;
            }
            $action = $request->postVar('action');
            $score = null;
            $verifier = new Verifier();
            $result = $verifier->check($token, $score, $action);
            // handle the response when it is a {@link NSWDPC\SpamProtection\TokenResponse}
            if($result instanceof TokenResponse) {
                // a verification response from API
                if($result->isValid()) {
                    // all good
                    $response = new HTTPResponse( json_encode(["result"=>"OK"]), 200 );
                    $response->addHeader('Content-Type','application/json');
                    return $response;
                } else {
                    // bad request / timeout / verification failed
                    $response = new HTTPResponse( json_encode(["result"=>"FAIL"]), 400 );
                    $response->addHeader('Content-Type','application/json');
                    return $response;
                }
            }

            // general failure on checking
            throw new \Exception("Failed to verify");

        } catch (\Exception $e) {
            $response = new HTTPResponse( json_encode(["result"=>"FAIL"]), 500 );
            $response->addHeader('Content-Type','application/json');
            return $response;
        }
    }
}

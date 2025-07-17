<?php

namespace NSWDPC\SpamProtection;

use SilverStripe\Control\Director;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Config\Config;

/**
 * This is a verification controller that can be used to check tokens returned
 * from the grecaptcha.execute calls.
 * Note that if you check a token that is already checked you will get
 * a 'timeout-or-duplicate' error
 * This controller can be used for non form submission actions, e.g check a user action
 * @author James <james.ellis@dpc.nsw.gov.au>
 */
class VerificationController extends Controller
{
    use HasVerifier;

    /**
     * If this is true, this controller is enabled
     */
    private static bool $enabled = false;

    /**
     * urlsegment for this controller
     */
    private static string $url_segment = "recaptchaverify";

    private static array $allowed_actions = [
        'check' => true
    ];

    /**
     * @var Verifier
     */
    protected $verifier;

    /**
     * 403 on / requests
     */
    public function index(HTTPRequest $request): HTTPResponse
    {
        $response = HTTPResponse::create(json_encode(["result" => "FAIL"]), 403);
        $response->addHeader('Content-Type', 'application/json');
        return $response;
    }

    /**
     * The relative link for this controller
     */
    #[\Override]
    public function Link($action = null): string
    {
        return Controller::join_links(
            Director::baseURL(),
            $this->config()->get('url_segment'),
            $action
        );
    }

    /**
     * Score for verification
     */
    public function getScore(): float
    {
        return Config::inst()->get(TokenResponse::class, 'score');
    }

    /**
     * Handle verification requests
     * check expects a POST with the 'token' and an optional 'action'
     * When the action is present, the returned action from verification must match the provided action
     * When no token is provided, the HTTP response code is 403. On success = 200, On error = 500
     * Tokens time out after 2 minutes
     * @return HTTPResponse  Response contains  a JSON encoded response object with a key of 'result' and a value or 'OK' or 'FAIL'
     */
    public function check(HTTPRequest $request): HTTPResponse
    {
        try {
            // the token is required
            // the controller must be enabled as well
            $token = $request->postVar('token');
            if (!$this->config()->get('enabled') || !$token) {
                // bad request
                $response = HTTPResponse::create(json_encode([
                    "result" => "FAIL",
                    "threshold" => null,
                    "score" => null,
                    "errorcodes" => []
                ]), 400);
                $response->addHeader('Content-Type', 'application/json');
                return $response;
            }

            $action = $request->postVar('action');
            $score = $request->postVar('score');
            if (!$score) {
                $score = $this->getScore();
            }

            $this->getVerifier();
            $result = $this->verifier->check($token, $score, $action);
            $this->verifier = null;
            // handle the response when it is a {@link NSWDPC\SpamProtection\TokenResponse}
            if ($result instanceof TokenResponse) {
                // a verification response from API
                if ($result->isValid()) {
                    // all good
                    $response = HTTPResponse::create(json_encode([
                        "result" => "OK",
                        "threshold" => $score,
                        "score" => $result->getResponseScore(),
                        'errorcodes' => $result->errorCodes()
                    ]), 200);
                    $response->addHeader('Content-Type', 'application/json');
                    return $response;
                } else {
                    // bad request / timeout / verification failed
                    $response = HTTPResponse::create(json_encode([
                        "result" => "FAIL",
                        "threshold" => $score,
                        "score" => $result->getResponseScore(),
                        'errorcodes' => $result->errorCodes()
                    ]), 400);
                    $response->addHeader('Content-Type', 'application/json');
                    return $response;
                }
            }

            // general failure on checking
            throw new \Exception("Failed to verify");
        } catch (\Exception $exception) {
            $response = HTTPResponse::create(json_encode([
                "result" => "FAIL",
                "threshold" => null,
                "score" => null,
                "errorcodes" => []
            ]), 500);
            $response->addHeader('Content-Type', 'application/json');
            $response->addHeader('X-Error-Message', $exception->getMessage());
            return $response;
        } finally {
            $this->verifier = null;
        }
    }
}

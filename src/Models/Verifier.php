<?php

namespace NSWDPC\SpamProtection;

use Silverstripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use GuzzleHttp\Client;

/**
 * Abstract verification model for checking captcha responses
 * @author James
 */
abstract class Verifier {

    use Configurable;

    use Injectable;

    /**
     * Verification API endpoint
     * @var string
     */
    private static $url_verify = "";

    /**
     * Key for verification requests
     * @var string
     */
    private static $secret_key = '';

    /**
     * An array of trusted proxies, for remote IP determination
     * @var array
     */
    private static $trusted_proxies = [];

    /**
     * Return a TokenResponse instance for verification
     */
    abstract protected function getTokenResponse( $decoded, $score, $action ) : TokenResponse;

    /**
     * Check a token returned from a client side validation response
     * Each token is valid for 2 minutes
     * @param string $token
     * @param float|null $score
     * @param string $action used to verify the action
     * @returns null|TokenResponse
     * @throws \Exception
     */
    public function check(string $token, float $score = null, string $action = "") : ?TokenResponse {
        $secret_key = $this->config()->get('secret_key');
        if(!$secret_key) {
            throw new \Exception("Configuration failure");
        }

        // data for siteverify
        $data = [
            'secret' => $secret_key,
            'response' => $token
        ];

        // try to get a remote addy
        $remote_ip = $this->getRemoteAddr();
        if($remote_ip) {
            $data['remoteip'] = $remote_ip;
        }

        // POST and get a verification response
        $client = new Client();
        Logger::log("Submitting token to ..." . $this->config()->get('url_verify') );
        $response = $client->request(
                "POST",
                $this->config()->get('url_verify'),
                [ 'form_params' => $data ]
        );
        $statusCode = $response->getStatusCode();
        Logger::log("Got a {$statusCode} response");
        if($statusCode != 200) {
            return null;
        }
        $body = $response->getBody();
        if($body) {
            $decoded = json_decode($body, true);
            if(json_last_error() == JSON_ERROR_NONE) {
                // return a TokenResponse model for the caller to decide on the action
                Logger::log("Decoded response OK");
                return $this->getTokenResponse( $decoded, $score, $action );
            }
        }
        // failed in the decode or response collection
        Logger::log("Decoded response NOTOK");
        return null;
    }

    /**
     * Get the remote addr from the request in a method not dissimilar to Zend/Laminas
     */
    protected function getRemoteAddr() {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
           // forwarded IP address
           $x_forwarded_for = trim(strip_tags($_SERVER['HTTP_X_FORWARDED_FOR']));
           $trusted = $this->config()->get('trusted_proxies');
           $ips = explode(",", $x_forwarded_for);
           if(empty($ips)) {
               // none found
               return "";
           } else if(count($ips) == 1) {
               // use first
               $remote_addr = $ips[0];
           } else {
               $ips = array_map("trim", $ips);
               if(!empty($trusted) && is_array($trusted)) {
                   $ips = array_diff($ips, $trusted);
                   // once trusted proxies are removed, use the last value
                   $remote_addr = array_pop($ips);
               } else {
                   // no trusted proxies set, use the first entry (client,proxy1,proxy2,etc)
                   $remote_addr = array_shift($ips);
               }
           }
       } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
           // use REMOTE_ADDR
           $remote_addr = trim(strip_tags($_SERVER['REMOTE_ADDR']));
       } else {
           $remote_addr = "";
       }

       return $remote_addr;

    }

}

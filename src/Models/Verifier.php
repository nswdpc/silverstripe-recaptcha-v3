<?php

namespace NSWDPC\SpamProtection;

use Silverstripe\Core\Config\Configurable;
use GuzzleHttp\Client;

class Verifier {

    use Configurable;

    private static $url_verify = "https://www.google.com/recaptcha/api/siteverify";
    private static $secret_key = '';
    private static $trusted_proxies = [];

    /**
     * Check a token returned from a RecaptchaV3
     * Each token is valid for 2 minutes
     * @param string $token
     * @param flaot|null $score
     * @param string $action used to verify the action
     * @returns boolean|TokenResponse
     * @throws \Exception
     */
    public function check($token, $score = null, $action = "") {
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
        $response = $client->request(
                "POST",
                $this->config()->get('url_verify'),
                [ 'form_params' => $data ]
        );
        if($response->getStatusCode() != 200) {
            return false;
        }
        $body = $response->getBody();
        if($body) {
            $decoded = json_decode($body, true);
            if(json_last_error() == JSON_ERROR_NONE) {
                // return a TokenResponse model for the caller to decide on the action
                return new TokenResponse( $decoded, $score, $action );
            }
        }
        // failed in the decode or response collection
        return false;
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

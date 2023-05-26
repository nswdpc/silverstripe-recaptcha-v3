<?php

namespace NSWDPC\SpamProtection\Tests;

use NSWDPC\SpamProtection\Verifier;
use NSWDPC\SpamProtection\TokenResponse;
use NSWDPC\SpamProtection\RecaptchaV3Field;
use NSWDPC\SpamProtection\VerificationController;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\FunctionalTest;

/**
 * Functional test for the VerificationController
 */
class VerificationControllerTest extends FunctionalTest
{

    /**
     * @inheritdoc
     */
    protected $usesDatabase = false;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        // default 'middle' score
        TokenResponse::config()->set('score', 0.5);
        Config::modify()->set(VerificationController::class, 'enabled', true);
    }

    /**
     * Test verification against a human with the requested threshold
     */
    public function testVerificationControllerHuman()
    {

        // and test verifier
        $verifier = TestVerifier::create();
        $verifier->setIsHuman(true);
        Injector::inst()->registerService(
            $verifier,
            Verifier::class
        );

        $token = 'test-human-response';

        $data = [
            'token' => $token,
            'score' => 0.9,// threshold to test against
            'action' => 'testverification/human'
        ];

        $response = $this->post(
            'recaptchaverify/check',
            $data
        );

        $body = $response->getBody();
        $decoded = json_decode($body, true);

        $this->assertEquals(0.9, $decoded['threshold'], 'Threshold is as requested');
        $this->assertEquals(TestVerifier::RESPONSE_HUMAN_SCORE, $decoded['score'], 'Response score is a human score');
        $this->assertEquals(200, $response->getStatusCode(), 'Response is 200');
    }

    /**
     * Test verification against a human with the configured threshold
     */
    public function testVerificationControllerScoreConfigured()
    {

        // and test verifier
        $verifier = TestVerifier::create();
        $verifier->setIsHuman(true);
        Injector::inst()->registerService(
            $verifier,
            Verifier::class
        );

        $token = 'test-human-response';

        $data = [
            'token' => $token,
            'action' => 'testverification/human'
        ];

        $response = $this->post(
            'recaptchaverify/check',
            $data
        );

        $body = $response->getBody();
        $decoded = json_decode($body, true);

        $this->assertEquals(0.5, $decoded['threshold'], 'Threshold is as configured');
        $this->assertEquals(TestVerifier::RESPONSE_HUMAN_SCORE, $decoded['score'], 'Response score is a human score');
        $this->assertEquals(200, $response->getStatusCode(), 'Response is 200');
    }

    /**
     * Test verification against a bot
     */
    public function testVerificationControllerBot()
    {

        // and test verifier
        $verifier = TestVerifier::create();
        $verifier->setIsHuman(false);

        Injector::inst()->registerService(
            $verifier,
            Verifier::class
        );

        $token = 'test-bot-response';

        $data = [
            'token' => $token,
            'score' => 0.9,// threshold to test against
            'action' => 'testverification/bot'
        ];

        $response = $this->post(
            'recaptchaverify/check',
            $data
        );

        $body = $response->getBody();
        $decoded = json_decode($body, true);

        $this->assertEquals(TestVerifier::RESPONSE_BOT_SCORE, $decoded['score'], 'Score is a bot score');
        $this->assertEquals(0.9, $decoded['threshold'], 'Threshold is as requested');
        $this->assertContains('an-error-code', $decoded['errorcodes'], 'Score is a bot score');
        $this->assertEquals(400, $response->getStatusCode(), 'Response is 400');
    }
}

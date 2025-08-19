<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Core\Security;

/**
 * Unit tests for the Security utility class.
 */
class SecurityTest extends TestCase
{
    public function testSignAndVerify(): void
    {
        $_ENV['SERVER_SECRET'] = 'test_secret_key';
        $nonce  = Security::generateNonce();
        $score  = 123;
        $duration = 4567;
        $sig    = Security::signRun($nonce, $score, $duration);
        $this->assertNotEmpty($sig);
        $this->assertTrue(Security::verifySignature($nonce, $score, $duration, $sig));
        $this->assertFalse(Security::verifySignature($nonce, $score + 1, $duration, $sig));
    }
}

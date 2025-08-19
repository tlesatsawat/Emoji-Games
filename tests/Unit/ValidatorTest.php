<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Core\Validator;
use App\Core\Response;

/**
 * Unit tests for the Validator class.
 */
class ValidatorTest extends TestCase
{
    public function testRequireKeysPassesWhenAllPresent(): void
    {
        $data = ['a' => 1, 'b' => 2];
        // Expect no exception or output
        Validator::requireKeys($data, ['a','b']);
        $this->assertTrue(true);
    }

    public function testRequireKeysFailsWhenMissing(): void
    {
        $this->expectOutputRegex('/Missing or empty parameter/');
        $data = ['a' => 1];
        Validator::requireKeys($data, ['a','b']);
    }
}

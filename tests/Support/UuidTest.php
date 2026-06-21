<?php

namespace Meritum\Database\Test\Support;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Meritum\Database\Support\Uuid;

class UuidTest extends TestCase
{
    #[Test]
    public function test_v4_returns_string(): void
    {
        $this->assertIsString(Uuid::v4());
    }

    #[Test]
    public function test_v4_matches_uuid_format(): void
    {
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            Uuid::v4()
        );
    }

    #[Test]
    public function test_v4_is_unique(): void
    {
        $this->assertNotSame(Uuid::v4(), Uuid::v4());
    }

    #[Test]
    public function test_v7_returns_string(): void
    {
        $this->assertIsString(Uuid::v7());
    }

    #[Test]
    public function test_v7_matches_uuid_format(): void
    {
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            Uuid::v7()
        );
    }

    #[Test]
    public function test_v7_is_unique(): void
    {
        $this->assertNotSame(Uuid::v7(), Uuid::v7());
    }

    #[Test]
    public function test_v7_timestamp_is_non_decreasing(): void
    {
        $a = Uuid::v7();
        $b = Uuid::v7();

        // Compare the full timestamp portion: first 8 chars (high 32 bits) + 4 chars (low 16 bits)
        $tsA = substr($a, 0, 8) . substr($a, 9, 4);
        $tsB = substr($b, 0, 8) . substr($b, 9, 4);

        $this->assertGreaterThanOrEqual($tsA, $tsB);
    }
}

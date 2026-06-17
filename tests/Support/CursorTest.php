<?php

namespace Meritum\Database\Test\Support;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Meritum\Database\Support\Cursor;

class CursorTest extends TestCase
{
    // --- encode / decode roundtrip ---

    #[Test]
    public function test_encode_decode_roundtrip_with_string_value(): void
    {
        $encoded = Cursor::encode('abc-123');
        $cursor  = Cursor::decode($encoded);

        $this->assertSame('str', $cursor->type);
        $this->assertSame('abc-123', $cursor->value);
        $this->assertSame('next', $cursor->direction);
    }

    #[Test]
    public function test_encode_decode_roundtrip_with_int_value(): void
    {
        $encoded = Cursor::encode(42, 'prev');
        $cursor  = Cursor::decode($encoded);

        $this->assertSame('int', $cursor->type);
        $this->assertSame(42, $cursor->value);
        $this->assertSame('prev', $cursor->direction);
    }

    // --- encode ---

    #[Test]
    public function test_encode_returns_string(): void
    {
        $this->assertIsString(Cursor::encode('abc'));
    }

    #[Test]
    public function test_encode_strips_base64_padding(): void
    {
        $this->assertStringNotContainsString('=', Cursor::encode('abc'));
    }

    #[Test]
    public function test_encode_produces_url_safe_base64(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $encoded = Cursor::encode((string) $i);
            $this->assertStringNotContainsString('+', $encoded);
            $this->assertStringNotContainsString('/', $encoded);
        }
    }

    #[Test]
    public function test_encode_defaults_direction_to_next(): void
    {
        $cursor = Cursor::decode(Cursor::encode('abc'));

        $this->assertSame('next', $cursor->direction);
    }

    #[Test]
    public function test_encode_int_value_sets_type_to_int(): void
    {
        $cursor = Cursor::decode(Cursor::encode(99));

        $this->assertSame('int', $cursor->type);
        $this->assertIsInt($cursor->value);
    }

    #[Test]
    public function test_encode_string_value_sets_type_to_str(): void
    {
        $cursor = Cursor::decode(Cursor::encode('abc'));

        $this->assertSame('str', $cursor->type);
        $this->assertIsString($cursor->value);
    }

    // --- decode ---

    #[Test]
    public function test_decode_throws_on_invalid_base64(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid or malformed cursor');

        Cursor::decode('not-a-valid-cursor!!!');
    }

    #[Test]
    public function test_decode_throws_on_wrong_version(): void
    {
        $payload = strtr(rtrim(base64_encode((string) json_encode(['v' => 999, 'typ' => 'str', 'val' => 'x', 'dir' => 'next'])), '='), '+/', '-_');

        $this->expectException(InvalidArgumentException::class);

        Cursor::decode($payload);
    }

    #[Test]
    public function test_decode_throws_on_non_json(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Cursor::decode(strtr(rtrim(base64_encode('not-json'), '='), '+/', '-_'));
    }

    #[Test]
    public function test_decode_throws_on_invalid_direction(): void
    {
        $payload = strtr(rtrim(base64_encode((string) json_encode(['v' => 1, 'typ' => 'str', 'val' => 'x', 'dir' => 'sideways'])), '='), '+/', '-_');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid or malformed cursor');

        Cursor::decode($payload);
    }

    #[Test]
    public function test_decode_int_value_is_cast_to_int(): void
    {
        $cursor = Cursor::decode(Cursor::encode(42));

        $this->assertIsInt($cursor->value);
        $this->assertSame(42, $cursor->value);
    }

    #[Test]
    public function test_decode_string_value_is_cast_to_string(): void
    {
        $cursor = Cursor::decode(Cursor::encode('abc'));

        $this->assertIsString($cursor->value);
        $this->assertSame('abc', $cursor->value);
    }
}

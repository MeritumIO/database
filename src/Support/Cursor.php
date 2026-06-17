<?php

namespace Meritum\Database\Support;

use InvalidArgumentException;

final class Cursor
{
    private const int VERSION = 1;

    public function __construct(
        public readonly string $type, // int or str
        public readonly int|string $value,
        public readonly string $direction
    ) {}

    public static function encode(int|string $value, string $direction = 'next'): string
    {
        $payload = [
            'v'   => self::VERSION,
            'typ' => is_int($value) ? 'int' : 'str',
            'val' => $value,
            'dir' => $direction,
        ];

        $json = json_encode($payload, JSON_THROW_ON_ERROR);

        return strtr(rtrim(base64_encode($json), '='), '+/', '-_');
    }

    public static function decode(string $cursor): self
    {
        try {
            $raw = json_decode(base64_decode(strtr($cursor, '-_', '+/')), true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new InvalidArgumentException('Invalid or malformed cursor');
        }

        if (!is_array($raw) || ($raw['v'] ?? null) !== self::VERSION) {
            throw new InvalidArgumentException('Invalid or malformed cursor');
        }

        /** @var array{v: int, typ: string, val: int|string, dir: string} $raw */

        if (!in_array($raw['dir'], ['next', 'prev'], true)) {
            throw new InvalidArgumentException('Invalid or malformed cursor');
        }

        $value = $raw['typ'] === 'int' ? (int) $raw['val'] : (string) $raw['val'];

        return new self($raw['typ'], $value, $raw['dir']);
    }
}

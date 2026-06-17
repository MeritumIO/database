<?php

namespace Meritum\Database\Support;

final class Uuid
{
    /**
     * Generate a UUIDv4
     */
    public static function v4(): string
    {
        return sprintf(
            '%s-%s-%s-%s-%s',
            bin2hex(random_bytes(4)),
            bin2hex(random_bytes(2)),
            bin2hex(chr((ord(random_bytes(1)) & 0x0F) | 0x40) . random_bytes(1)),
            bin2hex(chr((ord(random_bytes(1)) & 0x3F) | 0x80) . random_bytes(1)),
            bin2hex(random_bytes(6))
        );
    }
}

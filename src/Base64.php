<?php

namespace AuthToken;

/**
 * This class provides methods for encoding and decoding data using Base64URL.
 */

abstract class Base64 {

    public function base64url_encode($data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public function base64url_decode($data): false|string
    {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
    }

}
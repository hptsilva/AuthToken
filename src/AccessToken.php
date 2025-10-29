<?php

namespace AuthToken;

use AuthToken\Exception\InvalidToken;
use AuthToken\Exception\SecretNotFound;
use DateTimeImmutable;

class AccessToken extends Base64
{
    private string $secret;

    /**
     * @throws SecretNotFound
     */
    public function __construct()
    {
        // Read secret from environment (APP_SECRET). Dotenv should be loaded by the caller.
        $secret = $_ENV['AUTHTOKEN_APP_SECRET'] ?? getenv('AUTHTOKEN_APP_SECRET') ?: null;
        if (empty($secret)) {
            throw new SecretNotFound('Secret key not found. Please set AUTHTOKEN_APP_SECRET in your .env (use `php auth-token secret` to generate one).');
        }
        $this->secret = $secret;
    }

    /**
     * Generate an Access Token.
     */
    public function generate(int|string $userId): string
    {
        $header = $this->base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));

        $issuedAt = new DateTimeImmutable();
        $expire = $issuedAt->modify($_ENV['AUTHTOKEN_ACCESS_TOKEN_TIMEOUT'] ?? '+15 minutes')->getTimestamp();

        $payload = $this->base64url_encode(json_encode([
            'iat' => $issuedAt->getTimestamp(), // Issued at: time when the token was generated
            'nbf' => $issuedAt->getTimestamp(), // Not before
            'exp' => $expire,                  // Expire
            'sub' => $userId,                  // Subject (user ID)
        ]));

        $signature = $this->base64url_encode(hash_hmac('sha256', "$header.$payload", $this->secret, true));

        return "$header.$payload.$signature";
    }

    /**
     * Validates an Access Token in a stateless manner.
     * Returns the decoded payload on success, or false on failure.
     */
    public function validate(string $token): false|array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false; // Invalid structure
        }

        list($header, $payload, $receivedSignature) = $parts;

        $calculatedSignature = $this->base64url_encode(hash_hmac('sha256', "$header.$payload", $this->secret, true));

        if (!hash_equals($calculatedSignature, $receivedSignature)) {
            return false; // Invalid signature
        }

        $decodedPayload = json_decode($this->base64url_decode($payload), true);

        // Check if the token has expired
        if ($decodedPayload['exp'] < time()) {
            return false;
        }

        return $decodedPayload;
    }
}
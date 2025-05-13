<?php

namespace AuthToken;

use AuthToken\Exception\InvalidToken;
use AuthToken\Exception\SecretNotFound;
use Dotenv;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

/**
 * Class Token
 *
 * The `Token` class is responsible for generating secure tokens based on user-provided parameters.
 * It uses a combination of a secret key, a timestamp, and a random number to create a unique token.
 * The class also interacts with a database to ensure token uniqueness and manage token storage.
 *
 * Key Features:
 * - Generates tokens using a secure payload encoding mechanism (Base64 URL-safe encoding).
 * - Ensures token integrity with an HMAC SHA-256 signature.
 * - Validates the presence and integrity of a secret key stored in a file.
 * - Interacts with a database to:
 *   - Check if a token exists in the blacklist.
 *   - Verify if a token is already associated with a user.
 *   - Insert new tokens into the database.
 * - Handles exceptions for missing or corrupted secret keys, database connection errors, and other unexpected issues.
 *
 * Dependencies:
 * - Requires the `Dotenv` library to load environment variables.
 * - Relies on the `ConnectionDB` class for database operations.
 * - Extends the `Base64` class for Base64 URL-safe encoding.
 *
 * Exceptions:
 * - Throws `SecretNotFound` if the secret key is missing.
 * - Throws `CorruptedSecretKey` if the secret key is invalid or corrupted.
 * - Throws `ErrorConnection` if the database connection fails.
 *
 * Usage:
 * Instantiate the class and call the `generateToken` method with the required parameters:
 * - `string $user`: The username or identifier.
 * - `string $password`: A hashed password for security.
 * - `int|string $userID`: The unique identifier of the user.
 *
 * Example:
 * ```php
 * $token = new Token();
 * $result = $token->generateToken('username', 'hashed_password', 123);
 * if ($result['status']) {
 *     echo "Token generated: " . $result['token'];
 * } else {
 *     echo "Error: " . $result['message'];
 * }
 * ```
 */
class Token extends Base64 {

    /**
     * The validateToken method is responsible for verifying the validity of a provided token. It ensures that the token was generated using the correct secret key. The method performs the following steps:
     *
     * * Splits the token into its payload and signature parts.
     * * Validates the token structure to ensure it contains exactly two parts.
     * * Reads the secret key.
     * * Recalculates the signature using the payload and the secret key.
     * * Compares the recalculated signature with the received signature using a timing-attack-safe comparison (hash_equals).
     * * If the token is valid, the method returns true. Otherwise, it returns false.
     *
     * Exceptions:
     * * InvalidToken: Thrown if the token structure is invalid (does not contain exactly two parts).
     * * SecretNotFound: Thrown if the secret key file is missing or cannot be opened.
     * @throws InvalidToken
     * @throws SecretNotFound
     */
    public function validateToken(string $token): bool
    {
        $part = explode('.', $token);
        if (count($part) !== 2) {
            throw new InvalidToken('The structure of the token provided is not valid');
        }

        $path = __DIR__ . '/Secret/secret.txt';
        $key= @fopen($path, 'r');
        if (!$key) {
            throw new SecretNotFound('Secret key not found.');
        }
        $secret = fread($key, filesize($path));
        fclose($key);

        list($codifiedPayload, $receivedSignature) = $part;

        $calculatedSignature = $this->base64url_encode(hash_hmac('sha256', $codifiedPayload, $secret, true));

        if (!hash_equals($calculatedSignature, $receivedSignature)) {
            return false;
        }

        return true;
    }

}
<?php

namespace AuthToken;

use AuthToken\Exception\ErrorConnection;
use AuthToken\Exception\InvalidToken;
use AuthToken\Exception\SecretNotFound;
use AuthToken\Database\ConnectionDB;
use Dotenv;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

/**
 * Class Auth
 *
 * The `Auth` class is responsible for managing token authentication, resetting, and deletion.
 * It ensures the validity and integrity of tokens and interacts with a database to perform these operations.
 *
 * Key Features:
 * - Authenticates tokens by verifying their structure, signature, and expiration.
 * - Resets tokens, allowing them to be reused if they are still valid.
 * - Deletes tokens from the database.
 * - Handles exceptions for invalid tokens, database connection errors, and other unexpected issues.
 *
 * Dependencies:
 * - Requires the `Dotenv` library to load environment variables.
 * - Relies on the `ConnectionDB` class for database operations.
 * - Extends the `Base64` class for Base64 URL-safe encoding.
 *
 * Exceptions:
 * - Throws `InvalidToken` if the token structure or signature is invalid.
 * - Throws `SecretNotFound` if the secret key is missing.
 * - Throws `ErrorConnection` if the database connection fails.
 *
 * Methods:
 * - `authenticateToken(string $token): array`:
 *   Authenticates the provided token. Returns a status, HTTP code, and a message or user ID.
 * - `resetToken(string $token): array`:
 *   Resets the provided token if valid. Returns a status, HTTP code, and a confirmation message.
 * - `deleteToken(string $token): array`:
 *   Deletes the provided token. Returns a status, HTTP code, and a confirmation message.
 *
 * Usage:
 * Instantiate the class and call the desired method with the required parameters:
 *
 * Example:
 * ```php
 * $auth = new Auth();
 * 
 * // Authenticate a token
 * $result = $auth->authenticateToken('your_token_here');
 * if ($result['status']) {
 *     echo "Token is valid for user ID: " . $result['user_id'];
 * } else {
 *     echo "Error: " . $result['message'];
 * }
 * 
 * // Reset a token
 * $resetResult = $auth->resetToken('your_token_here');
 * echo $resetResult['message'];
 * 
 * // Delete a token
 * $deleteResult = $auth->deleteToken('your_token_here');
 * echo $deleteResult['message'];
 * ```
 */
class Auth extends Base64
{

    /**
     * Authenticates the provided token.
     * If the token is valid, it returns a True status, an HTTP status code, a confirmation message and the user ID.
     * If the token is invalid, it returns a False status, an HTTP status code and an error message.
     * @param string $token
     * @return array
     * @throws SecretNotFound|ErrorConnection|InvalidToken
     */
    public function authenticateToken(string $token): array
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
            return [
                'status' => false,
                'code' => 400,
                'message' => "Invalid Token."
            ];
        }

        $connection = new ConnectionDB();
        $cnx = $connection->connect($_ENV['DB_HOSTNAME'], $_ENV['DB_USER'], $_ENV['DB_PASSWORD'], $_ENV['DB_DATABASE']);
        if (!$cnx) {
            throw new ErrorConnection('Connection failed');
        }

        $result = $connection->searchToken($cnx, $token);
        if (!$result) {
            return [
                'status' => false,
                'code' => 401,
                'message' => "Unauthorized."
            ];
        }

        if (time() - strtotime($result['updated_at']) > $_ENV['TIMEOUT']) {
            return [
                'status' => false,
                'code' => 401,
                'message' => "Token has expired. Create another token."
            ];
        }

        return [
            'status' => true,
            'code' => 200,
            'message' => "Token is valid.",
            'user_id' => $result['user_id'],
        ];

    }

    /**
     * Resets the provided token.
     * If the token is valid, it returns a True status, an HTTP status code and a confirmation message indicating that the procedure was completed.
     * If the token is invalid, it returns a False status, an HTTP status code and an error message.
     * @param string $token
     * @return array
     * @throws ErrorConnection
     */
    public function resetToken(string $token): array
    {
        $connection = new ConnectionDB();

        $cnx = $connection->connect($_ENV['DB_HOSTNAME'], $_ENV['DB_USER'], $_ENV['DB_PASSWORD'], $_ENV['DB_DATABASE']);
        if (!$cnx) {
            throw new ErrorConnection('Connection failed');
        }

        $result = $connection->searchToken($cnx, $token);
        if(!$result) {
            return [
                'status' => false,
                'code' => 404,
                'message' => "Token not found. Create another one."
            ];
        }

        if (time() - strtotime($result['updated_at']) > $_ENV['TIMEOUT']) {
            return [
                'status' => false,
                'code' => 401,
                'message' => "Token has expired. Create another token."
            ];
        }

        if (!$connection->resetToken($cnx, $token)) {
            return [
                'status' => false,
                'code' => 400,
                'message' => "Not possible to reset the token."
            ];
        }

        return [
            'status' => true,
            'code' => 200,
            'message' => "Token successfully reset."
        ];
    }

    /**
     * Deletes the provided token.
     * If the token is valid, it returns a True status, an HTTP status code and a confirmation message indicating that the procedure was completed.
     * If the token is invalid, it returns a False status, an HTTP status code and an error message.
     * @param string $token
     * @return array
     * @throws ErrorConnection
     */
    public function deleteToken(string $token): array
    {
        $connection = new ConnectionDB();
        $cnx = $connection->connect($_ENV['DB_HOSTNAME'], $_ENV['DB_USER'], $_ENV['DB_PASSWORD'], $_ENV['DB_DATABASE']);
        if (!$cnx) {
            throw new ErrorConnection('Connection failed');
        }

        $response = $connection->searchToken($cnx, $token);
        if(!$response) {
            return [
                'status' => false,
                'code' => 404,
                'message' => "Token not found."
            ];
        }

        if (!$connection->deleteToken($cnx, $token)) {
            return [
                'status' => false,
                'code' => 400,
                'message' => "Not possible to delete the token."
            ];
        }

        return [
            'status' => true,
            'code' => 200,
            'message' => 'Token successfully deleted.'
        ];

    }
}
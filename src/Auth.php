<?php

namespace AuthToken;

use AuthToken\Exception\CorruptedSecretKey;
use AuthToken\Exception\ErrorConnection;
use AuthToken\Exception\InvalidToken;
use AuthToken\Exception\SecretNotFound;
use AuthToken\Database\ConnectionDB;
use Dotenv;
use Exception;
use PDOException;

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
 * - Throes `CorruptedSecretKey` If the secret key does not have 64 characters.
 *
 * Methods:
 * - `generateToken(string $user, string $password, int|string $userID): array`:
 *   Generates a token based on the provided parameters. Returns a status, HTTP code and the token created.
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
     * Generates a token based on the provided parameters.
     * If the procedure is completed successfully, it returns a True status, an HTTP status code and the created token.
     * Otherwise, it returns a False status, an HTTP status code and an error message.
     * Recommended to use a password hash to generate the token.
     * @param string $user
     * @param string $password
     * @param int|string $userID
     * @return array
     * @throws SecretNotFound|ErrorConnection|CorruptedSecretKey
     */
    public function generateToken(string $user, string $password, int|string $userID): array
    {

        $timestamp = time();
        $min = (int) pow(10, 10 -1);
        $max = (int) pow(10, 10) - 1;
        try{
            $randomNumber = random_int($min, $max);
        } catch (Exception $e) {
            return [
                'status' => false,
                'code' => 500,
                'message' => $e->getMessage(),
            ];
        }

        $payload = [
            'user' => $user,
            'passwordHash' => $password,
            'userID' => $userID,
            'timestamp' => $timestamp,
            'randomNumber' => $randomNumber
        ];

        $path = __DIR__ . '/Secret/secret.txt';
        $key= @fopen($path, 'r');
        if (!$key) {
            throw new SecretNotFound('Secret key not found');
        }

        $size = filesize($path);
        if ($size != 64) {
            throw new CorruptedSecretKey('Secret key is corrupted');
        }
        $secret = fread($key, $size);
        fclose($key);

        $codifiedPayload = $this->base64url_encode(json_encode($payload));
        $signature = $this->base64url_encode(hash_hmac('sha256', $codifiedPayload, $secret, true));

        $token = "$codifiedPayload.$signature";

        $connection = new ConnectionDB();
        $cnx = $connection->connect();

        if ($cnx instanceof PDOException) {
            $error = $cnx->getMessage();
            throw new ErrorConnection("\033[31m$error\033[0m\n");
        }

        if (!$connection->searchBlacklistToken($cnx, $token)) {
            $this->generateToken($user, $password, $userID);
        }

        if($connection->searchToken($cnx, $token)) {
            $this->generateToken($user, $password, $userID);
        }

        if ($connection->searchUserToken($cnx, $token, $userID)) {
            return [
                'status' => true,
                'code' => 200,
                'token' => $token,
            ];
        }

        if (!$connection->insertToken($cnx, $token, $userID)) {
            return [
                'status' => false,
                'code' => 500,
                'message' => "Failed to insert token into database",
            ];
        }

        return [
            'status' => true,
            'code' => 200,
            'token' => $token,
        ];

    }

    /**
     * Authenticates the provided token.
     * If the token is valid, it returns a True status, an HTTP status code, a confirmation message and the user ID.
     * If the token is invalid, it returns a False status, an HTTP status code and an error message.
     * @param string $token
     * @return array
     * @throws ErrorConnection
     */
    public function authenticateToken(string $token): array
    {

        $tokenObj = new Token();
        try {
            if (!$tokenObj->validateToken($token)) {
                return [
                    'status' => false,
                    'code' => 400,
                    'message' => "Invalid Token."
                ];
            }
        } catch (InvalidToken|SecretNotFound $e) {
            return [
                'status' => false,
                'code' => 500,
                'message' => $e->getMessage()
            ];
        }

        $connection = new ConnectionDB();
        $cnx = $connection->connect();

        if ($cnx instanceof PDOException) {
            $error = $cnx->getMessage();
            throw new ErrorConnection("\033[31m$error\033[0m\n");
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

        $tokenObj = new Token();
        try {
            if (!$tokenObj->validateToken($token)) {
                return [
                    'status' => false,
                    'code' => 400,
                    'message' => "Invalid Token."
                ];
            }
        } catch (InvalidToken|SecretNotFound $e) {
            return [
                'status' => false,
                'code' => 500,
                'message' => $e->getMessage()
            ];
        }

        $connection = new ConnectionDB();
        $cnx = $connection->connect();

        if ($cnx instanceof PDOException) {
            $error = $cnx->getMessage();
            throw new ErrorConnection("\033[31m$error\033[0m\n");
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
        $cnx = $connection->connect();
        if ($cnx instanceof PDOException) {
            $error = $cnx->getMessage();
            throw new ErrorConnection("\033[31m$error\033[0m\n");
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
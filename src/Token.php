<?php

namespace AuthToken;

use AuthToken\Exception\CorruptedSecretKey;
use AuthToken\Exception\SecretNotFound;
use AuthToken\Database\ConnectionDB;
use AuthToken\Exception\ErrorConnection;
use Exception;
use Dotenv;
use PDOException;

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
        } catch (Exception $ex) {
            return [
                'status' => false,
                'code' => 500,
                'message' => $ex->getMessage(),
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
        $cnx = $connection->connect($_ENV['DB_HOSTNAME'], $_ENV['DB_USER'], $_ENV['DB_PASSWORD'], $_ENV['DB_DATABASE']);

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

}
<?php

namespace AuthToken;

use AuthToken\exception\ErrorConnection;
use AuthToken\exception\InvalidToken;
use AuthToken\exception\SecretNotFound;
use Dotenv;
use AuthToken\database\ConnectionDB;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

class Auth extends Base64
{

    /**
     * Authenticates the provided token.
     * If the token is valid, it returns a True status, a confirmation message and the user ID.
     * If the token is invalid, it returns a False status and an error message.
     * @param $token - Token
     * @return array
     * @throws SecretNotFound|ErrorConnection|InvalidToken
     */
    public function authenticateToken(string $token): array
    {

        $part = explode('.', $token);
        if (count($part) !== 2) {
            throw new InvalidToken('The structure of the token provided is not valid');
        }
        
        $path = __DIR__ . '/secret/secret.txt';
        $file= @fopen($path, 'r');
        if (!$file) {
            throw new SecretNotFound('Secret key not found.');
        }
        $secret = fread($file, filesize($path));
        fclose($file); 

        list($codefiedPayload, $receivedSignature) = $part;

        $calculatedSignature = $this->base64url_encode(hash_hmac('sha256', $codefiedPayload, $secret, true));
        
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

        $resultado = $connection->searchToken($cnx, $token);
        if (!$resultado) {
            return [
                'status' => false,
                'code' => 401,
                'message' => "Unauthorized."
            ];
        }

        if (time() - strtotime($resultado['updated_at']) > $_ENV['TIMEOUT']) {
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
            'user_id' => $resultado['user_id'],
        ];

    }

    /**
     * Resets the provided token.
     * If the token is valid, it returns a True status and a confirmation message indicating that the procedure was completed.
     * If the token is invalid, it returns a False status and an error message.
     * * @param $token - Token
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

        $resultado = $connection->searchToken($cnx, $token);
        if(!$resultado) {
            return [
                'status' => false,
                'code' => 404,
                'message' => "Token not found. Create another one."
            ];
        }

        if (time() - strtotime($resultado['updated_at']) > $_ENV['TIMEOUT']) {
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
     * If the token is valid, it returns a True status and a confirmation message indicating that the procedure was completed.
     * If the token is invalid, it returns a False status and an error message.
     * * @param $token - Token
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

        $resultado = $connection->searchToken($cnx, $token);
        if(!$resultado) {
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
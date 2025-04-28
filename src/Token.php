<?php

namespace AuthToken;

use Dotenv;
use AuthToken\database\ConnectionDB;
use Exception;

// Cria uma instância do Dotenv
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
// Carrega as variáveis do arquivo .env
$dotenv->load();

class Token extends Base64 {

    /**
     * Generates a token based on the provided parameters.
     * If the procedure is completed successfully, it returns a True status and the created token.
     * Otherwise, it returns a False status and an error message.
     * @param $user - Username
     * @param $password - Password
     * @param $userID - User ID
     * @return array
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
                'message' => "Failed to generate token",
            ];
        }

        $payload = [
            'user' => $user,
            'passwordHash' => $password,
            'userID' => $userID,
            'timestamp' => $timestamp,
            'randomNumber' => $randomNumber
        ];

        $path = __DIR__ . '/secret/secret.txt';
        $file= fopen($path, 'r');
        if (!$file) {
            return [
                'status' => false,
                'message' => "The secret does not exist or is not a regular secret.",
            ];
        }
        $secret = fread($file, filesize($path));
        fclose($file);

        $codefiedPayload = $this->base64url_encode(json_encode($payload));
        $signature = $this->base64url_encode(hash_hmac('sha256', $codefiedPayload, $secret, true));      

        $token = "$codefiedPayload.$signature";

        $connection = new ConnectionDB();
        $cnx = $connection->connect($_ENV['DB_HOSTNAME'], $_ENV['DB_USER'], $_ENV['DB_PASSWORD'], $_ENV['DB_DATABASE']);

        if (!$cnx) {
            return [
                'status' => false,
                'message' => "Connection failed",
            ];
        }

        // Verifica se o "token" criado não está na blacklist. Caso ele esteja, outro "token" é criado
        if (!$connection->searchBlacklistToken($cnx, $token)) {
            $this->generateToken($user, $password, $userID);
        }

        // Verifica se o token criado já está cadastrado na lista.
        if($connection->searchToken($cnx, $token)) {
            $this->generateToken($user, $password, $userID);
        }

        if ($connection->searchUserToken($cnx, $token, $userID)) {
            return [
                'status' => true,
                'token' => $token,
            ];
        }

        if (!$connection->insertToken($cnx, $token, $userID)) {
            return [
                'status' => false,
                'message' => "Failed to insert token into database",
            ];
        }

        return [
            'status' => true,
            'token' => $token,
        ];

    }

}
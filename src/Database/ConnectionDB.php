<?php

namespace AuthToken\Database;

use AuthToken\Exception\ErrorConnection;
use PDO;
use PDOException;

/**
 * ConnectionDB Class
 * 
 * This class provides methods for connecting to a MariaDb database and inserting tokens.
 */
class ConnectionDB {

    /**
     * Conecta no banco de dados
     * @throws ErrorConnection
     */
    public function connect(): PDOException|PDO
    {
        try {
            $hostname = $_ENV['AUTHTOKEN_DB_HOSTNAME'];
            $username = $_ENV['AUTHTOKEN_DB_USER'];
            $password = $_ENV['AUTHTOKEN_DB_PASSWORD'];
            $database = $_ENV['AUTHTOKEN_DB_DATABASE'];
            if ($_ENV['AUTHTOKEN_DB_CONNECTION'] == 'mysql' || $_ENV['AUTHTOKEN_DB_CONNECTION'] == 'mariadb') {
                $options = [
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ];
                return new PDO("mysql:host=$hostname;dbname=$database;charset=utf8mb4", $username, $password, $options);
            } else if ($_ENV['AUTHTOKEN_DB_CONNECTION'] == 'sqlite') {

                $cnx = new PDO("sqlite:".__DIR__."/$database.sqlite");
                $cnx->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                return $cnx;
            } else {
                throw new ErrorConnection('Unknown DB connection');
            }
        } catch (PDOException $e) {
            return $e;
        }
    }

    public function insertRefreshToken($cnx, string $token, $userId, string $expiresAt): bool
    {
        $query = "INSERT INTO refresh_tokens (user_id, token, expires_at) VALUES (:user_id, :token, :expires_at)";
        $stmt = $cnx->prepare($query);

        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':token', $token);
        $stmt->bindParam(':expires_at', $expiresAt);

        return $stmt->execute();
    }

    public function findRefreshToken($cnx, string $token)
    {
        $query = "SELECT user_id, token, expires_at FROM refresh_tokens WHERE token = :token";
        $stmt = $cnx->prepare($query);
        $stmt->bindParam(':token', $token, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function deleteRefreshToken($cnx, string $token): bool
    {
        $query = "DELETE FROM refresh_tokens WHERE token = :token";
        $stmt = $cnx->prepare($query);
        $stmt->bindParam(':token', $token, PDO::PARAM_STR);

        return $stmt->execute();
    }

    public function deleteUserRefreshTokens($cnx, $userId): bool
    {
        $query = "DELETE FROM refresh_tokens WHERE user_id = :user_id";
        $stmt = $cnx->prepare($query);
        $stmt->bindParam(':user_id', $userId);

        return $stmt->execute();
    }


}
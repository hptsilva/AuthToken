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

    public function connect($hostname, $username, $password, $database): PDOException|PDO
    {

        try {

            $options = [
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
              ];

            return new PDO("mysql:host=$hostname;dbname=$database;charset=utf8mb4", $username, $password, $options);
        } catch (PDOException $e) {
            return $e;
        }

    }

    public function insertToken($cnx, $token, $user_id): bool
    {

        $query = "INSERT INTO tokens (token, user_id, created_at, updated_at) VALUES (:token, :user_id, :created_at, :updated_at)";
        $stmt = $cnx->prepare($query);

        $stmt->bindParam(':token', $token, PDO::PARAM_STR);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_STR);
        $timestamp = date('Y-m-d H:i:s');
        $stmt->bindParam(':created_at', $timestamp, PDO::PARAM_STR);
        $stmt->bindParam(':updated_at', $timestamp, PDO::PARAM_STR);

        if ($stmt->execute()) {
            return true;
        } else {
            return false;
        }

    }

    public function updateToken($cnx, $oldToken, $newToken, $user_id): bool
    {
        $query = "UPDATE tokens SET token = :token, created_at = :created_at, updated_at = :updated_at WHERE user_id = :user_id";
        $stmt = $cnx->prepare($query);
        $stmt->bindParam(':token', $newToken, PDO::PARAM_STR);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_STR);
        $timestamp = date('Y-m-d H:i:s');
        $stmt->bindParam(':created_at', $timestamp, PDO::PARAM_STR);
        $stmt->bindParam(':updated_at', $timestamp, PDO::PARAM_STR);
        if ($stmt->execute()) {
            $query = "INSERT INTO blacklist_tokens (token, created_at, updated_at) VALUES (:token, :created_at, :updated_at)";
            $stmt = $cnx->prepare($query);
            $stmt->bindParam(':token', $oldToken, PDO::PARAM_STR);
            $stmt->bindParam(':created_at', $timestamp, PDO::PARAM_STR);
            $stmt->bindParam(':updated_at', $timestamp, PDO::PARAM_STR);
            if ($stmt->execute()) {
                return true;
            }
        }
        return false;
    }

    public function searchToken($cnx, $token)
    {
        $query = "SELECT token, user_id, updated_at FROM tokens WHERE token = (:token)";
        try {
            $stmt = $cnx->prepare($query);
            $stmt->bindParam(':token', $token, PDO::PARAM_STR);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                return $result;
            } else {
                return false;
            }
        } catch (PDOException $e) {
            error_log($e);
            die;
        }
    }

    public function searchUserToken($cnx, $token, $user_id): bool|array
    {

        $query = "SELECT token, user_id FROM tokens WHERE user_id = (:user_id)";
        $stmt = $cnx->prepare($query);

        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            try {
                $cnx->setAttribute(\PDO::ATTR_AUTOCOMMIT, false);
                $cnx->beginTransaction();
                $this->updateToken($cnx, $result['token'], $token, $result['user_id']);
                $cnx->commit();
                $cnx->setAttribute(\PDO::ATTR_AUTOCOMMIT, true);
                return true;
            } catch (PDOException $e) {
                if ($cnx->inTransaction()) {
                    $cnx->rollBack();
                    $cnx->setAttribute(\PDO::ATTR_AUTOCOMMIT, true);
                }
                throw new PDOException($e->getMessage());
            }
        } else {
            return false;
        }
    }

    /**
     * @throws ErrorConnection
     */
    public function searchBlacklistToken($cnx, $token): bool
    {
        $query = "SELECT token FROM blacklist_tokens WHERE token = (:token)";
        try {
            $stmt = $cnx->prepare($query);

            $stmt->bindParam(':token', $token, PDO::PARAM_STR);

            if ($stmt->execute()) {
                if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                    return true;
                }
            }
            return false;
        }catch (PDOException $e) {
            error_log($e);
            die;
        }

    }

    public function resetToken($cnx, $token): bool
    {

        $query = "UPDATE tokens SET updated_at = :updated_at WHERE token = :token";
        try {
            $stmt = $cnx->prepare($query);
            $timestamp = date('Y-m-d H:i:s');
            $stmt->bindParam(':updated_at', $timestamp, PDO::PARAM_STR);
            $stmt->bindParam(':token', $token, PDO::PARAM_STR);
            if ($stmt->execute()) {
                return true;
            }
            return false;
        } catch (PDOException $e) {
            error_log($e);
            die;
        }
    }

    public function deleteToken($cnx, $token): bool
    {
        $query = "DELETE FROM tokens WHERE token = :token";
        try {
            $cnx->beginTransaction();
            $stmt = $cnx->prepare($query);
            $stmt->bindParam(':token', $token, PDO::PARAM_STR);
            $stmt->execute();
            $query = "INSERT INTO blacklist_tokens (token, created_at, updated_at) VALUES (:token, :created_at, :updated_at)";
            $stmt = $cnx->prepare($query);
            $stmt->bindParam(':token', $token, PDO::PARAM_STR);
            $timestamp = date('Y-m-d H:i:s');
            $stmt->bindParam(':created_at', $timestamp, PDO::PARAM_STR);
            $stmt->bindParam(':updated_at', $timestamp, PDO::PARAM_STR);
            $stmt->execute();
            $cnx->commit();
            return true;
        } catch (PDOException) {
            if ($cnx->inTransaction()) {
                $cnx->rollBack();
            }
            return false;
        }
    }
}
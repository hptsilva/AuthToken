<?php

namespace AuthToken;

use AuthToken\Database\ConnectionDB;
use AuthToken\Exception\ErrorConnection;
use DateInterval;
use DateTime;
use Exception;
use PDOException;
use Random\RandomException;

class Auth
{
    private ConnectionDB $connection;
    private \PDO|PDOException $cnx;
    private AccessToken $accessTokenHandler;

    /**
     * @throws ErrorConnection
     */
    public function __construct()
    {
        $this->connection = new ConnectionDB();
        $this->cnx = $this->connection->connect();
        if ($this->cnx instanceof PDOException) {
            $error = $this->cnx->getMessage();
            throw new ErrorConnection("\033[31m$error\033[0m\n");
        }
        $this->accessTokenHandler = new AccessToken();
    }

    /**
     * Log in, generating a pair of Access and Refresh tokens.
     * Invalidates any old refresh tokens for this user.
     * @param int|string $userID
     * @return array
     * @throws RandomException
     */
    public function login(int|string $userID): array
    {
        // Invalidate old tokens
        $this->connection->deleteUserRefreshTokens($this->cnx, $userID);

        // Generate new Refresh Token
        $refreshToken = bin2hex(random_bytes(32));
        try {
            $refreshExpireInterval = new DateInterval($_ENV['REFRESH_TOKEN_INTERVAL'] ?? 'P7D'); // P7D = 7 dias
        } catch(Exception $e) {
            return [
                'status' => false,
                'code' => 500,
                'message' => $e->getMessage()
            ];
        }
        $refreshExpiresAt = (new DateTime())->add($refreshExpireInterval)->format('Y-m-d H:i:s');

        $this->connection->insertRefreshToken($this->cnx, $refreshToken, $userID, $refreshExpiresAt);

        // Generate new Access Token
        $accessToken = $this->accessTokenHandler->generate($userID);

        return [
            'status' => true,
            'code' => 200,
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
        ];
    }

    /**
     * Validates an Access Token.
     */
    public function authenticate(string $accessToken): array
    {
        $payload = $this->accessTokenHandler->validate($accessToken);

        if (!$payload) {
            return ['status' => false, 'code' => 401, 'message' => 'Unauthorized: Invalid or expired token.'];
        }

        return ['status' => true, 'code' => 200, 'user_id' => $payload['sub']];
    }

    /**
     * Generate a new pair of tokens using a valid Refresh Token.
     */
    public function refresh(string $refreshToken): array
    {
        $storedToken = $this->connection->findRefreshToken($this->cnx, $refreshToken);

        // Token not found or expired
        if (!$storedToken || strtotime($storedToken['expires_at']) < time()) {
            if ($storedToken) {
                // Remove the expired token from the database for security.
                $this->connection->deleteRefreshToken($this->cnx, $refreshToken);
            }
            return ['status' => false, 'code' => 401, 'message' => 'Unauthorized: Invalid or expired refresh token.'];
        }

        // Invalidates the old refresh token
        $this->connection->deleteRefreshToken($this->cnx, $refreshToken);

        // Generate a new pair of tokens
        try {
            return $this->login($storedToken['user_id']);
        } catch (RandomException $e) {
            return ['status' => false, 'code' => 500, 'message' => 'Could not generate new tokens.'];
        }
    }

    /**
     * Logout by invalidating the Refresh Token.
     */
    public function logout(string $refreshToken): array
    {
        $deleted = $this->connection->deleteRefreshToken($this->cnx, $refreshToken);

        if (!$deleted) {
            return ['status' => false, 'code' => 404, 'message' => 'Refresh token not found.'];
        }

        return ['status' => true, 'code' => 200, 'message' => 'Logout successful.'];
    }
}
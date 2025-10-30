<?php

namespace AuthToken;

use AuthToken\Database\ConnectionDB;
use AuthToken\Exception\ErrorConnection;
use DateInterval;
use DateTime;
use Exception;
use PDOException;
use Random\RandomException;

/**
 * Class Auth
 *
 * Handles the primary authentication logic including login, token validation,
 * session refreshing, and logout. It acts as a facade, orchestrating the
 * interactions between the AccessToken handler and the database connection.
 */
class Auth
{
    private ConnectionDB $connection;
    private \PDO|PDOException $cnx;
    private AccessToken $accessTokenHandler;

    /**
     * Auth constructor.
     *
     * Initializes the authentication service by establishing a database connection
     * and preparing the AccessToken handler.
     *
     * @throws ErrorConnection if the database connection fails.
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
     * Logs a user in by generating a new pair of access and refresh tokens.
     *
     * This method first invalidates any existing refresh tokens for the specified user,
     * ensuring that each login creates a new, unique session.
     *
     * @param int|string $userID The unique identifier for the user.
     * @return array An associative array containing the operation status, HTTP code,
     *               'access_token', and 'refresh_token'.
     * @throws RandomException if the cryptographic random number generator fails.
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
     * Validates an access token to authenticate a request.
     *
     * Checks the token's signature and expiration time.
     *
     * @param string $accessToken The JWT access token to be validated.
     * @return array An associative array with the authentication status.
     *               On success, it includes 'status', 'code', and 'user_id'.
     *               On failure, it includes 'status', 'code', and 'message'.
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
     * Refreshes a session using a valid refresh token.
     *
     * This method implements refresh token rotation: upon successful validation, the old
     * refresh token is invalidated and a new pair of access and refresh tokens is issued.
     *
     * @param string $refreshToken The refresh token used to generate new tokens.
     * @return array A new set of tokens ('access_token', 'refresh_token') on success,
     *               or an error message on failure.
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
     * Logs a user out by invalidating their refresh token.
     *
     * This effectively terminates the user's session, preventing the refresh token
     * from being used to obtain new access tokens.
     *
     * @param string $refreshToken The refresh token of the session to be terminated.
     * @return array An array indicating the result of the logout operation.
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
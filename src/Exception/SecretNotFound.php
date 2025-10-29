<?php

namespace AuthToken\Exception;
use Exception;

/**
 * The secret key was not found in the environment (.env).
 * Use `php auth-token secret` to generate and store AUTHTOKEN_APP_SECRET in the project's .env file.
 */
class SecretNotFound extends Exception
{
    /**
     * Constructor of the SecretNotFound class
     * @param $message
     * @param $code
     * @param Exception|null $previous
     */
    public function __construct($message = "", $code = 0, Exception $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}
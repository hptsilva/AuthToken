<?php

namespace AuthToken\Exception;
use Exception;

/**
 * The secret file was not found in src/secret/.
 * Use ```php auth-token secret``` to generate a secret key.
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
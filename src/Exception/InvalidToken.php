<?php

namespace AuthToken\Exception;
use Exception;

/**
 * The structure of the token provided is not valid.
 */
class InvalidToken extends Exception
{
    /**
     * Constructor of the InvalidToken class
     * @param $message
     * @param $code
     * @param Exception|null $previous
     */
    public function __construct($message = "", $code = 1, Exception $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}
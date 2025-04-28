<?php

namespace AuthToken\exception;
use Exception;

/**
 * Error connecting to the database
 */
class ErrorConnection extends Exception
{
    /**
     * Constructor of the ErrorConnection class
     * @param $message
     * @param $code
     * @param Exception|null $previous
     */
    public function __construct($message = "", $code = 3, Exception $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}
<?php

namespace AuthToken\Exception;

use Exception;

/**
 * The secret key is corrupted
 * Use ```php auth-token secret``` to regenerate a secret key.
 */
class CorruptedSecretKey extends Exception
{
    /**
     * Constructor of the CorruptedSecretKey class
     * @param $message
     * @param $code
     * @param Exception|null $previous
     */
    public function __construct($message = "", $code = 3, Exception $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}
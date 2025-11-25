<?php

namespace Tabula17\Satelles\Omnia\Roga\Exception;

use Exception;

class RogaException extends \Exception
{
    /**
     * Creates a new validation exception.
     *
     * @param string $message The error message
     * @param int $code The error code
     * @param Exception|null $previous The previous exception
     */
    public function __construct(string $message, int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
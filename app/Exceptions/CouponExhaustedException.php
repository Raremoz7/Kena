<?php

namespace App\Exceptions;

/** O cupom esgotou entre o preview e a confirmação do pedido. */
class CouponExhaustedException extends \RuntimeException
{
    public function __construct(string $message = 'Este cupom atingiu o limite de usos.')
    {
        parent::__construct($message);
    }
}

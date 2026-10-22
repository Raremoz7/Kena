<?php

namespace App\Exceptions;

use App\Models\User;
use RuntimeException;

/**
 * O e-mail do convidado já pertence a uma conta leve (sem senha/login social).
 * Não dá para logar nela só por digitar o e-mail: o dono precisa provar acesso
 * à caixa de entrada pelo magic-link.
 */
class GuestAccountNeedsVerificationException extends RuntimeException
{
    public function __construct(public readonly User $user)
    {
        parent::__construct('Conta existente exige verificação por e-mail.');
    }
}

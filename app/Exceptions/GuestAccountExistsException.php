<?php

namespace App\Exceptions;

use RuntimeException;

/** O e-mail do convidado já entra com Google ou senha: a pessoa faz login, sem código. */
class GuestAccountExistsException extends RuntimeException {}

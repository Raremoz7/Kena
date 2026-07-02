<?php

namespace App\Exceptions;

use RuntimeException;

/** O e-mail do convidado já pertence a uma conta com senha/login social. */
class GuestAccountExistsException extends RuntimeException {}

<?php

namespace App\Exceptions;

use RuntimeException;

/** O CPF informado já pertence a outra conta (o limite de ingressos é por CPF). */
class CpfInUseException extends RuntimeException {}

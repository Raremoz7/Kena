<?php

namespace App\Exceptions;

use RuntimeException;

/** Falha ao comunicar com o gateway de pagamento. */
class PaymentException extends RuntimeException {}

<?php

namespace App\Services;

/** Sinal interno de rollback: assento do pedido já pertence a outra compra. */
class SeatsUnavailableForIssuance extends \RuntimeException {}

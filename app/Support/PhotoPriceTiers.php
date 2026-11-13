<?php

namespace App\Support;

/**
 * Faixas de preço da galeria de fotos: [{min_qty, unit_price_cents}, ...].
 *
 * Regras: a primeira faixa começa em 1 foto, as quantidades crescem e o preço
 * unitário só cai de uma faixa para a próxima.
 */
final class PhotoPriceTiers
{
    public const MAX_TIERS = 8;

    /**
     * @param  array<int, mixed>  $tiers
     * @return list<string> mensagens de erro (vazio = faixas válidas)
     */
    public static function errors(array $tiers): array
    {
        $tiers = array_values($tiers);

        if ($tiers === []) {
            return ['Informe pelo menos uma faixa de preço.'];
        }

        if (count($tiers) > self::MAX_TIERS) {
            return ['Use no máximo '.self::MAX_TIERS.' faixas de preço.'];
        }

        $errors = [];
        if ((int) ($tiers[0]['min_qty'] ?? 0) !== 1) {
            $errors[] = 'A primeira faixa precisa começar em 1 foto.';
        }

        for ($i = 1; $i < count($tiers); $i++) {
            if ((int) $tiers[$i]['min_qty'] <= (int) $tiers[$i - 1]['min_qty']) {
                $errors[] = 'As quantidades das faixas precisam ser crescentes.';
                break;
            }
        }

        for ($i = 1; $i < count($tiers); $i++) {
            if ((int) $tiers[$i]['unit_price_cents'] >= (int) $tiers[$i - 1]['unit_price_cents']) {
                $errors[] = 'O preço por foto precisa diminuir a cada faixa.';
                break;
            }
        }

        return $errors;
    }

    /**
     * Normaliza para a forma guardada no banco (inteiros, em ordem).
     *
     * @param  array<int, array{min_qty: mixed, unit_price_cents: mixed}>  $tiers
     * @return list<array{min_qty: int, unit_price_cents: int}>
     */
    public static function normalize(array $tiers): array
    {
        return array_map(fn (array $t): array => [
            'min_qty' => (int) $t['min_qty'],
            'unit_price_cents' => (int) $t['unit_price_cents'],
        ], array_values($tiers));
    }
}

<?php

namespace App\Services\Photos;

use App\Models\PhotoGallery;

/**
 * Preço das fotos, sempre calculado aqui a partir das faixas da galeria (o
 * valor que vem do navegador não vale nada). Mesma regra da vitrine
 * (resources/js/lib/photos/pricing.ts): todas as fotos saem pelo preço da
 * maior faixa alcançada, não só as que passaram do limite.
 */
class PhotoPricing
{
    /**
     * @return array{qty: int, unit_price_cents: int, base_price_cents: int, subtotal_cents: int, discount_cents: int, total_cents: int}
     */
    public function quote(PhotoGallery $gallery, int $qty): array
    {
        $tiers = $gallery->sortedTiers();
        if ($tiers === [] || $qty < 1) {
            throw new \InvalidArgumentException('Galeria sem faixa de preço ou quantidade inválida.');
        }

        $base = (int) $tiers[0]['unit_price_cents'];
        $unit = $base;
        foreach ($tiers as $tier) {
            if ($qty >= (int) $tier['min_qty']) {
                $unit = (int) $tier['unit_price_cents'];
            }
        }

        return [
            'qty' => $qty,
            'unit_price_cents' => $unit,
            'base_price_cents' => $base,
            // Subtotal a preço avulso e o desconto do pacote: o comprador vê a economia.
            'subtotal_cents' => $qty * $base,
            'discount_cents' => max(0, $qty * ($base - $unit)),
            'total_cents' => $qty * $unit,
        ];
    }
}

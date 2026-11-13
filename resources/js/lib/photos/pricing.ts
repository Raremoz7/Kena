export interface PriceTier {
    minQty: number;
    unitPrice: number;
}

export interface PhotoQuote {
    qty: number;
    /** Preço de cada foto na faixa alcançada. */
    unitPrice: number;
    /** Preço avulso (primeira faixa), para mostrar a economia. */
    basePrice: number;
    total: number;
    savings: number;
    /** Próxima faixa ainda não alcançada, com quanto falta e quanto ficaria. */
    next: {
        minQty: number;
        unitPrice: number;
        missing: number;
        total: number;
    } | null;
}

/**
 * Todas as fotos saem pelo preço da maior faixa alcançada: com 10 fotos, as 10
 * custam o valor da faixa de 10, não só as que passaram do limite.
 */
export function quotePhotos(qty: number, tiers: PriceTier[]): PhotoQuote {
    const sorted = [...tiers].sort((a, b) => a.minQty - b.minQty);
    const basePrice = sorted[0]?.unitPrice ?? 0;
    const current =
        [...sorted].reverse().find((t) => qty >= t.minQty) ?? sorted[0];
    const unitPrice = current?.unitPrice ?? 0;
    const nextTier = sorted.find((t) => t.minQty > qty);

    return {
        qty,
        unitPrice,
        basePrice,
        total: qty * unitPrice,
        savings: qty * (basePrice - unitPrice),
        next: nextTier
            ? {
                  minQty: nextTier.minQty,
                  unitPrice: nextTier.unitPrice,
                  missing: nextTier.minQty - qty,
                  total: nextTier.minQty * nextTier.unitPrice,
              }
            : null,
    };
}

/** "1 foto", "3 fotos". */
export function photoCount(n: number): string {
    return `${n} ${n === 1 ? 'foto' : 'fotos'}`;
}

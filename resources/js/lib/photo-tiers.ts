export interface TierDraft {
    min_qty: string;
    price: string;
}

export interface ParsedTier {
    minQty: number;
    cents: number;
}

export interface TierWarning {
    /** Índice da faixa onde o problema começa. */
    index: number;
    /** A partir de quantas fotos o cliente paga mais do que pagaria pela faixa seguinte. */
    fromQty: number;
    /** Faixa seguinte, mais barata no total. */
    nextQty: number;
    cost: number;
    nextCost: number;
    suggestion: { minQty: number; cents: number } | null;
}

/** "35", "35,5" ou "35.50" para centavos; null se não for um valor válido. */
export function parseCents(value: string): number | null {
    const normalized = value.trim().replace(/\s/g, '').replace(',', '.');

    if (normalized === '' || !/^\d+(\.\d{1,2})?$/.test(normalized)) {
        return null;
    }

    return Math.round(Number(normalized) * 100);
}

export function parseTiers(drafts: TierDraft[]): ParsedTier[] | null {
    const parsed: ParsedTier[] = [];

    for (const draft of drafts) {
        const cents = parseCents(draft.price);
        const minQty = /^\d+$/.test(draft.min_qty.trim())
            ? Number(draft.min_qty)
            : NaN;

        if (cents === null || cents <= 0 || !Number.isFinite(minQty)) {
            return null;
        }

        parsed.push({ minQty, cents });
    }

    return parsed;
}

/** Mesmas regras do servidor, para avisar enquanto a pessoa digita. */
export function tierErrors(tiers: ParsedTier[]): string[] {
    const errors: string[] = [];

    if (tiers.length === 0) {
        return ['Informe pelo menos uma faixa de preço.'];
    }

    if (tiers[0].minQty !== 1) {
        errors.push('A primeira faixa precisa começar em 1 foto.');
    }

    if (tiers.some((t, i) => i > 0 && t.minQty <= tiers[i - 1].minQty)) {
        errors.push('As quantidades das faixas precisam ser crescentes.');
    }

    if (tiers.some((t, i) => i > 0 && t.cents >= tiers[i - 1].cents)) {
        errors.push('O preço por foto precisa diminuir a cada faixa.');
    }

    return errors;
}

/**
 * Procura quantidades em que levar mais fotos sai mais barato: com N fotos na
 * faixa atual o cliente paga N x preço, e pode pagar menos levando as da faixa
 * seguinte. Sugere uma faixa intermediária que resolve o intervalo todo.
 * Só olha faixas válidas (crescentes e com preço decrescente).
 */
export function tierWarnings(tiers: ParsedTier[]): TierWarning[] {
    if (tierErrors(tiers).length > 0) {
        return [];
    }

    const warnings: TierWarning[] = [];

    for (let i = 0; i < tiers.length - 1; i++) {
        const current = tiers[i];
        const next = tiers[i + 1];
        const nextCost = next.minQty * next.cents;
        // Menor N dessa faixa em que N fotos custam mais que as da faixa seguinte.
        const fromQty = Math.floor(nextCost / current.cents) + 1;

        if (fromQty >= next.minQty) {
            continue;
        }

        const start = Math.max(fromQty, current.minQty + 1);
        // Maior preço que não pune quem passa a faixa nova: N fotos no preço novo
        // custam o mesmo que N-1 no preço atual. Um passo de cada vez: ao aplicar,
        // o aviso seguinte (se houver) sugere a faixa depois dela.
        const cents = Math.max(
            Math.ceil((current.cents * (start - 1)) / start),
            next.cents + 1,
        );

        warnings.push({
            index: i,
            fromQty,
            nextQty: next.minQty,
            cost: (next.minQty - 1) * current.cents,
            nextCost,
            // Sem espaço entre as duas faixas não cabe uma intermediária.
            suggestion:
                start < next.minQty && cents < current.cents
                    ? { minQty: start, cents }
                    : null,
        });
    }

    return warnings;
}

export function formatBRL(cents: number): string {
    return (cents / 100).toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    });
}

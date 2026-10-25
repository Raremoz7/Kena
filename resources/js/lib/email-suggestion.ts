/** Mesma lista e regra de App\Support\EmailDomain (servidor). */
const COMMON = [
    'gmail.com',
    'hotmail.com',
    'outlook.com',
    'live.com',
    'msn.com',
    'icloud.com',
    'yahoo.com',
    'yahoo.com.br',
    'hotmail.com.br',
    'outlook.com.br',
    'bol.com.br',
    'uol.com.br',
    'terra.com.br',
    'globo.com',
    'ig.com.br',
];

function distance(a: string, b: string): number {
    let prev = Array.from({ length: b.length + 1 }, (_, i) => i);

    for (let i = 1; i <= a.length; i++) {
        const row = [i];

        for (let j = 1; j <= b.length; j++) {
            row[j] = Math.min(
                prev[j] + 1,
                row[j - 1] + 1,
                prev[j - 1] + (a[i - 1] === b[j - 1] ? 0 : 1),
            );
        }

        prev = row;
    }

    return prev[b.length];
}

/** "ana@gmial.com" → "ana@gmail.com"; null quando não parece erro de digitação. */
export function emailSuggestion(email: string): string | null {
    const value = email.trim();
    const at = value.lastIndexOf('@');

    if (at < 1) {
        return null;
    }

    const domain = value.slice(at + 1).toLowerCase();

    if (!domain.includes('.') || COMMON.includes(domain)) {
        return null;
    }

    let best: string | null = null;
    let bestDistance = 3;

    for (const candidate of COMMON) {
        const d = distance(domain, candidate);

        if (d < bestDistance) {
            best = candidate;
            bestDistance = d;
        }
    }

    return best === null ? null : `${value.slice(0, at + 1)}${best}`;
}

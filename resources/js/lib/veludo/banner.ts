// Degradê do banner do evento: faixa horizontal (esquerda → direita) por cima
// da foto, com opacidade própria em cada ponta. Usado no topo do evento e na
// prévia do painel — os dois precisam desenhar exatamente o mesmo.

export interface BannerGradient {
    from: string; // #rrggbb
    fromOpacity: number; // 0–100
    to: string;
    toOpacity: number;
}

/** "#rrggbb" + opacidade 0–100 → "#rrggbbaa". Fora do padrão vira preto. */
export function hexWithAlpha(hex: string, opacity: number): string {
    const base = /^#[0-9a-f]{6}$/i.test(hex) ? hex : '#000000';
    const alpha = Math.round((Math.min(100, Math.max(0, opacity)) / 100) * 255);

    return `${base}${alpha.toString(16).padStart(2, '0')}`;
}

export function bannerGradient({
    from,
    fromOpacity,
    to,
    toOpacity,
}: BannerGradient): string {
    return `linear-gradient(90deg, ${hexWithAlpha(from, fromOpacity)}, ${hexWithAlpha(to, toOpacity)})`;
}

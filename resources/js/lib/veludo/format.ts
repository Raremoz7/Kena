/** Formata reais no padrão pt-BR; omite centavos quando o valor é inteiro. */
export function formatBRL(value: number): string {
    const hasCents = Math.round(Math.abs(value) * 100) % 100 !== 0;

    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
        minimumFractionDigits: hasCents ? 2 : 0,
        maximumFractionDigits: 2,
    }).format(value);
}

/**
 * "Pular para o conteúdo" (WCAG 2.4.1): invisível até receber foco pelo Tab;
 * leva direto ao <main id="conteudo">, sem passar pelo menu.
 */
export function SkipLink({ href = '#conteudo' }: { href?: string }) {
    return (
        <a
            href={href}
            className="sr-only z-[80] rounded-btn bg-accent px-4 py-2 font-body text-sm font-semibold text-accent-fg focus:not-sr-only focus:fixed focus:top-3 focus:left-3"
        >
            Pular para o conteúdo
        </a>
    );
}

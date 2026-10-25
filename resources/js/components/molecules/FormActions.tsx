import type { ReactNode } from 'react';

/**
 * Rodapé de formulário do painel. No celular gruda acima da barra inferior,
 * com botões em largura cheia, para salvar sem rolar até o fim.
 */
export function FormActions({ children }: { children: ReactNode }) {
    return (
        <div className="sticky bottom-[calc(4rem+env(safe-area-inset-bottom))] z-20 -mx-4 flex gap-3 border-t border-border bg-bg/95 px-4 py-3 backdrop-blur-xl *:flex-1 sm:-mx-8 sm:px-8 md:static md:mx-0 md:justify-end md:border-0 md:bg-transparent md:p-0 md:backdrop-blur-none md:*:flex-none">
            {children}
        </div>
    );
}

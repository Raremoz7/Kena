import { router } from '@inertiajs/react';

/** Um item de `links` do paginator do Laravel. */
export interface PaginatorLink {
    url: string | null;
    label: string;
    active: boolean;
}

/** Envelope mínimo do paginator do Laravel consumido pelas telas do painel. */
export interface Paginator<T> {
    data: T[];
    links: PaginatorLink[];
}

/**
 * O Laravel injeta HTML nos labels de "« Anterior" / "Próximo »".
 * Removemos as tags e decodificamos as entidades das setas.
 */
function linkLabel(label: string): string {
    return label
        .replace(/<[^>]*>/g, '')
        .replace(/&laquo;/g, '«')
        .replace(/&raquo;/g, '»')
        .trim();
}

/**
 * Navegação de páginas do painel. Some quando há uma página só — o paginator
 * do Laravel sempre devolve ao menos "anterior", "1" e "próximo".
 */
export function Pagination({ links }: { links: PaginatorLink[] }) {
    if (links.length <= 3) {
        return null;
    }

    return (
        <nav aria-label="Paginação" className="mt-4 flex flex-wrap gap-1">
            {links.map((link, i) => (
                <button
                    key={i}
                    type="button"
                    disabled={!link.url}
                    aria-current={link.active ? 'page' : undefined}
                    onClick={() => link.url && router.get(link.url, {}, { preserveScroll: true })}
                    className={`rounded-btn px-3 py-1.5 font-body text-sm transition-colors ${
                        link.active
                            ? 'bg-accent text-accent-fg'
                            : 'border border-border-strong text-muted-foreground hover:bg-surface-2 disabled:opacity-40'
                    }`}
                >
                    {linkLabel(link.label)}
                </button>
            ))}
        </nav>
    );
}

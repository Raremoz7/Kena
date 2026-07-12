import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';

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
 * O paginator do Laravel emite o rótulo do meio como número ou como "..."
 * (separador, sem url). Trocamos as reticências pelo caractere correto.
 */
function pageLabel(label: string): string {
    return label.replace(/^\.\.\.$/, '…');
}

const base = 'inline-flex min-h-8 items-center rounded-btn px-3 py-1.5 font-body text-sm transition-colors';
const inactive = 'border border-border-strong text-muted-foreground';

function Item({
    link,
    label,
    ariaLabel,
}: {
    link: PaginatorLink;
    label: string;
    ariaLabel?: string;
}) {
    // Extremidades e o separador "…" vêm com url null: nada para onde navegar.
    if (!link.url) {
        return (
            <span aria-disabled="true" className={cn(base, inactive, 'opacity-40')}>
                {label}
            </span>
        );
    }

    return (
        <Link
            href={link.url}
            preserveScroll
            aria-label={ariaLabel}
            aria-current={link.active ? 'page' : undefined}
            className={cn(
                base,
                link.active ? 'bg-accent text-accent-fg' : cn(inactive, 'hover:bg-surface-2'),
            )}
        >
            {label}
        </Link>
    );
}

/**
 * Navegação de páginas do painel. Some quando há uma página só — o paginator
 * do Laravel sempre devolve ao menos "anterior", "1" e "próximo".
 *
 * Cada página é um <Link> de verdade (não button), para que ctrl/cmd+clique e
 * clique do meio abram em nova aba.
 *
 * Os rótulos de "anterior"/"próxima" são identificados pela POSIÇÃO no array
 * (primeiro e último), não pelo texto: o Laravel os emite em inglês
 * ("&laquo; Previous") quando não há arquivo de tradução publicado.
 */
export function Pagination({ links }: { links: PaginatorLink[] }) {
    if (links.length <= 3) {
        return null;
    }

    const previous = links[0];
    const next = links[links.length - 1];
    const pages = links.slice(1, -1);

    return (
        <nav aria-label="Paginação" className="mt-4 flex flex-wrap gap-1">
            <Item link={previous} label="« Anterior" ariaLabel="Página anterior" />

            {pages.map((link, i) => (
                <Item
                    key={i}
                    link={link}
                    label={pageLabel(link.label)}
                    ariaLabel={`Página ${link.label}`}
                />
            ))}

            <Item link={next} label="Próxima »" ariaLabel="Próxima página" />
        </nav>
    );
}

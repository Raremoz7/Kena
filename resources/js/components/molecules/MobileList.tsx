import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Icon } from '@/components/atoms/Icon';
import { cn } from '@/lib/utils';

/**
 * Lista em cartão para o celular — par das tabelas do painel, que só aparecem
 * a partir de md. Cada linha da tabela vira um item tocável.
 */
export function MobileList({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    return (
        <ul
            className={cn(
                'divide-y divide-border overflow-hidden rounded-card border border-border bg-surface md:hidden',
                className,
            )}
        >
            {children}
        </ul>
    );
}

interface MobileListItemProps {
    title: ReactNode;
    subtitle?: ReactNode;
    /** Linha de detalhes abaixo do subtítulo (datas, contagens). */
    meta?: ReactNode;
    /** Canto direito: status, valor. */
    aside?: ReactNode;
    /** Ações abaixo do conteúdo (botões pequenos). */
    actions?: ReactNode;
    /** Item inteiro vira link (mostra a seta). */
    href?: string;
}

export function MobileListItem({
    title,
    subtitle,
    meta,
    aside,
    actions,
    href,
}: MobileListItemProps) {
    const body = (
        <div className="flex items-start gap-3">
            <div className="min-w-0 flex-1">
                <div className="font-body text-[15px] leading-snug font-medium text-foreground">
                    {title}
                </div>
                {subtitle && (
                    <div className="mt-0.5 truncate font-body text-[13px] text-muted-foreground">
                        {subtitle}
                    </div>
                )}
                {meta && (
                    <div className="mt-1.5 font-body text-xs text-faint">
                        {meta}
                    </div>
                )}
            </div>
            {aside && (
                <div className="flex shrink-0 flex-col items-end gap-1.5 text-right">
                    {aside}
                </div>
            )}
            {href && (
                <Icon
                    name="chevron-right"
                    size={18}
                    className="mt-0.5 shrink-0 self-center text-faint"
                />
            )}
        </div>
    );

    return (
        <li>
            {href ? (
                <Link
                    href={href}
                    className="block px-4 py-3.5 transition-colors active:bg-surface-2"
                >
                    {body}
                </Link>
            ) : (
                <div className="px-4 py-3.5">{body}</div>
            )}
            {actions && (
                <div className="-mt-1 flex flex-wrap items-center gap-1 px-2.5 pb-2.5">
                    {actions}
                </div>
            )}
        </li>
    );
}

/** Vazio da lista mobile, no mesmo cartão. */
export function MobileListEmpty({ children }: { children: ReactNode }) {
    return (
        <li className="px-4 py-10 text-center font-body text-sm text-muted-foreground">
            {children}
        </li>
    );
}

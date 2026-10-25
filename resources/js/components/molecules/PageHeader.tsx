import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Button } from '@/components/atoms/Button';
import { Icon } from '@/components/atoms/Icon';
import type { IconName } from '@/components/atoms/Icon';
import { cn } from '@/lib/utils';

interface PrimaryAction {
    label: string;
    href: string;
    icon?: IconName;
}

interface PageHeaderProps {
    title: string;
    description?: ReactNode;
    /** Link de volta (telas de detalhe/formulário). */
    back?: { href: string; label: string };
    /**
     * Ação principal da tela ("Novo evento"). No desktop vira botão no
     * cabeçalho; no celular, um botão flutuante acima da barra inferior.
     */
    primary?: PrimaryAction;
    /** Ações secundárias: no celular quebram para uma linha própria. */
    actions?: ReactNode;
}

/**
 * Cabeçalho padrão das telas do painel. Título menor no celular para sobrar
 * tela para o conteúdo, e ações que não espremem o título.
 */
export function PageHeader({
    title,
    description,
    back,
    primary,
    actions,
}: PageHeaderProps) {
    return (
        <header className="mb-5 sm:mb-6">
            {back && (
                <Link
                    href={back.href}
                    className="-ml-1 inline-flex min-h-9 items-center gap-1 rounded-btn pr-2 font-body text-sm text-muted-foreground transition-colors hover:text-foreground"
                >
                    <Icon name="chevron-left" size={18} /> {back.label}
                </Link>
            )}
            <div className="flex flex-wrap items-end justify-between gap-x-4 gap-y-3">
                <div className="min-w-0">
                    <h1 className="font-display text-display-md text-foreground uppercase sm:text-display-lg">
                        {title}
                    </h1>
                    {description && (
                        <p className="mt-1 max-w-prose font-body text-sm text-muted-foreground">
                            {description}
                        </p>
                    )}
                </div>
                {(actions || primary) && (
                    <div className="flex w-full flex-wrap items-center gap-2 sm:w-auto">
                        {actions}
                        {primary && (
                            <Button asChild className="hidden md:inline-flex">
                                <Link href={primary.href}>
                                    <Icon
                                        name={primary.icon ?? 'plus'}
                                        size={18}
                                    />
                                    {primary.label}
                                </Link>
                            </Button>
                        )}
                    </div>
                )}
            </div>
            {primary && <Fab {...primary} />}
        </header>
    );
}

/** Botão flutuante do celular, logo acima da barra de navegação inferior. */
export function Fab({ label, href, icon = 'plus' }: PrimaryAction) {
    return (
        <Link
            href={href}
            className={cn(
                'fixed right-4 bottom-[calc(5rem+env(safe-area-inset-bottom))] z-30 inline-flex h-14 items-center gap-2 rounded-2xl bg-accent pr-5 pl-4 font-body text-sm font-semibold text-accent-fg shadow-[0_8px_24px_-6px_rgba(0,0,0,0.35)] transition-transform active:scale-95 md:hidden',
            )}
        >
            <Icon name={icon} size={22} strokeWidth={2} />
            {label}
        </Link>
    );
}

/** Contêiner das telas do painel: respiro menor no celular, largura por tipo de tela. */
export function AdminPage({
    width = 'full',
    children,
}: {
    width?: 'full' | 'narrow' | 'medium';
    children: ReactNode;
}) {
    return (
        <div
            className={cn(
                'px-4 pt-5 pb-8 sm:px-8 sm:py-8',
                width === 'narrow' && 'mx-auto max-w-2xl',
                width === 'medium' && 'mx-auto max-w-4xl',
            )}
        >
            {children}
        </div>
    );
}

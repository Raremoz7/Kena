import { Link } from '@inertiajs/react';
import { useState } from 'react';
import { Icon } from '@/components/atoms/Icon';
import type { IconName } from '@/components/atoms/Icon';
import { BottomSheet } from '@/components/molecules/BottomSheet';
import {
    AdminUserCard,
    isNavActive,
    useAdminNav,
} from '@/components/organisms/AdminSidebar';
import { cn } from '@/lib/utils';

function TabItem({
    label,
    icon,
    active,
}: {
    label: string;
    icon: IconName;
    active: boolean;
}) {
    return (
        <>
            <span
                className={cn(
                    'flex h-8 w-14 items-center justify-center rounded-full transition-colors duration-200',
                    active ? 'bg-accent/12 text-accent-text' : '',
                )}
            >
                <Icon name={icon} size={22} strokeWidth={active ? 1.9 : 1.5} />
            </span>
            <span
                className={cn(
                    'font-body text-[11px] leading-none',
                    active
                        ? 'font-semibold text-foreground'
                        : 'font-medium text-muted-foreground',
                )}
            >
                {label}
            </span>
        </>
    );
}

const tabClass =
    'flex min-w-0 flex-1 flex-col items-center justify-center gap-1 outline-none focus-visible:[&>span:first-child]:ring-2 focus-visible:[&>span:first-child]:ring-accent active:[&>span:first-child]:scale-95 [&>span:first-child]:transition-transform';

/**
 * Barra de navegação inferior do painel no mobile, no padrão de app: os destinos
 * do dia a dia ficam a um toque e o resto mora na folha "Mais", junto da conta.
 */
export function AdminBottomNav() {
    const { url, groups } = useAdminNav();
    const [moreOpen, setMoreOpen] = useState(false);

    const items = groups.flatMap((g) => g.items);
    const primary = items.filter((item) => item.primary);
    const rest = items.filter((item) => !item.primary);
    const moreActive = rest.some((item) => isNavActive(url, item.href));

    return (
        <>
            <nav
                aria-label="Navegação do painel"
                className="fixed inset-x-0 bottom-0 z-40 border-t border-border bg-surface/90 pb-[env(safe-area-inset-bottom)] backdrop-blur-xl md:hidden"
            >
                <ul className="mx-auto flex h-16 max-w-lg items-stretch">
                    {primary.map((item) => {
                        const active = isNavActive(url, item.href);

                        return (
                            <li key={item.href} className="flex min-w-0 flex-1">
                                <Link
                                    href={item.href}
                                    aria-current={active ? 'page' : undefined}
                                    className={tabClass}
                                >
                                    <TabItem
                                        label={item.short ?? item.label}
                                        icon={item.icon}
                                        active={active}
                                    />
                                </Link>
                            </li>
                        );
                    })}
                    <li className="flex min-w-0 flex-1">
                        <button
                            type="button"
                            onClick={() => setMoreOpen(true)}
                            aria-haspopup="dialog"
                            aria-expanded={moreOpen}
                            className={tabClass}
                        >
                            <TabItem
                                label="Mais"
                                icon="grid"
                                active={moreActive || moreOpen}
                            />
                        </button>
                    </li>
                </ul>
            </nav>

            <BottomSheet
                open={moreOpen}
                onOpenChange={setMoreOpen}
                title="Mais"
                hideTitle
            >
                {rest.length > 0 && (
                    <ul className="grid grid-cols-3 gap-2 pb-3">
                        {rest.map((item) => {
                            const active = isNavActive(url, item.href);

                            return (
                                <li key={item.href}>
                                    <Link
                                        href={item.href}
                                        onClick={() => setMoreOpen(false)}
                                        aria-current={
                                            active ? 'page' : undefined
                                        }
                                        className={cn(
                                            'flex aspect-[5/4] flex-col items-center justify-center gap-2 rounded-card border px-2 text-center font-body text-xs font-medium transition-colors active:scale-[0.97]',
                                            active
                                                ? 'border-accent/40 bg-accent/8 text-accent-text'
                                                : 'border-border bg-bg text-foreground hover:bg-surface-2',
                                        )}
                                    >
                                        <Icon name={item.icon} size={24} />
                                        {item.label}
                                    </Link>
                                </li>
                            );
                        })}
                    </ul>
                )}
                <div className="rounded-card border border-border bg-bg p-3">
                    <AdminUserCard onNavigate={() => setMoreOpen(false)} />
                </div>
            </BottomSheet>
        </>
    );
}

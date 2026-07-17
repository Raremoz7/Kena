import { Link, usePage } from '@inertiajs/react';
import { Avatar } from '@/components/atoms/Avatar';
import { Icon } from '@/components/atoms/Icon';
import type { IconName } from '@/components/atoms/Icon';
import { KenaMark } from '@/components/atoms/KenaMark';
import { cn } from '@/lib/utils';

interface NavItem {
    label: string;
    href?: string;
    icon: IconName;
    soon?: boolean;
    /** Só organizador/admin — staff não vê. */
    organizerOnly?: boolean;
}

const nav: NavItem[] = [
    {
        label: 'Visão geral',
        href: '/painel',
        icon: 'home',
        organizerOnly: true,
    },
    {
        label: 'Eventos',
        href: '/painel/eventos',
        icon: 'ticket',
        organizerOnly: true,
    },
    {
        label: 'Pedidos',
        href: '/painel/pedidos',
        icon: 'agenda',
        organizerOnly: true,
    },
    {
        label: 'Cupons',
        href: '/painel/cupons',
        icon: 'tag',
        organizerOnly: true,
    },
    {
        label: 'Locais',
        href: '/painel/locais',
        icon: 'map-pin',
        organizerOnly: true,
    },
    {
        label: 'Equipe',
        href: '/painel/equipe',
        icon: 'shield',
        organizerOnly: true,
    },
    { label: 'Check-in', href: '/painel/checkin', icon: 'qr' },
    {
        label: 'Configurações',
        href: '/painel/config',
        icon: 'shield',
        organizerOnly: true,
    },
];

/** Marca do painel — compartilhada pela sidebar, pelo header mobile e pela gaveta. */
export function AdminBrand() {
    return (
        <>
            <KenaMark className="size-7" />
            <span className="font-display text-base font-semibold tracking-[0.04em] text-foreground uppercase">
                Kena
            </span>
            <span className="kicker ml-0.5 text-faint">Painel</span>
        </>
    );
}

/**
 * Itens de navegação do painel + rodapé do usuário. Vive na sidebar (desktop) e
 * dentro da gaveta (mobile) — a lista é definida uma vez só, aqui.
 */
export function AdminNavList({ onNavigate }: { onNavigate?: () => void } = {}) {
    const { url, props } = usePage();
    const user = props.auth?.user;
    const canOrganize = user?.role === 'organizer' || Boolean(user?.is_admin);
    const items = nav.filter((item) => !item.organizerOnly || canOrganize);

    return (
        <>
            <nav className="flex flex-1 flex-col gap-1 p-3">
                {items.map((item) => {
                    if (item.soon || !item.href) {
                        return (
                            <span
                                key={item.label}
                                className="flex items-center gap-3 rounded-btn px-3 py-2 font-body text-sm text-faint"
                            >
                                <Icon name={item.icon} size={18} />
                                {item.label}
                                <span className="kicker ml-auto text-[9px] text-faint">
                                    em breve
                                </span>
                            </span>
                        );
                    }

                    const active =
                        url === item.href ||
                        (item.href !== '/painel' &&
                            url.startsWith(item.href));

                    return (
                        <Link
                            key={item.label}
                            href={item.href}
                            onClick={onNavigate}
                            className={cn(
                                'flex items-center gap-3 rounded-btn px-3 py-2 font-body text-sm transition-colors',
                                active
                                    ? 'border-l-2 border-accent bg-surface-2 font-medium text-foreground'
                                    : 'text-muted-foreground hover:bg-surface-2 hover:text-foreground',
                            )}
                        >
                            <Icon name={item.icon} size={18} />
                            {item.label}
                        </Link>
                    );
                })}
            </nav>

            {user && (
                <div className="flex items-center gap-3 border-t border-border p-4">
                    <Avatar name={user.name} />
                    <div className="min-w-0">
                        <p className="truncate font-body text-sm font-medium text-foreground">
                            {user.name}
                        </p>
                        <p className="truncate font-body text-xs text-faint capitalize">
                            {String(user.role ?? 'equipe')}
                        </p>
                    </div>
                </div>
            )}
        </>
    );
}

export function AdminSidebar() {
    return (
        <aside className="hidden w-60 shrink-0 flex-col border-r border-border bg-sidebar md:flex">
            <div className="flex h-16 items-center gap-2.5 border-b border-border px-5">
                <AdminBrand />
            </div>

            <AdminNavList />
        </aside>
    );
}

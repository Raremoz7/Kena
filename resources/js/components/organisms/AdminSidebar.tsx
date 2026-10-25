import { Link, usePage } from '@inertiajs/react';
import { Avatar } from '@/components/atoms/Avatar';
import { Icon } from '@/components/atoms/Icon';
import type { IconName } from '@/components/atoms/Icon';
import { KenaMark } from '@/components/atoms/KenaMark';
import { cn } from '@/lib/utils';
import { logout } from '@/routes/painel';

export interface AdminNavItem {
    label: string;
    /** Rótulo curto da barra inferior (mobile), onde cabe pouco texto. */
    short?: string;
    href: string;
    icon: IconName;
    /** Só organizador/admin — staff não vê. */
    organizerOnly?: boolean;
    /** Fica na barra inferior do mobile; o resto vai para "Mais". */
    primary?: boolean;
}

interface AdminNavGroup {
    label: string;
    items: AdminNavItem[];
}

/** Lista única da navegação do painel: sidebar, barra inferior e folha "Mais" saem daqui. */
const groups: AdminNavGroup[] = [
    {
        label: 'Vendas',
        items: [
            {
                label: 'Visão geral',
                short: 'Início',
                href: '/painel',
                icon: 'home',
                organizerOnly: true,
                primary: true,
            },
            {
                label: 'Eventos',
                href: '/painel/eventos',
                icon: 'ticket',
                organizerOnly: true,
                primary: true,
            },
            {
                label: 'Pedidos',
                href: '/painel/pedidos',
                icon: 'receipt',
                organizerOnly: true,
                primary: true,
            },
        ],
    },
    {
        label: 'Promoções',
        items: [
            {
                label: 'Cupons',
                href: '/painel/cupons',
                icon: 'tag',
                organizerOnly: true,
            },
            {
                label: 'Cortesias',
                href: '/painel/cortesias',
                icon: 'gift',
                organizerOnly: true,
            },
        ],
    },
    {
        label: 'Operação',
        items: [
            {
                label: 'Check-in',
                href: '/painel/checkin',
                icon: 'qr',
                primary: true,
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
                icon: 'users',
                organizerOnly: true,
            },
            {
                label: 'Configurações',
                href: '/painel/config',
                icon: 'settings',
                organizerOnly: true,
            },
        ],
    },
];

export function isNavActive(url: string, href: string): boolean {
    const path = url.split('?')[0];

    return path === href || (href !== '/painel' && path.startsWith(href));
}

/** Grupos e itens visíveis para o usuário logado (staff só vê o check-in). */
export function useAdminNav() {
    const { url, props } = usePage();
    const user = props.auth?.panelUser;
    const canOrganize = user?.role === 'organizer';
    const visible = groups
        .map((group) => ({
            ...group,
            items: group.items.filter(
                (item) => !item.organizerOnly || canOrganize,
            ),
        }))
        .filter((group) => group.items.length > 0);

    return { url, user, groups: visible };
}

/** Marca do painel — compartilhada pela sidebar e pela barra superior do mobile. */
export function AdminBrand() {
    return (
        <>
            <KenaMark className="size-7" />
            <span className="kicker text-faint">Painel</span>
        </>
    );
}

/** Cartão do usuário logado com o botão de sair. */
export function AdminUserCard({ onNavigate }: { onNavigate?: () => void }) {
    const { user } = useAdminNav();

    if (!user) {
        return null;
    }

    return (
        <div className="flex items-center gap-3">
            <Avatar name={user.name} />
            <div className="min-w-0 flex-1">
                <p className="truncate font-body text-sm font-medium text-foreground">
                    {user.name}
                </p>
                <p className="truncate font-body text-xs text-faint">
                    {user.role === 'organizer' ? 'Organizador' : 'Equipe'}
                </p>
            </div>
            <Link
                href={logout()}
                as="button"
                onClick={onNavigate}
                title="Sair do painel"
                aria-label="Sair do painel"
                className="shrink-0 rounded-btn p-2 text-faint transition-colors hover:bg-surface-2 hover:text-foreground"
            >
                <Icon name="logout" size={18} />
            </Link>
        </div>
    );
}

export function AdminSidebar() {
    const { url, groups: visible } = useAdminNav();

    return (
        <aside className="sticky top-0 hidden h-dvh w-60 shrink-0 flex-col border-r border-border bg-sidebar md:flex">
            <div className="flex h-16 items-center gap-2.5 border-b border-border px-5">
                <AdminBrand />
            </div>

            <nav
                aria-label="Painel"
                className="flex flex-1 flex-col gap-5 overflow-y-auto p-3"
            >
                {visible.map((group) => (
                    <div key={group.label}>
                        <p className="kicker px-3 pb-2 text-[10px] text-faint">
                            {group.label}
                        </p>
                        <ul className="flex flex-col gap-0.5">
                            {group.items.map((item) => {
                                const active = isNavActive(url, item.href);

                                return (
                                    <li key={item.href}>
                                        <Link
                                            href={item.href}
                                            aria-current={
                                                active ? 'page' : undefined
                                            }
                                            className={cn(
                                                'relative flex items-center gap-3 rounded-btn px-3 py-2 font-body text-sm transition-colors',
                                                active
                                                    ? 'bg-surface-2 font-medium text-foreground before:absolute before:inset-y-2 before:left-0 before:w-0.5 before:rounded-full before:bg-accent'
                                                    : 'text-muted-foreground hover:bg-surface-2 hover:text-foreground',
                                            )}
                                        >
                                            <Icon
                                                name={item.icon}
                                                size={18}
                                                className={
                                                    active
                                                        ? 'text-accent-text'
                                                        : undefined
                                                }
                                            />
                                            {item.label}
                                        </Link>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                ))}
            </nav>

            <div className="border-t border-border p-3">
                <AdminUserCard />
            </div>
        </aside>
    );
}

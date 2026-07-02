import { Link, usePage } from '@inertiajs/react';
import { Avatar } from '@/components/atoms/Avatar';
import { Icon  } from '@/components/atoms/Icon';
import type {IconName} from '@/components/atoms/Icon';
import { KenaMark } from '@/components/atoms/KenaMark';
import { cn } from '@/lib/utils';

interface NavItem {
    label: string;
    href?: string;
    icon: IconName;
    soon?: boolean;
}

const nav: NavItem[] = [
    { label: 'Visão geral', href: '/dashboard', icon: 'home' },
    { label: 'Eventos', href: '/dashboard/eventos', icon: 'ticket' },
    { label: 'Pedidos', href: '/dashboard/pedidos', icon: 'agenda' },
    { label: 'Cupons', href: '/dashboard/cupons', icon: 'tag' },
    { label: 'Locais', href: '/dashboard/locais', icon: 'map-pin' },
    { label: 'Check-in', href: '/dashboard/checkin', icon: 'qr' },
    { label: 'Configurações', href: '/dashboard/config', icon: 'shield' },
];

export function AdminSidebar() {
    const { url, props } = usePage();
    const user = props.auth?.user;

    return (
        <aside className="hidden w-60 shrink-0 flex-col border-r border-border bg-sidebar md:flex">
            <div className="flex h-16 items-center gap-2.5 border-b border-border px-5">
                <KenaMark className="size-7" />
                <span className="font-display text-base font-semibold tracking-[0.04em] text-foreground uppercase">
                    Kena
                </span>
                <span className="kicker ml-0.5 text-faint">Painel</span>
            </div>

            <nav className="flex flex-1 flex-col gap-1 p-3">
                {nav.map((item) => {
                    if (item.soon || !item.href) {
                        return (
                            <span
                                key={item.label}
                                className="flex items-center gap-3 rounded-btn px-3 py-2 font-body text-sm text-faint"
                            >
                                <Icon name={item.icon} size={18} />
                                {item.label}
                                <span className="kicker ml-auto text-[9px] text-faint">em breve</span>
                            </span>
                        );
                    }

                    const active =
                        url === item.href ||
                        (item.href !== '/dashboard' && url.startsWith(item.href));

                    return (
                        <Link
                            key={item.label}
                            href={item.href}
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
                        <p className="truncate font-body text-sm font-medium text-foreground">{user.name}</p>
                        <p className="truncate font-body text-xs text-faint capitalize">
                            {String(user.role ?? 'equipe')}
                        </p>
                    </div>
                </div>
            )}
        </aside>
    );
}

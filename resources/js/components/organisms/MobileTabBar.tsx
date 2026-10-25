import { Link, usePage } from '@inertiajs/react';
import { Icon } from '@/components/atoms/Icon';
import type { IconName } from '@/components/atoms/Icon';
import { cn } from '@/lib/utils';

const items: {
    label: string;
    href: string;
    icon: IconName;
    /** Outros caminhos que contam como esta aba (home, página do evento). */
    matches?: string[];
}[] = [
    {
        label: 'Eventos',
        href: '/eventos',
        icon: 'sparkle',
        matches: ['/', '/e/'],
    },
    { label: 'Ingressos', href: '/meus-ingressos', icon: 'ticket' },
    {
        label: 'Perfil',
        href: '/settings/profile',
        icon: 'user',
        matches: ['/settings'],
    },
];

function isActive(url: string, href: string, matches: string[] = []) {
    const path = url.split('?')[0];

    return (
        path.startsWith(href) ||
        matches.some((m) => (m === '/' ? path === '/' : path.startsWith(m)))
    );
}

export function MobileTabBar() {
    const { url } = usePage();

    return (
        <nav
            aria-label="Navegação inferior"
            className="fixed inset-x-0 bottom-0 z-40 border-t border-border bg-bg/95 pb-[env(safe-area-inset-bottom)] backdrop-blur-md md:hidden"
        >
            <div className="mx-auto flex max-w-md items-stretch justify-around px-2 py-1.5">
                {items.map((it) => {
                    const active = isActive(url, it.href, it.matches);

                    return (
                        <Link
                            key={it.label}
                            href={it.href}
                            aria-current={active ? 'page' : undefined}
                            className={cn(
                                'flex flex-1 flex-col items-center gap-1 rounded-btn py-1.5 font-body text-[11px] font-medium transition-colors',
                                active
                                    ? 'text-accent-text'
                                    : 'text-muted-foreground',
                            )}
                        >
                            <Icon name={it.icon} size={20} />
                            {it.label}
                        </Link>
                    );
                })}
            </div>
        </nav>
    );
}

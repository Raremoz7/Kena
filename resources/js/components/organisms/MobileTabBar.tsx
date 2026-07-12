import { Link, usePage } from '@inertiajs/react';
import { Icon  } from '@/components/atoms/Icon';
import type {IconName} from '@/components/atoms/Icon';
import { cn } from '@/lib/utils';

const items: { label: string; href: string; icon: IconName }[] = [
    { label: 'Eventos', href: '/eventos', icon: 'sparkle' },
    { label: 'Agenda', href: '/agenda', icon: 'agenda' },
    { label: 'Ingressos', href: '/meus-ingressos', icon: 'ticket' },
    { label: 'Perfil', href: '/settings/profile', icon: 'user' },
];

export function MobileTabBar() {
    const { url } = usePage();

    return (
        <nav className="fixed inset-x-0 bottom-0 z-40 border-t border-border bg-bg/95 backdrop-blur-md md:hidden">
            <div className="mx-auto flex max-w-md items-stretch justify-around px-2 py-1.5">
                {items.map((it) => {
                    const active = url === it.href || url.startsWith(it.href);

                    return (
                        <Link
                            key={it.label}
                            href={it.href}
                            className={cn(
                                'flex flex-1 flex-col items-center gap-1 rounded-btn py-1.5 font-body text-[11px] font-medium transition-colors',
                                active ? 'text-accent-text' : 'text-muted-foreground',
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

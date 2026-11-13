import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';

const TABS = [
    { key: 'tickets', label: 'Ingressos', href: '/meus-ingressos' },
    { key: 'photos', label: 'Fotos', href: '/minhas-fotos' },
] as const;

/** Abas da área do comprador: ingressos e fotos compradas. */
export function BuyerAreaTabs({ current }: { current: 'tickets' | 'photos' }) {
    return (
        <nav aria-label="Minha área" className="mt-6 border-b border-border">
            <ul className="-mb-px flex gap-6">
                {TABS.map((tab) => {
                    const active = tab.key === current;

                    return (
                        <li key={tab.key}>
                            <Link
                                href={tab.href}
                                aria-current={active ? 'page' : undefined}
                                className={cn(
                                    'inline-flex border-b-2 pb-3 font-body text-sm font-semibold transition-colors',
                                    active
                                        ? 'border-accent text-foreground'
                                        : 'border-transparent text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {tab.label}
                            </Link>
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}

import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';

const navItems = [
    { title: 'Perfil', href: editProfile() },
    { title: 'Segurança', href: editSecurity() },
];

/**
 * Configurações do comprador. Roda dentro do BuyerLayout (header, rodapé e tab
 * bar já vêm de lá), então aqui só entram o título e a sub-navegação.
 */
export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <div className="mx-auto w-full max-w-3xl px-4 py-10 sm:px-6">
            <h1 className="font-display text-display-lg text-foreground uppercase">Configurações</h1>
            <p className="mt-1 font-body text-sm text-muted-foreground">
                Seus dados de acesso e segurança da conta.
            </p>

            <nav
                aria-label="Configurações"
                className="mt-6 flex w-fit items-center gap-1 rounded-full border border-border bg-surface-2 p-1"
            >
                {navItems.map((item) => {
                    const active = isCurrentOrParentUrl(item.href);

                    return (
                        <Link
                            key={toUrl(item.href)}
                            href={item.href}
                            aria-current={active ? 'page' : undefined}
                            className={cn(
                                'rounded-full px-4 py-1.5 font-body text-sm transition-colors',
                                active
                                    ? 'bg-accent font-semibold text-accent-fg'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {item.title}
                        </Link>
                    );
                })}
            </nav>

            <section className="mt-10 max-w-xl space-y-12">{children}</section>
        </div>
    );
}

import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Button } from '@/components/atoms/Button';
import { ProfileMenu } from '@/components/molecules/ProfileMenu';
import { cn } from '@/lib/utils';

const MARK = {
    maskImage: 'url(/kena-mark.svg)',
    WebkitMaskImage: 'url(/kena-mark.svg)',
    maskRepeat: 'no-repeat',
    WebkitMaskRepeat: 'no-repeat',
    maskPosition: 'center',
    WebkitMaskPosition: 'center',
    maskSize: 'contain',
    WebkitMaskSize: 'contain',
} as const;

function PillLink({
    href,
    matches = [],
    children,
}: {
    href: string;
    /** Outros caminhos que contam como este link (home, página do evento). */
    matches?: string[];
    children: ReactNode;
}) {
    const { url } = usePage();
    const path = url.split('?')[0];
    const active =
        path.startsWith(href) ||
        matches.some((m) => (m === '/' ? path === '/' : path.startsWith(m)));

    return (
        <Link
            href={href}
            aria-current={active ? 'page' : undefined}
            className={cn(
                // min-h-8 garante o alvo mínimo de 24px da WCAG 2.5.8 com folga.
                'inline-flex min-h-8 items-center rounded-full px-3 py-1.5 font-body text-sm whitespace-nowrap transition-colors sm:px-4',
                active
                    ? 'bg-accent font-semibold text-accent-fg'
                    : 'text-muted-foreground hover:text-foreground',
            )}
        >
            {children}
        </Link>
    );
}

export function Header() {
    const { auth } = usePage().props;
    const user = auth?.user;

    return (
        <header className="sticky top-0 z-40 border-b border-border bg-bg/85 pt-[env(safe-area-inset-top)] backdrop-blur-md">
            <div className="relative mx-auto flex h-16 max-w-6xl items-center justify-between gap-2 px-4 sm:px-6">
                <Link
                    href="/"
                    className="flex shrink-0 items-center"
                    aria-label="Kena — início"
                >
                    <span
                        aria-hidden="true"
                        className="size-9 bg-accent"
                        style={MARK}
                    />
                </Link>

                {/* nav em pill: centralizada no desktop, entre o logo e a conta no mobile */}
                <nav
                    aria-label="Principal"
                    className="flex items-center gap-0.5 rounded-full border border-border bg-surface-2 p-1 md:absolute md:left-1/2 md:-translate-x-1/2 md:gap-1"
                >
                    <PillLink href="/eventos" matches={['/', '/e/']}>
                        Eventos
                    </PillLink>
                    <PillLink href="/meus-ingressos">
                        <span className="sm:hidden">Ingressos</span>
                        <span className="hidden sm:inline">Meus ingressos</span>
                    </PillLink>
                </nav>

                <div className="flex shrink-0 items-center gap-3">
                    {user ? (
                        <ProfileMenu name={user.name} />
                    ) : (
                        <Button asChild size="sm">
                            <Link href="/login">Entrar</Link>
                        </Button>
                    )}
                </div>
            </div>
        </header>
    );
}

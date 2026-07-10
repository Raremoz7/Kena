import { Link, usePage } from '@inertiajs/react';
import { Avatar } from '@/components/atoms/Avatar';
import { Button } from '@/components/atoms/Button';
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

function PillLink({ href, children }: { href: string; children: string }) {
    const { url } = usePage();
    const active =
        url === href ||
        (href !== '/' && url.startsWith(href)) ||
        (href === '/eventos' && url === '/');

    return (
        <Link
            href={href}
            className={cn(
                // min-h-8 garante o alvo mínimo de 24px da WCAG 2.5.8 com folga.
                'inline-flex min-h-8 items-center rounded-full px-4 py-1.5 font-body text-sm transition-colors',
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
        <header className="sticky top-0 z-40 border-b border-border bg-bg/85 backdrop-blur-md">
            <div className="relative mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6">
                <Link href="/" className="flex items-center" aria-label="Kena — início">
                    <span aria-hidden="true" className="size-9 bg-accent" style={MARK} />
                </Link>

                {/* nav central em pill (desktop) — no mobile o tab bar resolve */}
                <nav className="absolute left-1/2 hidden -translate-x-1/2 items-center gap-1 rounded-full border border-border bg-surface-2 p-1 md:flex">
                    <PillLink href="/eventos">Eventos</PillLink>
                    <PillLink href="/meus-ingressos">Meus ingressos</PillLink>
                </nav>

                <div className="flex items-center gap-3">
                    {user ? (
                        <Link href="/meus-ingressos" className="flex items-center gap-2.5">
                            <span className="hidden font-body text-sm text-muted-foreground sm:block">
                                {user.name.split(' ')[0]}
                            </span>
                            <Avatar name={user.name} />
                        </Link>
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

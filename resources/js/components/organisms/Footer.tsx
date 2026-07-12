import { Link } from '@inertiajs/react';

const footerLink =
    'inline-flex min-h-6 items-center py-1.5 -my-1.5 font-body text-xs text-muted-foreground transition-colors hover:text-foreground';

export function Footer() {
    return (
        <footer className="hidden border-t border-border md:block">
            <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-7">
                <p className="font-body text-xs text-faint">
                    Kena · Entre em cena · {new Date().getFullYear()}
                </p>
                {/*
                 * O padding vertical (com margem negativa compensando) leva a área
                 * clicável ao mínimo de 24px da WCAG 2.5.8 sem alterar o layout.
                 */}
                <nav className="flex gap-6">
                    <Link href="/eventos" className={footerLink}>
                        Eventos
                    </Link>
                    <a href="#" className={footerLink}>
                        Ajuda
                    </a>
                    <a href="#" className={footerLink}>
                        Termos
                    </a>
                </nav>
            </div>
        </footer>
    );
}

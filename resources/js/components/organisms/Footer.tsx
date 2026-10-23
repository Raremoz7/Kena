import { Link } from '@inertiajs/react';

const footerLink =
    'inline-flex min-h-6 items-center py-1.5 -my-1.5 font-body text-xs text-muted-foreground transition-colors hover:text-foreground';

export function Footer() {
    return (
        // No mobile o rodapé também aparece (Termos/Privacidade precisam estar
        // acessíveis), com folga embaixo para a tab bar fixa.
        <footer className="border-t border-border pb-20 md:pb-0">
            <div className="mx-auto flex max-w-6xl flex-col gap-3 px-6 py-7 md:flex-row md:items-center md:justify-between">
                <p className="font-body text-xs text-faint">
                    Kena · Entre em cena · {new Date().getFullYear()}
                </p>
                {/*
                 * O padding vertical (com margem negativa compensando) leva a área
                 * clicável ao mínimo de 24px da WCAG 2.5.8 sem alterar o layout.
                 */}
                <nav aria-label="Rodapé" className="flex gap-6">
                    <Link href="/eventos" className={footerLink}>
                        Eventos
                    </Link>
                    <Link href="/ajuda" className={footerLink}>
                        Ajuda
                    </Link>
                    <Link href="/termos" className={footerLink}>
                        Termos
                    </Link>
                    <Link href="/privacidade" className={footerLink}>
                        Privacidade
                    </Link>
                </nav>
            </div>
        </footer>
    );
}

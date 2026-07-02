import { Link } from '@inertiajs/react';

export function Footer() {
    return (
        <footer className="hidden border-t border-border md:block">
            <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-7">
                <p className="font-body text-xs text-faint">
                    Kena · Entre em cena · {new Date().getFullYear()}
                </p>
                <nav className="flex gap-6">
                    <Link href="/eventos" className="font-body text-xs text-muted-foreground hover:text-foreground">
                        Eventos
                    </Link>
                    <a href="#" className="font-body text-xs text-muted-foreground hover:text-foreground">
                        Ajuda
                    </a>
                    <a href="#" className="font-body text-xs text-muted-foreground hover:text-foreground">
                        Termos
                    </a>
                </nav>
            </div>
        </footer>
    );
}

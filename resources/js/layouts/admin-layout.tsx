import { useState } from 'react';
import type { ReactNode } from 'react';
import { Icon } from '@/components/atoms/Icon';
import { Drawer } from '@/components/molecules/Drawer';
import {
    AdminBrand,
    AdminNavList,
    AdminSidebar,
} from '@/components/organisms/AdminSidebar';
import { useFlashToasts } from '@/lib/veludo/use-flash-toasts';

/**
 * Shell do painel admin: sidebar fixa (desktop) + barra compacta no mobile, onde
 * a navegação vive numa gaveta — a sidebar some abaixo de md.
 */
export default function AdminLayout({ children }: { children: ReactNode }) {
    useFlashToasts();
    const [menuOpen, setMenuOpen] = useState(false);

    return (
        <div className="flex min-h-dvh bg-bg">
            <AdminSidebar />
            <div className="flex min-w-0 flex-1 flex-col">
                <header className="flex h-14 items-center gap-2.5 border-b border-border px-4 md:hidden">
                    <button
                        type="button"
                        onClick={() => setMenuOpen(true)}
                        aria-label="Abrir menu"
                        aria-expanded={menuOpen}
                        className="-ml-1.5 rounded-btn p-1.5 text-muted-foreground transition-colors hover:bg-surface-2 hover:text-foreground"
                    >
                        <Icon name="menu" size={20} />
                    </button>
                    <AdminBrand />
                </header>

                <Drawer
                    open={menuOpen}
                    onOpenChange={setMenuOpen}
                    title="Kena · Painel"
                    description="Navegação do painel"
                >
                    <div className="flex h-full flex-col">
                        <AdminNavList onNavigate={() => setMenuOpen(false)} />
                    </div>
                </Drawer>

                <main className="min-w-0 flex-1">{children}</main>
            </div>
        </div>
    );
}

import type { ReactNode } from 'react';
import { KenaMark } from '@/components/atoms/KenaMark';
import { AdminSidebar } from '@/components/organisms/AdminSidebar';

/**
 * Shell do painel admin: sidebar fixa (desktop) + barra compacta no mobile.
 */
export default function AdminLayout({ children }: { children: ReactNode }) {
    return (
        <div className="flex min-h-dvh bg-bg">
            <AdminSidebar />
            <div className="flex min-w-0 flex-1 flex-col">
                <header className="flex h-14 items-center gap-2.5 border-b border-border px-4 md:hidden">
                    <KenaMark className="size-7" />
                    <span className="font-display text-base font-semibold tracking-[0.04em] text-foreground uppercase">
                        Kena
                    </span>
                    <span className="kicker ml-0.5 text-faint">Painel</span>
                </header>
                <main className="min-w-0 flex-1">{children}</main>
            </div>
        </div>
    );
}

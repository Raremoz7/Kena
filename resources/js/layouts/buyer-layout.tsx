import type { ReactNode } from 'react';
import { Footer } from '@/components/organisms/Footer';
import { Header } from '@/components/organisms/Header';
import { MobileTabBar } from '@/components/organisms/MobileTabBar';
import { useFlashToasts } from '@/lib/veludo/use-flash-toasts';

/**
 * Shell das telas do comprador: header fixo, conteúdo, footer (desktop) e
 * tab bar (mobile).
 */
export default function BuyerLayout({ children }: { children: ReactNode }) {
    useFlashToasts();

    return (
        <div className="flex min-h-dvh flex-col bg-bg">
            <Header />
            <main className="flex-1 pb-24 md:pb-0">{children}</main>
            <Footer />
            <MobileTabBar />
        </div>
    );
}

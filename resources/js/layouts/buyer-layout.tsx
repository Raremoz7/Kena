import type { ReactNode } from 'react';
import { SkipLink } from '@/components/atoms/SkipLink';
import { Footer } from '@/components/organisms/Footer';
import { Header } from '@/components/organisms/Header';
import { MobileTabBar } from '@/components/organisms/MobileTabBar';
import { useFlashToasts } from '@/lib/veludo/use-flash-toasts';

/**
 * Shell das telas do comprador: header fixo, conteúdo, footer e tab bar
 * (mobile). A folga para a tab bar fica no próprio footer.
 */
export default function BuyerLayout({ children }: { children: ReactNode }) {
    useFlashToasts();

    return (
        <div className="flex min-h-dvh flex-col bg-bg">
            <SkipLink />
            <Header />
            <main id="conteudo" tabIndex={-1} className="flex-1 outline-none">
                {children}
            </main>
            <Footer />
            <MobileTabBar />
        </div>
    );
}

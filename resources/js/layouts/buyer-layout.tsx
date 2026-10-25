import type { ReactNode } from 'react';
import { SkipLink } from '@/components/atoms/SkipLink';
import { ActiveReservationBanner } from '@/components/organisms/ActiveReservationBanner';
import { Footer } from '@/components/organisms/Footer';
import { Header } from '@/components/organisms/Header';
import { useFlashToasts } from '@/lib/veludo/use-flash-toasts';

/**
 * Shell das telas do comprador: header fixo (com a navegação, inclusive no
 * mobile), aviso de reserva em andamento, conteúdo e footer.
 */
export default function BuyerLayout({ children }: { children: ReactNode }) {
    useFlashToasts();

    return (
        <div className="flex min-h-dvh flex-col bg-bg">
            <SkipLink />
            <Header />
            <ActiveReservationBanner />
            <main id="conteudo" tabIndex={-1} className="flex-1 outline-none">
                {children}
            </main>
            <Footer />
        </div>
    );
}

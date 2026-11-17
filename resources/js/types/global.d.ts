import type { ActiveReservation } from '@/components/organisms/ActiveReservationBanner';
import type { Auth, Impersonation } from '@/types/auth';

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            activeReservation?: ActiveReservation | null;
            /** Existe galeria de fotos publicada: só aí "Fotos" entra no menu. */
            photosAvailable?: boolean;
            /** Super admin "dentro" do painel de um organizador. */
            impersonation?: Impersonation | null;
            [key: string]: unknown;
        };
    }
}

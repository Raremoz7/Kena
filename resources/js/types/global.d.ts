import type { ActiveReservation } from '@/components/organisms/ActiveReservationBanner';
import type { Auth, Impersonation, OrganizationAlert } from '@/types/auth';

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
            /** Quem opera o Kena (Decreto 7.962/2013): vai no rodapé. */
            company?: {
                name: string;
                document: string | null;
                address: string | null;
                email: string | null;
            };
            activeReservation?: ActiveReservation | null;
            /** Existe galeria de fotos publicada: só aí "Fotos" entra no menu. */
            photosAvailable?: boolean;
            /** Super admin "dentro" do painel de um organizador. */
            impersonation?: Impersonation | null;
            /** Por que a organização não está vendendo. */
            organizationAlert?: OrganizationAlert | null;
            [key: string]: unknown;
        };
    }
}

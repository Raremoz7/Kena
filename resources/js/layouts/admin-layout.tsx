import { Head, router, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { SkipLink } from '@/components/atoms/SkipLink';
import { AdminBottomNav } from '@/components/organisms/AdminBottomNav';
import { AdminBrand, AdminSidebar } from '@/components/organisms/AdminSidebar';
import { useFlashToasts } from '@/lib/veludo/use-flash-toasts';

/**
 * Shell do painel admin: sidebar fixa no desktop; no mobile vira app, com barra
 * superior compacta e navegação inferior (a sidebar some abaixo de md).
 */
/** Aviso fixo quando o super admin está "dentro" do painel de um organizador. */
function ImpersonationBanner() {
    const impersonation = usePage().props.impersonation;

    if (!impersonation) {
        return null;
    }

    return (
        <div
            role="status"
            className="sticky top-0 z-40 flex flex-wrap items-center justify-between gap-2 bg-warning px-4 py-2 font-body text-sm text-warning-fg"
        >
            <span>
                Você ({impersonation.adminName}) está no painel de{' '}
                <strong>{impersonation.organizationName}</strong> como
                organizador. Tudo o que fizer aqui vale de verdade.
            </span>
            <button
                type="button"
                onClick={() => router.post(impersonation.stopUrl)}
                className="rounded-btn border border-current px-3 py-1 text-xs font-medium"
            >
                Voltar ao admin
            </button>
        </div>
    );
}

export default function AdminLayout({ children }: { children: ReactNode }) {
    useFlashToasts();

    return (
        <div className="flex min-h-dvh bg-bg">
            <Head>
                <link rel="manifest" href="/painel.webmanifest" />
                <meta name="apple-mobile-web-app-capable" content="yes" />
                <meta name="mobile-web-app-capable" content="yes" />
                <meta name="apple-mobile-web-app-title" content="Kena Painel" />
            </Head>
            <SkipLink />
            <AdminSidebar />
            <div className="flex min-w-0 flex-1 flex-col">
                <ImpersonationBanner />
                <header className="sticky top-0 z-30 flex h-14 items-center gap-2.5 border-b border-border bg-bg/90 px-4 pt-[env(safe-area-inset-top)] backdrop-blur-xl md:hidden">
                    <AdminBrand />
                </header>

                <main
                    id="conteudo"
                    tabIndex={-1}
                    className="min-w-0 flex-1 pb-[calc(5rem+env(safe-area-inset-bottom))] outline-none md:pb-0"
                >
                    {children}
                </main>
            </div>
            <AdminBottomNav />
        </div>
    );
}

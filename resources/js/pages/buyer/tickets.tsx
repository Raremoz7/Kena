import { Head, Link } from '@inertiajs/react';
import { Icon } from '@/components/atoms/Icon';
import { BuyerTicketList } from '@/components/organisms/BuyerTicketList';
import type { TicketInfo } from '@/lib/veludo/types';

interface TicketsPageProps {
    tickets: TicketInfo[];
    needsPassword?: boolean;
    googleWalletEnabled?: boolean;
}

export default function TicketsPage({
    tickets,
    needsPassword = false,
    googleWalletEnabled = false,
}: TicketsPageProps) {
    return (
        <>
            <Head title="Meus ingressos" />
            <div className="mx-auto max-w-4xl px-4 py-10 sm:px-6 sm:py-12">
                <h1 className="font-display text-display-lg text-foreground uppercase">Meus ingressos</h1>
                <p className="mt-1 font-body text-sm text-muted-foreground">
                    Seus lugares, o QR de entrada e as transferências de titularidade.
                </p>

                {needsPassword && (
                    <Link
                        href="/definir-senha"
                        className="mt-6 flex items-center justify-between gap-3 rounded-card border border-accent/40 bg-surface px-4 py-3 transition-colors hover:bg-surface-2"
                    >
                        <span className="flex items-center gap-2.5">
                            <Icon name="lock" size={18} className="text-accent" />
                            <span className="font-body text-sm text-foreground">
                                Crie uma senha para entrar sem depender do link por e-mail.
                            </span>
                        </span>
                        <Icon name="chevron-right" size={16} className="shrink-0 text-faint" />
                    </Link>
                )}

                <div className="mt-8">
                    <BuyerTicketList tickets={tickets} walletEnabled={googleWalletEnabled} />
                </div>
            </div>
        </>
    );
}

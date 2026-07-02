import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/atoms/Button';
import { Icon } from '@/components/atoms/Icon';
import { Input } from '@/components/atoms/Input';
import { QrCode } from '@/components/atoms/QrCode';
import { Spinner } from '@/components/atoms/Spinner';
import { EmptyState } from '@/components/molecules/EmptyState';
import { FormField } from '@/components/molecules/FormField';
import { Modal } from '@/components/molecules/Modal';
import { TicketStub } from '@/components/molecules/TicketStub';
import { api, ApiError } from '@/lib/veludo/api';
import { veludoToast } from '@/lib/veludo/toast';
import type { TicketInfo } from '@/lib/veludo/types';

export function BuyerTicketList({
    tickets,
    walletEnabled = false,
}: {
    tickets: TicketInfo[];
    walletEnabled?: boolean;
}) {
    const [qrTicket, setQrTicket] = useState<TicketInfo | null>(null);
    const [transferTicket, setTransferTicket] = useState<TicketInfo | null>(
        null,
    );
    const [email, setEmail] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [refundingId, setRefundingId] = useState<number | null>(null);
    const [refundTicket, setRefundTicket] = useState<TicketInfo | null>(null);

    async function requestRefund(t: TicketInfo) {
        if (refundingId !== null) {
            return;
        }

        setRefundingId(t.id);

        try {
            const res = await api.post<{ message: string }>(t.refundUrl);
            veludoToast.success('Reembolso solicitado', res.message);
            setRefundTicket(null);
            router.reload({ only: ['tickets'] });
        } catch (err) {
            const message =
                err instanceof ApiError
                    ? err.message
                    : 'Não foi possível reembolsar.';
            veludoToast.error('Reembolso não concluído', message);
        } finally {
            setRefundingId(null);
        }
    }

    if (!tickets.length) {
        return (
            <EmptyState
                title="Nenhum ingresso ainda"
                description="Quando você comprar, seus ingressos aparecem aqui com QR e opção de transferência."
                action={
                    <Button asChild>
                        <Link href="/eventos">Explorar eventos</Link>
                    </Button>
                }
            />
        );
    }

    async function submitTransfer(e: FormEvent) {
        e.preventDefault();

        if (!transferTicket || submitting) {
            return;
        }

        setSubmitting(true);

        try {
            const res = await api.post<{ message: string }>(
                transferTicket.transferUrl,
                { email },
            );
            veludoToast.success('Ingresso transferido', res.message);
            setTransferTicket(null);
            setEmail('');
            router.reload({ only: ['tickets'] });
        } catch (err) {
            const message =
                err instanceof ApiError
                    ? err.message
                    : 'Não foi possível transferir.';
            veludoToast.error('Transferência não concluída', message);
        } finally {
            setSubmitting(false);
        }
    }

    return (
        <>
            <div className="grid gap-4 sm:grid-cols-2">
                {tickets.map((t) => (
                    <TicketStub
                        key={t.id}
                        ticket={t}
                        actions={
                            <>
                                <Button
                                    size="sm"
                                    variant="secondary"
                                    onClick={() => setQrTicket(t)}
                                >
                                    <Icon name="eye" size={15} /> Ver QR
                                </Button>
                                {t.status === 'valid' && (
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        onClick={() => setTransferTicket(t)}
                                    >
                                        <Icon name="transfer" size={15} />{' '}
                                        Transferir
                                    </Button>
                                )}
                                {t.canRefund && (
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        disabled={refundingId === t.id}
                                        onClick={() => setRefundTicket(t)}
                                    >
                                        <Icon name="refund" size={15} />{' '}
                                        Reembolsar
                                    </Button>
                                )}
                            </>
                        }
                    />
                ))}
            </div>

            <Modal
                open={!!qrTicket}
                onOpenChange={(o) => !o && setQrTicket(null)}
                title="Seu ingresso"
                description={
                    qrTicket
                        ? `${qrTicket.sectorName} · ${qrTicket.seatLabel}`
                        : undefined
                }
            >
                {qrTicket && (
                    <div className="flex flex-col items-center gap-4">
                        <QrCode value={qrTicket.qrToken} size={196} frame />
                        <p className="font-mono text-xs text-faint">
                            {qrTicket.code}
                        </p>
                        <p className="text-center font-body text-sm text-muted-foreground">
                            Apresente este QR na entrada. Ele é reemitido se
                            você transferir o ingresso.
                        </p>
                        <div className="flex flex-col items-center gap-2">
                            <a
                                href={qrTicket.calendarUrl}
                                className="inline-flex items-center gap-1.5 font-body text-sm text-accent hover:underline"
                            >
                                <Icon name="calendar" size={15} /> Adicionar à
                                agenda
                            </a>
                            {walletEnabled && (
                                <a
                                    href={qrTicket.googleWalletUrl}
                                    className="inline-flex items-center gap-1.5 font-body text-sm text-accent hover:underline"
                                >
                                    <Icon name="ticket" size={15} /> Adicionar
                                    ao Google Wallet
                                </a>
                            )}
                        </div>
                    </div>
                )}
            </Modal>

            <Modal
                open={!!transferTicket}
                onOpenChange={(o) => !o && setTransferTicket(null)}
                title="Transferir ingresso"
                description={
                    transferTicket
                        ? `${transferTicket.sectorName} · ${transferTicket.seatLabel}`
                        : undefined
                }
            >
                {transferTicket && (
                    <form
                        onSubmit={submitTransfer}
                        className="flex flex-col gap-4"
                    >
                        <p className="flex items-start gap-1.5 rounded-btn border border-border bg-bg px-3 py-2 font-body text-xs text-muted-foreground">
                            <Icon
                                name="clock"
                                size={14}
                                className="mt-px text-warning"
                            />
                            Transferência liberada até 24h antes do início da
                            sessão.
                        </p>
                        <FormField
                            label="E-mail do destinatário"
                            htmlFor="transfer-email"
                            helper="Se ele não tiver conta, criamos uma e enviamos o acesso por e-mail."
                        >
                            <Input
                                id="transfer-email"
                                type="email"
                                required
                                value={email}
                                onChange={(e) => setEmail(e.target.value)}
                                placeholder="nome@email.com"
                            />
                        </FormField>
                        <div className="flex justify-end gap-3">
                            <Button
                                type="button"
                                variant="secondary"
                                onClick={() => setTransferTicket(null)}
                                disabled={submitting}
                            >
                                Cancelar
                            </Button>
                            <Button type="submit" disabled={submitting}>
                                {submitting ? <Spinner /> : 'Transferir'}
                            </Button>
                        </div>
                    </form>
                )}
            </Modal>

            <Modal
                open={!!refundTicket}
                onOpenChange={(o) => !o && setRefundTicket(null)}
                title="Reembolsar ingresso?"
                description={
                    refundTicket
                        ? `${refundTicket.sectorName} · ${refundTicket.seatLabel}`
                        : undefined
                }
            >
                {refundTicket && (
                    <div className="flex flex-col gap-4">
                        <p className="flex items-start gap-1.5 rounded-btn border border-border bg-bg px-3 py-2 font-body text-xs text-muted-foreground">
                            <Icon
                                name="clock"
                                size={14}
                                className="mt-px text-warning"
                            />
                            Os ingressos deste pedido serão cancelados.
                        </p>
                        <div className="flex justify-end gap-3">
                            <Button
                                type="button"
                                variant="secondary"
                                onClick={() => setRefundTicket(null)}
                                disabled={refundingId === refundTicket.id}
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="button"
                                variant="danger"
                                disabled={refundingId === refundTicket.id}
                                onClick={() => requestRefund(refundTicket)}
                            >
                                {refundingId === refundTicket.id ? (
                                    <Spinner />
                                ) : (
                                    'Reembolsar'
                                )}
                            </Button>
                        </div>
                    </div>
                )}
            </Modal>
        </>
    );
}

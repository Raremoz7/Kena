import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { Countdown } from '@/components/atoms/Countdown';
import { Icon } from '@/components/atoms/Icon';
import { CouponInput } from '@/components/molecules/CouponInput';
import type { CouponFeedback } from '@/components/molecules/CouponInput';
import { CheckoutPanel } from '@/components/organisms/CheckoutPanel';
import { PaymentBrick } from '@/components/organisms/PaymentBrick';
import type {
    CardFields,
    PaymentMethod,
    PixData,
} from '@/components/organisms/PaymentBrick';
import { api, ApiError } from '@/lib/veludo/api';
import { createMercadoPago } from '@/lib/veludo/mercadopago';
import { veludoToast } from '@/lib/veludo/toast';
import type { PriceLine, ReservationInfo } from '@/lib/veludo/types';

interface PriceSummary {
    lines: PriceLine[];
    total: number;
}

interface PayResponse {
    status: 'pending' | 'paid' | 'failed' | 'none';
    orderReference?: string;
    redirect: string | null;
    pix: (PixData & { expiresAt?: string | null }) | null;
}

interface CheckoutPageProps {
    reservation: ReservationInfo;
    priceSummary: PriceSummary;
    couponUrl: string;
    payUrl: string;
    statusUrl: string;
    mpPublicKey: string | null;
}

const brl = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
});

export default function CheckoutPage({
    reservation,
    priceSummary,
    couponUrl,
    payUrl,
    statusUrl,
    mpPublicKey,
}: CheckoutPageProps) {
    const [summary, setSummary] = useState<PriceSummary>(priceSummary);
    const [appliedCoupon, setAppliedCoupon] = useState<string | null>(null);
    const [couponBusy, setCouponBusy] = useState(false);
    const [couponFeedback, setCouponFeedback] = useState<CouponFeedback | null>(
        null,
    );

    const [method, setMethod] = useState<PaymentMethod>('card');
    const [card, setCard] = useState<CardFields>({
        number: '',
        exp: '',
        cvv: '',
        name: '',
        installments: 1,
    });
    const [pix, setPix] = useState<PixData | null>(null);
    const [submitting, setSubmitting] = useState(false);
    const [awaitingPix, setAwaitingPix] = useState(false);

    const installmentOptions = useMemo(() => {
        return [1, 2, 3].map((n) => ({
            value: n,
            label: `${n}× de ${brl.format(summary.total / n)} sem juros`,
        }));
    }, [summary.total]);

    function onExpire() {
        veludoToast.warning('Reserva expirada', 'Os assentos foram liberados.');
        router.visit('/eventos');
    }

    async function applyCoupon(code: string) {
        setCouponBusy(true);

        try {
            const res = await api.post<{
                priceSummary: PriceSummary;
                coupon: string;
            }>(couponUrl, { code });
            setSummary(res.priceSummary);
            setAppliedCoupon(res.coupon);
            setCouponFeedback({
                tone: 'success',
                message: `Cupom ${res.coupon} aplicado.`,
            });
        } catch (e) {
            const message =
                e instanceof ApiError
                    ? e.message
                    : 'Não foi possível aplicar o cupom.';
            setCouponFeedback({ tone: 'danger', message });
        } finally {
            setCouponBusy(false);
        }
    }

    function handlePay(res: PayResponse) {
        if (res.status === 'paid' && res.redirect) {
            veludoToast.success(
                'Pagamento aprovado',
                'Seus ingressos foram emitidos. Bom espetáculo!',
            );
            router.visit(res.redirect);

            return;
        }

        if (res.status === 'failed') {
            veludoToast.error(
                'Pagamento recusado',
                'O pagamento não foi aprovado. Tente outro meio.',
            );
            setSubmitting(false);

            return;
        }

        if (res.pix) {
            setPix({
                qrBase64: res.pix.qrBase64,
                copyPaste: res.pix.copyPaste,
            });
            setAwaitingPix(true);
            veludoToast.info(
                'Pix gerado',
                'Escaneie o QR. Confirmamos automaticamente quando cair.',
            );
        }

        setSubmitting(false);
    }

    async function confirm() {
        if (submitting || awaitingPix) {
            return;
        }

        setSubmitting(true);

        try {
            if (method === 'pix') {
                const res = await api.post<PayResponse>(payUrl, {
                    method: 'pix',
                    coupon_code: appliedCoupon ?? undefined,
                });
                handlePay(res);

                return;
            }

            if (!mpPublicKey) {
                veludoToast.warning(
                    'Pagamento indisponível',
                    'A integração do Mercado Pago ainda não foi configurada.',
                );
                setSubmitting(false);

                return;
            }

            const mp = await createMercadoPago(mpPublicKey);
            const [mm, yy] = card.exp.split('/').map((s) => s.trim());
            const token = await mp.createCardToken({
                cardNumber: card.number.replace(/\s/g, ''),
                cardholderName: card.name,
                cardExpirationMonth: mm ?? '',
                cardExpirationYear: yy ? `20${yy}` : '',
                securityCode: card.cvv,
            });
            const bin = card.number.replace(/\D/g, '').slice(0, 6);
            const methods = await mp.getPaymentMethods({ bin });

            const res = await api.post<PayResponse>(payUrl, {
                method: 'card',
                token: token.id,
                payment_method_id: methods.results[0]?.id,
                installments: card.installments,
                coupon_code: appliedCoupon ?? undefined,
            });
            handlePay(res);
        } catch (e) {
            const message =
                e instanceof ApiError
                    ? e.message
                    : 'Não foi possível concluir o pagamento.';
            veludoToast.error('Pagamento não concluído', message);
            setSubmitting(false);
        }
    }

    // Polling do Pix até confirmar.
    useEffect(() => {
        if (!awaitingPix) {
            return;
        }

        let active = true;
        const id = window.setInterval(async () => {
            try {
                const res = await api.get<PayResponse>(statusUrl);

                if (!active) {
                    return;
                }

                if (res.status === 'paid' && res.redirect) {
                    veludoToast.success(
                        'Pix confirmado',
                        'Seus ingressos foram emitidos. Bom espetáculo!',
                    );
                    router.visit(res.redirect);
                }
            } catch {
                /* tenta de novo */
            }
        }, 4000);

        return () => {
            active = false;
            window.clearInterval(id);
        };
    }, [awaitingPix, statusUrl]);

    return (
        <>
            <Head title="Checkout" />

            <div className="border-b border-border bg-surface/60">
                <div className="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-3 sm:px-6">
                    <nav className="flex min-w-0 items-center gap-1.5 font-body text-sm text-muted-foreground">
                        <Link href="/eventos" className="hover:text-foreground">
                            Eventos
                        </Link>
                        <Icon
                            name="chevron-right"
                            size={14}
                            className="text-faint"
                        />
                        <span className="text-foreground">Pagamento</span>
                    </nav>
                    <div className="flex items-center gap-2 rounded-btn border border-border bg-bg px-3 py-1.5">
                        <Icon name="alert" size={15} className="text-warning" />
                        <Countdown
                            expiresAt={reservation.expiresAt}
                            onExpire={onExpire}
                            withIcon={false}
                            label="Reserva"
                        />
                    </div>
                </div>
            </div>

            <div className="mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-10">
                <div className="grid gap-8 lg:grid-cols-[1fr_340px]">
                    <section className="order-2 flex flex-col gap-7 lg:order-1">
                        <div>
                            <h2 className="kicker text-faint">
                                Cupom de desconto
                            </h2>
                            <div className="mt-3">
                                <CouponInput
                                    onApply={applyCoupon}
                                    busy={couponBusy}
                                    feedback={couponFeedback}
                                />
                            </div>
                        </div>

                        <div>
                            <h2 className="kicker text-faint">Pagamento</h2>
                            <div className="mt-3 rounded-card border border-border bg-surface p-6">
                                <PaymentBrick
                                    method={method}
                                    onMethodChange={setMethod}
                                    card={card}
                                    onCardChange={(patch) =>
                                        setCard((prev) => ({
                                            ...prev,
                                            ...patch,
                                        }))
                                    }
                                    installmentOptions={installmentOptions}
                                    pix={pix}
                                />
                            </div>
                        </div>
                    </section>

                    <aside className="order-1 lg:sticky lg:top-20 lg:order-2 lg:self-start">
                        <CheckoutPanel
                            reservation={reservation}
                            lines={summary.lines}
                            total={summary.total}
                            onConfirm={confirm}
                            submitting={submitting || awaitingPix}
                        />
                    </aside>
                </div>
            </div>
        </>
    );
}

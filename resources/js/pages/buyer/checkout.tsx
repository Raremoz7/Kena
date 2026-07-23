import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { Countdown } from '@/components/atoms/Countdown';
import { Icon } from '@/components/atoms/Icon';
import { CouponInput } from '@/components/molecules/CouponInput';
import type { CouponFeedback } from '@/components/molecules/CouponInput';
import { CheckoutPanel } from '@/components/organisms/CheckoutPanel';
import { PaymentBrick } from '@/components/organisms/PaymentBrick';
import type {
    CardErrors,
    CardFields,
    PaymentMethod,
    PixData,
} from '@/components/organisms/PaymentBrick';
import { api, ApiError } from '@/lib/veludo/api';
import { veludoToast } from '@/lib/veludo/toast';
import type { PriceLine, ReservationInfo } from '@/lib/veludo/types';
import { useSecureCardFields } from '@/lib/veludo/useSecureCardFields';

interface PriceSummary {
    lines: PriceLine[];
    total: number;
}

interface PayResponse {
    status: 'pending' | 'paid' | 'failed' | 'cancelled' | 'refunded' | 'none';
    orderReference?: string;
    redirect: string | null;
    pix: (PixData & { expiresAt?: string | null }) | null;
    /** Motivo amigável da recusa do cartão (status_detail traduzido). */
    failureReason?: string | null;
    /** Prazo real da reserva — o Pix estende o hold no servidor. */
    reservationExpiresAt?: string | null;
}

interface CheckoutPageProps {
    reservation: ReservationInfo;
    priceSummary: PriceSummary;
    couponUrl: string;
    payUrl: string;
    statusUrl: string;
    releaseUrl: string;
    mpPublicKey: string | null;
    /** Comprador sem CPF cadastrado precisa informá-lo no checkout. */
    needsDocument: boolean;
    /** Pagamento pendente vivo (ex.: refresh com Pix aguardando) — restaura QR e polling. */
    pendingPayment: PayResponse | null;
}

const brl = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
});

/** Valida o CPF (dígitos verificadores) no cliente. */
function isValidCpf(raw: string): boolean {
    const cpf = raw.replace(/\D/g, '');

    if (cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)) {
        return false;
    }

    for (let t = 9; t < 11; t++) {
        let sum = 0;

        for (let i = 0; i < t; i++) {
            sum += Number(cpf[i]) * (t + 1 - i);
        }

        const digit = ((10 * sum) % 11) % 10;

        if (Number(cpf[t]) !== digit) {
            return false;
        }
    }

    return true;
}

/**
 * Validação dos campos NÃO sensíveis (nome/CPF). Número, validade e CVV são
 * validados pelo próprio Mercado Pago dentro dos Secure Fields.
 */
function validateCard(card: CardFields, needsDocument: boolean): CardErrors {
    const errors: CardErrors = {};

    if (!card.name.trim()) {
        errors.name = 'Informe o nome impresso no cartão.';
    }

    if (needsDocument) {
        if (!card.document.trim()) {
            errors.document = 'Informe o CPF do titular.';
        } else if (!isValidCpf(card.document)) {
            errors.document = 'CPF inválido.';
        }
    }

    return errors;
}

export default function CheckoutPage({
    reservation,
    priceSummary,
    couponUrl,
    payUrl,
    statusUrl,
    releaseUrl,
    mpPublicKey,
    needsDocument,
    pendingPayment,
}: CheckoutPageProps) {
    const [summary, setSummary] = useState<PriceSummary>(priceSummary);
    const [appliedCoupon, setAppliedCoupon] = useState<string | null>(null);
    const [couponBusy, setCouponBusy] = useState(false);
    const [couponFeedback, setCouponFeedback] = useState<CouponFeedback | null>(
        null,
    );

    // Refresh com pagamento pendente: restaura QR, polling e prazo.
    const restoredPix = pendingPayment?.pix ?? null;

    const [method, setMethod] = useState<PaymentMethod>(
        restoredPix ? 'pix' : 'card',
    );
    const [card, setCard] = useState<CardFields>({
        name: '',
        document: '',
        installments: 1,
    });
    const [pix, setPix] = useState<PixData | null>(
        restoredPix
            ? {
                  qrBase64: restoredPix.qrBase64,
                  copyPaste: restoredPix.copyPaste,
              }
            : null,
    );
    const [cardErrors, setCardErrors] = useState<CardErrors>({});
    const [submitting, setSubmitting] = useState(false);
    const [awaitingPayment, setAwaitingPayment] = useState(
        pendingPayment?.status === 'pending',
    );
    // Prazo real do hold — o back estende quando um Pix é gerado.
    const [deadline, setDeadline] = useState(
        pendingPayment?.reservationExpiresAt ?? reservation.expiresAt,
    );

    // Secure Fields do Mercado Pago (número/validade/CVV em iframes). Só monta
    // enquanto a aba Cartão está ativa e não há pagamento em andamento.
    const secureCard = useSecureCardFields(
        mpPublicKey,
        method === 'card' && !awaitingPayment,
    );

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

    /** Libera o hold e volta pra escolher assentos de novo. */
    function chooseOtherSeats() {
        router.delete(releaseUrl, { preserveScroll: true });
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
        if (res.reservationExpiresAt) {
            setDeadline(res.reservationExpiresAt);
        }

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
                res.failureReason ??
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
            setAwaitingPayment(true);
            veludoToast.info(
                'Pix gerado',
                'Escaneie o QR. Confirmamos automaticamente quando cair.',
            );
            setSubmitting(false);

            return;
        }

        if (res.status === 'pending') {
            // Cartão em análise (in_process): trava o botão e acompanha por polling
            // — sem isso o usuário clica de novo e paga duas vezes.
            setAwaitingPayment(true);
            veludoToast.info(
                'Pagamento em análise',
                'Estamos aguardando a confirmação da operadora. Não feche esta página.',
            );
        }

        setSubmitting(false);
    }

    async function confirm() {
        if (submitting || awaitingPayment) {
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

            const fieldErrors = validateCard(card, needsDocument);

            if (Object.keys(fieldErrors).length > 0) {
                setCardErrors(fieldErrors);
                setSubmitting(false);

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

            // Campos seguros com erro de validade: destaca e não tokeniza.
            if (Object.values(secureCard.errors).some(Boolean)) {
                veludoToast.error(
                    'Dados do cartão',
                    'Confira os campos do cartão destacados.',
                );
                setSubmitting(false);

                return;
            }

            const doc = card.document.replace(/\D/g, '');
            let tokenId: string;

            try {
                tokenId = await secureCard.createToken({
                    cardholderName: card.name,
                    ...(needsDocument
                        ? {
                              identificationType: 'CPF',
                              identificationNumber: doc,
                          }
                        : {}),
                });
            } catch (tokenError) {
                veludoToast.error(
                    'Dados do cartão',
                    tokenError instanceof Error && tokenError.message
                        ? tokenError.message
                        : 'Não foi possível validar o cartão. Confira número, validade e CVV.',
                );
                setSubmitting(false);

                return;
            }

            const paymentMethodId = await secureCard.resolvePaymentMethodId();

            const res = await api.post<PayResponse>(payUrl, {
                method: 'card',
                token: tokenId,
                payment_method_id: paymentMethodId,
                installments: card.installments,
                document: needsDocument ? doc : undefined,
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

    // Polling do pagamento pendente (Pix ou cartão em análise) até um estado final.
    useEffect(() => {
        if (!awaitingPayment) {
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
                        'Pagamento confirmado',
                        'Seus ingressos foram emitidos. Bom espetáculo!',
                    );
                    router.visit(res.redirect);

                    return;
                }

                if (res.status === 'failed') {
                    // Recusado: destrava para tentar outro meio (o hold segue vivo).
                    veludoToast.error(
                        'Pagamento não aprovado',
                        res.failureReason ??
                            'Tente novamente com outro meio de pagamento.',
                    );
                    setAwaitingPayment(false);
                    setPix(null);

                    return;
                }

                if (res.status === 'cancelled' || res.status === 'refunded') {
                    // Pix expirou/cancelado: os assentos foram liberados no servidor.
                    veludoToast.warning(
                        'Pagamento expirado',
                        'O prazo do Pix terminou e os assentos foram liberados. Escolha novamente.',
                    );
                    router.visit('/eventos');
                }
            } catch {
                /* tenta de novo */
            }
        }, 4000);

        return () => {
            active = false;
            window.clearInterval(id);
        };
    }, [awaitingPayment, statusUrl]);

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
                    <div className="flex items-center gap-3">
                        {!awaitingPayment && (
                            <button
                                type="button"
                                onClick={chooseOtherSeats}
                                className="font-body text-sm text-muted-foreground underline-offset-2 transition-colors hover:text-foreground hover:underline"
                            >
                                Escolher outros lugares
                            </button>
                        )}
                        <div className="flex items-center gap-2 rounded-btn border border-border bg-bg px-3 py-1.5">
                            <Icon
                                name="alert"
                                size={15}
                                className="text-warning"
                            />
                            <Countdown
                                expiresAt={deadline}
                                onExpire={onExpire}
                                withIcon={false}
                                label="Reserva"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <div className="mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-10">
                <div className="grid gap-8 lg:grid-cols-[1fr_340px]">
                    <section className="order-1 flex flex-col gap-7">
                        <div>
                            <h2 className="kicker text-faint">
                                Cupom de desconto
                            </h2>
                            <div className="mt-3">
                                <CouponInput
                                    onApply={applyCoupon}
                                    busy={couponBusy || awaitingPayment}
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
                                    onCardChange={(patch) => {
                                        setCard((prev) => ({
                                            ...prev,
                                            ...patch,
                                        }));
                                        // Limpa o erro dos campos que estão sendo editados.
                                        setCardErrors((prev) => {
                                            const next = { ...prev };

                                            for (const key of Object.keys(
                                                patch,
                                            ) as (keyof CardErrors)[]) {
                                                delete next[key];
                                            }

                                            return next;
                                        });
                                    }}
                                    installmentOptions={installmentOptions}
                                    pix={pix}
                                    errors={cardErrors}
                                    secureErrors={secureCard.errors}
                                    cardReady={secureCard.ready}
                                    configured={mpPublicKey !== null}
                                    needsDocument={needsDocument}
                                />
                            </div>
                        </div>
                    </section>

                    <aside className="order-2 lg:sticky lg:top-20 lg:self-start">
                        <CheckoutPanel
                            reservation={reservation}
                            lines={summary.lines}
                            total={summary.total}
                            onConfirm={confirm}
                            submitting={submitting || awaitingPayment}
                        />
                    </aside>
                </div>
            </div>
        </>
    );
}

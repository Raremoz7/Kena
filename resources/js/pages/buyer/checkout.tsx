import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { Countdown } from '@/components/atoms/Countdown';
import { Icon } from '@/components/atoms/Icon';
import { CouponInput } from '@/components/molecules/CouponInput';
import type { CouponFeedback } from '@/components/molecules/CouponInput';
import { CheckoutPanel } from '@/components/organisms/CheckoutPanel';
import {
    PaymentBrick,
    PaymentMethodPicker,
} from '@/components/organisms/PaymentBrick';
import type {
    CardErrors,
    CardFields,
    PaymentMethod,
    PixData,
} from '@/components/organisms/PaymentBrick';
import { cn } from '@/lib/utils';
import { api, ApiError } from '@/lib/veludo/api';
import { veludoToast } from '@/lib/veludo/toast';
import type { PriceLine, ReservationInfo } from '@/lib/veludo/types';
import { useCountdown } from '@/lib/veludo/useCountdown';
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
    /** "Preciso de mais tempo": estende a reserva uma vez. */
    extendUrl: string;
    mpPublicKey: string | null;
    /** Comprador sem CPF cadastrado precisa informá-lo no checkout. */
    needsDocument: boolean;
    /** Pagamento pendente vivo (ex.: refresh com Pix aguardando) — restaura QR e polling. */
    pendingPayment: PayResponse | null;
    /** Lugares escolhidos depois que a venda paga esgotou: só fecham com código de cortesia. */
    courtesyRequired: number;
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

/** Volta uma etapa (some com pagamento em andamento). */
function BackLink({
    onClick,
    hidden,
    children,
}: {
    onClick: () => void;
    hidden: boolean;
    children: string;
}) {
    if (hidden) {
        return null;
    }

    return (
        <button
            type="button"
            onClick={onClick}
            className="mt-4 inline-flex items-center gap-1 font-body text-sm text-muted-foreground underline-offset-2 hover:text-foreground hover:underline"
        >
            <Icon name="chevron-left" size={14} /> {children}
        </button>
    );
}

export default function CheckoutPage({
    reservation,
    priceSummary,
    couponUrl,
    payUrl,
    statusUrl,
    releaseUrl,
    extendUrl,
    mpPublicKey,
    needsDocument,
    pendingPayment,
    courtesyRequired,
}: CheckoutPageProps) {
    const [summary, setSummary] = useState<PriceSummary>(priceSummary);
    const [appliedCoupon, setAppliedCoupon] = useState<string | null>(null);
    const [couponBusy, setCouponBusy] = useState(false);
    const [couponFeedback, setCouponFeedback] = useState<CouponFeedback | null>(
        null,
    );

    // Refresh com pagamento pendente: restaura QR, polling e prazo.
    const restoredPix = pendingPayment?.pix ?? null;

    // Etapas: 1) cupom/cortesia, 2) forma de pagamento, 3) dados do método.
    // Com pagamento em andamento, abre direto na 3. Total zerado pula a forma
    // de pagamento: a etapa "details" vira só a confirmação.
    const [step, setStep] = useState<'coupon' | 'method' | 'details'>(
        pendingPayment?.status === 'pending' ? 'details' : 'coupon',
    );
    const stepHeading = useRef<HTMLHeadingElement>(null);
    const isFree = summary.total === 0;

    // Pix primeiro: é o padrão (o cartão só volta se a página recarregar com um cartão em análise).
    const [method, setMethod] = useState<PaymentMethod>(
        pendingPayment?.status === 'pending' && !restoredPix ? 'card' : 'pix',
    );
    // CPF de quem paga o Pix — separado do CPF do cartão e da conta.
    const [pixDocument, setPixDocument] = useState('');
    const [pixError, setPixError] = useState<string | undefined>();
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
    // na etapa de pagamento, com a aba Cartão ativa e sem pagamento em andamento.
    const secureCard = useSecureCardFields(
        mpPublicKey,
        step === 'details' && !isFree && method === 'card' && !awaitingPayment,
    );

    const installmentOptions = useMemo(() => {
        return [1, 2, 3].map((n) => ({
            value: n,
            label: `${n}× de ${brl.format(summary.total / n)} sem juros`,
        }));
    }, [summary.total]);

    // Aviso antes de a reserva acabar, com opção de mais tempo (WCAG 2.2.1).
    const { total: secondsLeft } = useCountdown(deadline);
    const [extended, setExtended] = useState(false);
    const [extending, setExtending] = useState(false);
    const showTimeWarning =
        !extended && !awaitingPayment && secondsLeft > 0 && secondsLeft <= 60;

    async function askMoreTime() {
        setExtending(true);

        try {
            const res = await api.post<{ reservationExpiresAt: string }>(
                extendUrl,
            );
            setDeadline(res.reservationExpiresAt);
            veludoToast.success(
                'Mais tempo',
                'Sua reserva ganhou mais 5 minutos.',
            );
        } catch (e) {
            veludoToast.error(
                'Não foi possível estender',
                e instanceof ApiError ? e.message : 'Tente novamente.',
            );
        } finally {
            setExtended(true);
            setExtending(false);
        }
    }

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

    /** Avança uma etapa. Lugar da cota de cortesia não sai do cupom sem o código. */
    function goNext() {
        if (step === 'method') {
            setStep('details');

            return;
        }

        if (courtesyRequired > 0 && !appliedCoupon) {
            setCouponFeedback({
                tone: 'danger',
                message:
                    'Digite e aplique o código de cortesia para continuar.',
            });

            return;
        }

        setStep(isFree ? 'details' : 'method');
    }

    function goBack() {
        if (awaitingPayment || submitting) {
            return;
        }

        setStep(step === 'details' && !isFree ? 'method' : 'coupon');
    }

    // Etapa nova (ou Pix gerado) começa do topo, e o foco vai ao título da
    // etapa (leitor de tela anuncia a troca).
    const firstRender = useRef(true);
    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;

            return;
        }

        window.scrollTo({ top: 0 });
        stepHeading.current?.focus({ preventScroll: true });
    }, [step, awaitingPayment]);

    async function confirm() {
        if (submitting || awaitingPayment) {
            return;
        }

        setSubmitting(true);

        try {
            if (isFree) {
                const res = await api.post<PayResponse>(payUrl, {
                    method: 'free',
                    coupon_code: appliedCoupon ?? undefined,
                });
                handlePay(res);

                return;
            }

            if (method === 'pix') {
                // Sempre o CPF de quem paga: nem sempre é o da conta.
                const pixDoc = pixDocument.replace(/\D/g, '');

                if (!isValidCpf(pixDoc)) {
                    setPixError(
                        pixDoc === ''
                            ? 'Informe o CPF de quem vai pagar.'
                            : 'CPF inválido.',
                    );
                    setSubmitting(false);

                    return;
                }

                const res = await api.post<PayResponse>(payUrl, {
                    method: 'pix',
                    coupon_code: appliedCoupon ?? undefined,
                    document: pixDoc,
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

    const stepList: { key: typeof step; label: string }[] = isFree
        ? [
              { key: 'coupon', label: 'Cupom ou cortesia' },
              { key: 'details', label: 'Confirmação' },
          ]
        : [
              { key: 'coupon', label: 'Cupom ou cortesia' },
              { key: 'method', label: 'Forma de pagamento' },
              {
                  key: 'details',
                  label: method === 'pix' ? 'Pagar com Pix' : 'Dados do cartão',
              },
          ];
    const stepIndex = Math.max(
        0,
        stepList.findIndex((item) => item.key === step),
    );
    const ctaLabel =
        step !== 'details'
            ? 'Continuar'
            : isFree
              ? 'Confirmar ingressos'
              : method === 'pix'
                ? awaitingPayment
                    ? 'Aguardando o Pix'
                    : 'Gerar Pix'
                : 'Pagar com cartão';

    return (
        <>
            <Head title="Checkout" />
            <h1 className="sr-only">Pagamento</h1>

            {showTimeWarning && (
                <div
                    role="alert"
                    className="border-b border-warning bg-warning/15 px-4 py-3"
                >
                    <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 sm:px-2">
                        <p className="font-body text-sm text-foreground">
                            Sua reserva acaba em menos de 1 minuto.
                        </p>
                        <button
                            type="button"
                            onClick={askMoreTime}
                            disabled={extending}
                            className="rounded-btn bg-accent px-4 py-2 font-body text-sm font-semibold text-accent-fg focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:outline-none disabled:opacity-60"
                        >
                            Preciso de mais tempo
                        </button>
                    </div>
                </div>
            )}

            <div className="border-b border-border bg-surface/60">
                <div className="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-3 sm:px-6">
                    <nav
                        aria-label="Caminho"
                        className="flex min-w-0 items-center gap-1.5 font-body text-sm text-muted-foreground"
                    >
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
                        <ol
                            aria-label="Etapas"
                            className="flex flex-wrap items-center gap-2 font-body text-xs text-faint"
                        >
                            {stepList.map((item, i) => (
                                <li
                                    key={item.key}
                                    aria-current={
                                        step === item.key ? 'step' : undefined
                                    }
                                    className={cn(
                                        'flex items-center gap-2',
                                        step === item.key &&
                                            'font-semibold text-foreground',
                                    )}
                                >
                                    {i > 0 && <span aria-hidden="true">›</span>}
                                    {i + 1}. {item.label}
                                </li>
                            ))}
                        </ol>

                        <div>
                            <h2
                                ref={stepHeading}
                                tabIndex={-1}
                                className="kicker text-faint focus:outline-none"
                            >
                                {stepIndex + 1}. {stepList[stepIndex].label}
                            </h2>

                            {step === 'coupon' && (
                                <>
                                    <p className="mt-1 font-body text-sm text-muted-foreground">
                                        Tem cupom de desconto ou código de
                                        cortesia? Aplique aqui. Se não tiver, é
                                        só continuar.
                                    </p>
                                    {courtesyRequired > 0 && (
                                        <p
                                            role="status"
                                            className="mt-3 rounded-card border border-warning bg-warning/15 px-4 py-3 font-body text-sm text-foreground"
                                        >
                                            Os ingressos à venda esgotaram.{' '}
                                            {courtesyRequired === 1
                                                ? '1 dos seus lugares é da cota de cortesia'
                                                : `${courtesyRequired} dos seus lugares são da cota de cortesia`}
                                            : digite abaixo o código de cortesia
                                            (nome completo da criança + 3
                                            caracteres) para concluir.
                                        </p>
                                    )}
                                    <div className="mt-3">
                                        <CouponInput
                                            onApply={applyCoupon}
                                            busy={couponBusy || awaitingPayment}
                                            feedback={couponFeedback}
                                        />
                                    </div>
                                </>
                            )}

                            {step === 'method' && (
                                <div className="mt-3">
                                    <PaymentMethodPicker
                                        value={method}
                                        onChange={setMethod}
                                    />
                                    <BackLink
                                        onClick={goBack}
                                        hidden={awaitingPayment}
                                    >
                                        Voltar ao cupom
                                    </BackLink>
                                </div>
                            )}

                            {step === 'details' && isFree && (
                                <div className="mt-3 rounded-card border border-border bg-surface p-6 font-body text-sm text-foreground">
                                    <p>
                                        {appliedCoupon
                                            ? `Com ${appliedCoupon}, seu pedido não tem custo.`
                                            : 'Seu pedido não tem custo.'}{' '}
                                        Não é preciso pagar: confirme para
                                        emitir os ingressos.
                                    </p>
                                    <BackLink
                                        onClick={goBack}
                                        hidden={awaitingPayment}
                                    >
                                        Voltar ao cupom
                                    </BackLink>
                                </div>
                            )}

                            {step === 'details' && !isFree && (
                                <div className="mt-3 rounded-card border border-border bg-surface p-6">
                                    <PaymentBrick
                                        method={method}
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
                                        pixDocument={pixDocument}
                                        onPixDocumentChange={(value) => {
                                            setPixDocument(value);
                                            setPixError(undefined);
                                        }}
                                        pixError={pixError}
                                    />
                                    <BackLink
                                        onClick={goBack}
                                        hidden={awaitingPayment}
                                    >
                                        Trocar forma de pagamento
                                    </BackLink>
                                </div>
                            )}
                        </div>
                    </section>

                    <aside className="order-2 lg:sticky lg:top-20 lg:self-start">
                        <CheckoutPanel
                            reservation={reservation}
                            lines={summary.lines}
                            total={summary.total}
                            onConfirm={step === 'details' ? confirm : goNext}
                            confirmLabel={ctaLabel}
                            submitting={submitting || awaitingPayment}
                        />
                    </aside>
                </div>
            </div>
        </>
    );
}

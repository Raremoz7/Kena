import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { Button } from '@/components/atoms/Button';
import { Icon } from '@/components/atoms/Icon';
import { Spinner } from '@/components/atoms/Spinner';
import { PriceSummary } from '@/components/molecules/PriceSummary';
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
import { photoCount } from '@/lib/photos/pricing';
import { cn } from '@/lib/utils';
import { api, ApiError } from '@/lib/veludo/api';
import { formatBRL } from '@/lib/veludo/format';
import { isValidCpf, validateCard } from '@/lib/veludo/payment-validation';
import { veludoToast } from '@/lib/veludo/toast';
import type { PriceLine } from '@/lib/veludo/types';
import { useSecureCardFields } from '@/lib/veludo/useSecureCardFields';

interface PriceSummaryData {
    lines: PriceLine[];
    total: number;
}

interface PayResponse {
    status: 'pending' | 'paid' | 'failed' | 'cancelled' | 'refunded';
    orderReference: string;
    redirect: string | null;
    pix: (PixData & { expiresAt?: string | null }) | null;
    failureReason?: string | null;
    priceSummary?: PriceSummaryData;
}

interface OrderInfo {
    reference: string;
    status: PayResponse['status'];
    eventTitle: string;
    galleryUrl: string;
    photos: { id: number; thumb: string | null; filename: string }[];
    unitPrice: number;
}

interface Props {
    order: OrderInfo;
    priceSummary: PriceSummaryData;
    payUrl: string;
    statusUrl: string;
    mpPublicKey: string | null;
    needsDocument: boolean;
    pendingPayment: PayResponse | null;
}

/** Quantas miniaturas cabem no resumo antes de virar "+N". */
const THUMBS_SHOWN = 8;

export default function PhotoCheckoutPage({
    order,
    priceSummary,
    payUrl,
    statusUrl,
    mpPublicKey,
    needsDocument,
    pendingPayment,
}: Props) {
    const [summary, setSummary] = useState(priceSummary);
    const restoredPix = pendingPayment?.pix ?? null;
    const resuming = pendingPayment?.status === 'pending';

    const [step, setStep] = useState<'method' | 'details'>(
        resuming ? 'details' : 'method',
    );
    const [method, setMethod] = useState<PaymentMethod>(
        resuming && !restoredPix ? 'card' : 'pix',
    );
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
    const [awaitingPayment, setAwaitingPayment] = useState(resuming);
    const stepHeading = useRef<HTMLHeadingElement>(null);

    const secureCard = useSecureCardFields(
        mpPublicKey,
        step === 'details' && method === 'card' && !awaitingPayment,
    );

    const installmentOptions = useMemo(
        () =>
            [1, 2, 3].map((n) => ({
                value: n,
                label: `${n}× de ${formatBRL(summary.total / n)} sem juros`,
            })),
        [summary.total],
    );

    const firstRender = useRef(true);
    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;

            return;
        }

        window.scrollTo({ top: 0 });
        stepHeading.current?.focus({ preventScroll: true });
    }, [step, awaitingPayment]);

    function goPaid(res: PayResponse, title: string) {
        veludoToast.success(title, 'Suas fotos estão liberadas para baixar.');
        router.visit(res.redirect ?? '/minhas-fotos');
    }

    function handlePay(res: PayResponse) {
        if (res.priceSummary) {
            setSummary(res.priceSummary);
        }

        if (res.status === 'paid') {
            goPaid(res, 'Pagamento aprovado');

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
            setPix({ qrBase64: res.pix.qrBase64, copyPaste: res.pix.copyPaste });
            setAwaitingPayment(true);
            veludoToast.info(
                'Pix gerado',
                'Escaneie o QR. Confirmamos automaticamente quando cair.',
            );
            setSubmitting(false);

            return;
        }

        if (res.status === 'pending') {
            // Cartão em análise: trava o botão e acompanha por polling.
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

                handlePay(
                    await api.post<PayResponse>(payUrl, {
                        method: 'pix',
                        document: pixDoc,
                    }),
                );

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

            handlePay(
                await api.post<PayResponse>(payUrl, {
                    method: 'card',
                    token: tokenId,
                    payment_method_id: paymentMethodId,
                    installments: card.installments,
                    document: needsDocument ? doc : undefined,
                }),
            );
        } catch (e) {
            veludoToast.error(
                'Pagamento não concluído',
                e instanceof ApiError
                    ? e.message
                    : 'Não foi possível concluir o pagamento.',
            );
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

                if (res.status === 'paid') {
                    goPaid(res, 'Pagamento confirmado');

                    return;
                }

                if (res.status === 'failed' || res.status === 'cancelled') {
                    // Recusado ou Pix vencido: destrava para tentar de novo, no mesmo pedido.
                    veludoToast.warning(
                        'Pagamento não concluído',
                        res.status === 'cancelled'
                            ? 'O prazo do Pix terminou. Gere outro Pix ou pague com cartão.'
                            : (res.failureReason ??
                                  'Tente novamente com outro meio de pagamento.'),
                    );
                    setAwaitingPayment(false);
                    setPix(null);
                    setStep('method');

                    return;
                }

                if (res.status === 'refunded') {
                    veludoToast.warning(
                        'Pagamento estornado',
                        'Este pedido foi estornado. Monte o carrinho de novo pela galeria.',
                    );
                    router.visit(order.galleryUrl);
                }
            } catch {
                /* tenta de novo */
            }
        }, 4000);

        return () => {
            active = false;
            window.clearInterval(id);
        };
    }, [awaitingPayment, statusUrl, order.galleryUrl]);

    const stepList: { key: typeof step; label: string }[] = [
        { key: 'method', label: 'Forma de pagamento' },
        {
            key: 'details',
            label: method === 'pix' ? 'Pagar com Pix' : 'Dados do cartão',
        },
    ];
    const stepIndex = step === 'method' ? 0 : 1;
    const ctaLabel =
        step === 'method'
            ? 'Continuar'
            : method === 'pix'
              ? awaitingPayment
                  ? 'Aguardando o Pix'
                  : 'Gerar Pix'
              : 'Pagar com cartão';

    const shown = order.photos.slice(0, THUMBS_SHOWN);
    const hidden = order.photos.length - shown.length;
    const busy = submitting || awaitingPayment;

    return (
        <>
            <Head title="Pagamento das fotos" />
            <h1 className="sr-only">Pagamento das fotos</h1>

            <div className="border-b border-border bg-surface/60">
                <div className="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-3 sm:px-6">
                    <nav
                        aria-label="Caminho"
                        className="flex min-w-0 items-center gap-1.5 font-body text-sm text-muted-foreground"
                    >
                        <Link href="/fotos" className="hover:text-foreground">
                            Fotos
                        </Link>
                        <Icon
                            name="chevron-right"
                            size={14}
                            className="text-faint"
                        />
                        <Link
                            href={order.galleryUrl}
                            className="truncate hover:text-foreground"
                        >
                            {order.eventTitle}
                        </Link>
                        <Icon
                            name="chevron-right"
                            size={14}
                            className="text-faint"
                        />
                        <span className="text-foreground">Pagamento</span>
                    </nav>
                    {!awaitingPayment && (
                        <Link
                            href={order.galleryUrl}
                            className="hidden font-body text-sm whitespace-nowrap text-muted-foreground underline-offset-2 hover:text-foreground hover:underline sm:inline"
                        >
                            Mudar a seleção
                        </Link>
                    )}
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

                            {step === 'method' && (
                                <div className="mt-3">
                                    <PaymentMethodPicker
                                        value={method}
                                        onChange={setMethod}
                                    />
                                </div>
                            )}

                            {step === 'details' && (
                                <div className="mt-3 rounded-card border border-border bg-surface p-6">
                                    <PaymentBrick
                                        method={method}
                                        card={card}
                                        onCardChange={(patch) => {
                                            setCard((prev) => ({
                                                ...prev,
                                                ...patch,
                                            }));
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
                                    {!busy && (
                                        <button
                                            type="button"
                                            onClick={() => setStep('method')}
                                            className="mt-4 inline-flex items-center gap-1 font-body text-sm text-muted-foreground underline-offset-2 hover:text-foreground hover:underline"
                                        >
                                            <Icon name="chevron-left" size={14} />{' '}
                                            Trocar forma de pagamento
                                        </button>
                                    )}
                                </div>
                            )}
                        </div>
                    </section>

                    <aside className="order-2 lg:sticky lg:top-20 lg:self-start">
                        <div className="overflow-hidden rounded-card border border-border bg-surface">
                            <div className="h-1 w-full bg-accent" />
                            <div className="p-6">
                                <p className="kicker text-faint">Suas fotos</p>
                                <p className="mt-2 font-display text-display-sm text-foreground uppercase">
                                    {order.eventTitle}
                                </p>
                                <p className="font-body text-sm text-muted-foreground">
                                    {photoCount(order.photos.length)} ·{' '}
                                    {formatBRL(order.unitPrice)} cada
                                </p>

                                <ul
                                    aria-label="Fotos do pedido"
                                    className="mt-4 grid grid-cols-4 gap-1.5 border-y border-border py-4"
                                >
                                    {shown.map((p) => (
                                        <li
                                            key={p.id}
                                            className="aspect-square overflow-hidden rounded-[6px] bg-surface-2"
                                        >
                                            {p.thumb && (
                                                <img
                                                    src={p.thumb}
                                                    alt=""
                                                    className="size-full object-cover"
                                                />
                                            )}
                                        </li>
                                    ))}
                                    {hidden > 0 && (
                                        <li className="flex aspect-square items-center justify-center rounded-[6px] bg-surface-2 font-body text-sm font-semibold text-muted-foreground">
                                            +{hidden}
                                        </li>
                                    )}
                                </ul>

                                <div className="mt-4">
                                    <PriceSummary
                                        lines={summary.lines}
                                        total={summary.total}
                                    />
                                </div>

                                <Button
                                    block
                                    className="mt-6"
                                    onClick={
                                        step === 'details'
                                            ? confirm
                                            : () => setStep('details')
                                    }
                                    disabled={busy}
                                >
                                    {submitting ? (
                                        <Spinner />
                                    ) : (
                                        <>
                                            {ctaLabel}
                                            <Icon name="arrow-right" size={18} />
                                        </>
                                    )}
                                </Button>
                                <p className="mt-2.5 flex items-center justify-center gap-1.5 text-center font-body text-xs text-faint">
                                    <Icon name="lock" size={13} />
                                    Fotos em alta, sem marca d'água, liberadas
                                    logo após o pagamento
                                </p>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </>
    );
}

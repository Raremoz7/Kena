import { Icon } from '@/components/atoms/Icon';
import type { IconName } from '@/components/atoms/Icon';
import { Input } from '@/components/atoms/Input';
import { FormField } from '@/components/molecules/FormField';
import { cn } from '@/lib/utils';
import { SECURE_FIELD_IDS } from '@/lib/veludo/useSecureCardFields';
import type { SecureFieldErrors } from '@/lib/veludo/useSecureCardFields';

export type PaymentMethod = 'card' | 'pix';

/** Campos NÃO sensíveis do cartão (os sensíveis vivem nos iframes do MP). */
export interface CardFields {
    name: string;
    document: string;
    installments: number;
}

export interface PixData {
    qrBase64: string | null;
    copyPaste: string | null;
}

interface InstallmentOption {
    value: number;
    label: string;
}

export type CardErrors = Partial<Record<'name' | 'document', string>>;

/** Formata o CPF como 000.000.000-00. */
function maskCpf(value: string): string {
    const d = value.replace(/\D/g, '').slice(0, 11);

    return d
        .replace(/(\d{3})(\d)/, '$1.$2')
        .replace(/(\d{3})(\d)/, '$1.$2')
        .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
}

interface PaymentBrickProps {
    method: PaymentMethod;
    onMethodChange: (method: PaymentMethod) => void;
    card: CardFields;
    onCardChange: (patch: Partial<CardFields>) => void;
    installmentOptions: InstallmentOption[];
    pix: PixData | null;
    /** Erros dos campos não sensíveis (nome/CPF). */
    errors?: CardErrors;
    /** Erros de validade dos campos seguros (número/validade/CVV). */
    secureErrors?: SecureFieldErrors;
    /** Os iframes seguros terminaram de carregar. */
    cardReady?: boolean;
    /** O Mercado Pago está configurado (public key presente). */
    configured?: boolean;
    /** Pede o CPF do pagador (comprador sem CPF cadastrado). */
    needsDocument?: boolean;
}

function TabBtn({
    active,
    icon,
    onClick,
    children,
}: {
    active: boolean;
    icon: IconName;
    onClick: () => void;
    children: string;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-pressed={active}
            className={cn(
                'flex items-center justify-center gap-2 rounded-[6px] py-2.5 font-body text-sm font-semibold transition-colors',
                active
                    ? 'bg-surface-2 text-foreground'
                    : 'text-muted-foreground hover:text-foreground',
            )}
        >
            <Icon name={icon} size={17} />
            {children}
        </button>
    );
}

/** Contêiner estilizado onde o iframe do campo seguro do MP é montado. */
function SecureFieldBox({ id, invalid }: { id: string; invalid?: boolean }) {
    return (
        <div
            id={id}
            className={cn(
                'h-[46px] w-full rounded-input border bg-bg px-[14px] transition-colors',
                '[&>iframe]:h-full [&>iframe]:w-full',
                'focus-within:ring-[3px]',
                invalid
                    ? 'border-danger focus-within:border-danger focus-within:ring-danger/20'
                    : 'border-border-strong focus-within:border-accent focus-within:ring-accent/20',
            )}
        />
    );
}

function copy(text: string) {
    void navigator.clipboard?.writeText(text);
}

/**
 * Pagamento (Mercado Pago). Cartão: Secure Fields (número/validade/CVV em
 * iframes do SDK — PCI SAQ A) + nome/CPF/parcelas como campos comuns.
 * Pix: QR + copia-e-cola reais retornados pelo backend após confirmar.
 */
export function PaymentBrick({
    method,
    onMethodChange,
    card,
    onCardChange,
    installmentOptions,
    pix,
    errors,
    secureErrors,
    cardReady,
    configured = true,
    needsDocument,
}: PaymentBrickProps) {
    return (
        <div>
            <div className="grid grid-cols-2 gap-1 rounded-btn border border-border-strong p-1">
                <TabBtn
                    active={method === 'card'}
                    icon="credit-card"
                    onClick={() => onMethodChange('card')}
                >
                    Cartão
                </TabBtn>
                <TabBtn
                    active={method === 'pix'}
                    icon="pix"
                    onClick={() => onMethodChange('pix')}
                >
                    Pix
                </TabBtn>
            </div>

            {method === 'card' ? (
                !configured ? (
                    <p className="mt-5 rounded-card border border-dashed border-border bg-bg px-5 py-6 text-center font-body text-sm text-muted-foreground">
                        A integração do Mercado Pago ainda não foi configurada.
                    </p>
                ) : (
                    <div className="mt-5 flex flex-col gap-4">
                        <FormField
                            label="Número do cartão"
                            error={secureErrors?.cardNumber}
                        >
                            <SecureFieldBox
                                id={SECURE_FIELD_IDS.cardNumber}
                                invalid={!!secureErrors?.cardNumber}
                            />
                        </FormField>
                        <div className="grid grid-cols-2 gap-4">
                            <FormField
                                label="Validade"
                                error={secureErrors?.expirationDate}
                            >
                                <SecureFieldBox
                                    id={SECURE_FIELD_IDS.expirationDate}
                                    invalid={!!secureErrors?.expirationDate}
                                />
                            </FormField>
                            <FormField
                                label="CVV"
                                error={secureErrors?.securityCode}
                            >
                                <SecureFieldBox
                                    id={SECURE_FIELD_IDS.securityCode}
                                    invalid={!!secureErrors?.securityCode}
                                />
                            </FormField>
                        </div>
                        <FormField
                            label="Nome impresso no cartão"
                            htmlFor="cc-name"
                            error={errors?.name}
                        >
                            <Input
                                id="cc-name"
                                placeholder="Como está no cartão"
                                autoComplete="cc-name"
                                value={card.name}
                                onChange={(e) =>
                                    onCardChange({ name: e.target.value })
                                }
                            />
                        </FormField>
                        {needsDocument && (
                            <FormField
                                label="CPF do titular"
                                htmlFor="cc-doc"
                                error={errors?.document}
                                helper="Exigido pelo Mercado Pago para pagamento com cartão."
                            >
                                <Input
                                    id="cc-doc"
                                    inputMode="numeric"
                                    placeholder="000.000.000-00"
                                    value={card.document}
                                    onChange={(e) =>
                                        onCardChange({
                                            document: maskCpf(e.target.value),
                                        })
                                    }
                                />
                            </FormField>
                        )}
                        <FormField label="Parcelas" htmlFor="cc-inst">
                            <select
                                id="cc-inst"
                                className="w-full rounded-input border border-border-strong bg-bg px-[14px] py-3 font-body text-sm text-foreground outline-none focus-visible:border-accent focus-visible:ring-[3px] focus-visible:ring-accent/20"
                                value={card.installments}
                                onChange={(e) =>
                                    onCardChange({
                                        installments: Number(e.target.value),
                                    })
                                }
                            >
                                {installmentOptions.map((opt) => (
                                    <option key={opt.value} value={opt.value}>
                                        {opt.label}
                                    </option>
                                ))}
                            </select>
                        </FormField>

                        {!cardReady && (
                            <p className="flex items-center gap-1.5 font-body text-xs text-faint">
                                <Icon name="shield" size={13} />
                                Carregando campos seguros do Mercado Pago…
                            </p>
                        )}
                    </div>
                )
            ) : (
                <div className="mt-5 flex flex-col items-center gap-4 rounded-card border border-border bg-bg px-5 py-7 text-center">
                    {pix?.qrBase64 ? (
                        <img
                            src={`data:image/png;base64,${pix.qrBase64}`}
                            alt="QR Code do Pix"
                            className="size-[160px] rounded-[8px] bg-white p-2"
                        />
                    ) : (
                        <div className="flex size-[140px] items-center justify-center rounded-[8px] border border-dashed border-border text-faint">
                            <Icon name="pix" size={40} />
                        </div>
                    )}
                    <div>
                        <p className="font-body text-sm font-medium text-foreground">
                            {pix?.qrBase64
                                ? 'Escaneie para pagar'
                                : 'Gere o Pix ao confirmar'}
                        </p>
                        <p className="mt-1 font-body text-xs text-muted-foreground">
                            Aprovação na hora. O pedido fica pendente até o Pix
                            cair.
                        </p>
                    </div>
                    {pix?.copyPaste && (
                        <button
                            type="button"
                            onClick={() => copy(pix.copyPaste as string)}
                            className="w-full truncate rounded-btn border border-border-strong py-2.5 font-mono text-xs text-muted-foreground transition-colors hover:bg-surface-2"
                        >
                            {pix.copyPaste.slice(0, 28)}… · toque para copiar
                        </button>
                    )}
                </div>
            )}

            <p className="mt-4 flex items-start gap-1.5 font-body text-[11px] text-faint">
                <Icon name="shield" size={14} className="mt-px text-success" />
                Pagamento criptografado. Os dados do cartão são capturados em
                campos seguros do Mercado Pago (iframes) e não passam pelo nosso
                servidor.
            </p>

            <p className="mt-2 text-center font-body text-[11px] text-faint">
                Processado com segurança por{' '}
                <span className="font-semibold text-muted-foreground">
                    Mercado&nbsp;Pago
                </span>
            </p>
        </div>
    );
}

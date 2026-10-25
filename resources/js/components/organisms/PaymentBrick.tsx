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
    /** Método já escolhido na etapa anterior (PaymentMethodPicker). */
    method: PaymentMethod;
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
    /** Pede o CPF do titular do cartão (comprador sem CPF cadastrado). */
    needsDocument?: boolean;
    /** CPF de quem paga o Pix (sempre pedido: nem sempre é o do login). */
    pixDocument: string;
    onPixDocumentChange: (value: string) => void;
    pixError?: string;
}

const METHODS: {
    value: PaymentMethod;
    icon: IconName;
    label: string;
    hint: string;
}[] = [
    {
        value: 'pix',
        icon: 'pix',
        label: 'Pix',
        hint: 'Aprovação na hora, pelo app do banco.',
    },
    {
        value: 'card',
        icon: 'credit-card',
        label: 'Cartão de crédito ou débito',
        hint: 'Crédito em até 3× sem juros.',
    },
];

/** Etapa "Forma de pagamento": só a escolha, os dados vêm na etapa seguinte. */
export function PaymentMethodPicker({
    value,
    onChange,
}: {
    value: PaymentMethod;
    onChange: (method: PaymentMethod) => void;
}) {
    return (
        <fieldset className="flex flex-col gap-3">
            <legend className="sr-only">Forma de pagamento</legend>
            {METHODS.map((m) => (
                <label
                    key={m.value}
                    className={cn(
                        'flex cursor-pointer items-center gap-4 rounded-card border bg-surface px-5 py-4 transition-colors',
                        'focus-within:ring-[3px] focus-within:ring-accent/20',
                        value === m.value
                            ? 'border-accent'
                            : 'border-border hover:border-border-strong',
                    )}
                >
                    <input
                        type="radio"
                        name="payment-method"
                        value={m.value}
                        checked={value === m.value}
                        onChange={() => onChange(m.value)}
                        className="size-4 accent-accent"
                    />
                    <Icon name={m.icon} size={22} className="text-foreground" />
                    <span className="flex flex-col">
                        <span className="font-body text-sm font-semibold text-foreground">
                            {m.label}
                        </span>
                        <span className="font-body text-xs text-muted-foreground">
                            {m.hint}
                        </span>
                    </span>
                </label>
            ))}
        </fieldset>
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
    card,
    onCardChange,
    installmentOptions,
    pix,
    errors,
    secureErrors,
    cardReady,
    configured = true,
    needsDocument,
    pixDocument,
    onPixDocumentChange,
    pixError,
}: PaymentBrickProps) {
    return (
        <div>
            {method === 'card' ? (
                !configured ? (
                    <p className="rounded-card border border-dashed border-border bg-bg px-5 py-6 text-center font-body text-sm text-muted-foreground">
                        A integração do Mercado Pago ainda não foi configurada.
                    </p>
                ) : (
                    <div className="flex flex-col gap-4">
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
            ) : pix?.qrBase64 || pix?.copyPaste ? (
                <div className="flex flex-col items-center gap-4 text-center">
                    {pix.qrBase64 && (
                        <img
                            src={`data:image/png;base64,${pix.qrBase64}`}
                            alt="QR Code do Pix"
                            className="size-[180px] rounded-[8px] bg-white p-2"
                        />
                    )}
                    <div>
                        <p className="font-body text-sm font-medium text-foreground">
                            Escaneie o QR ou copie o código no app do banco
                        </p>
                        <p className="mt-1 font-body text-xs text-muted-foreground">
                            Confirmamos sozinhos quando o Pix cair. Não feche
                            esta página.
                        </p>
                    </div>
                    {pix.copyPaste && (
                        <button
                            type="button"
                            onClick={() => copy(pix.copyPaste as string)}
                            className="w-full truncate rounded-btn border border-border-strong py-2.5 font-mono text-xs text-muted-foreground transition-colors hover:bg-surface-2"
                        >
                            {pix.copyPaste.slice(0, 28)}… · toque para copiar
                        </button>
                    )}
                </div>
            ) : (
                <div className="flex flex-col gap-4">
                    <FormField
                        label="CPF de quem vai pagar"
                        htmlFor="pix-doc"
                        error={pixError}
                        helper="Pode ser outra pessoa, não precisa ser o CPF da sua conta. Exigido pelo Mercado Pago."
                    >
                        <Input
                            id="pix-doc"
                            inputMode="numeric"
                            autoComplete="off"
                            placeholder="000.000.000-00"
                            value={pixDocument}
                            onChange={(e) =>
                                onPixDocumentChange(maskCpf(e.target.value))
                            }
                        />
                    </FormField>
                    <p className="font-body text-xs text-muted-foreground">
                        Ao gerar, mostramos o QR Code e o código copia e cola.
                    </p>
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

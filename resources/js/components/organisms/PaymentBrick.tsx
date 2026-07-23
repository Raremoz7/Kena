import { Icon } from '@/components/atoms/Icon';
import type { IconName } from '@/components/atoms/Icon';
import { Input } from '@/components/atoms/Input';
import { FormField } from '@/components/molecules/FormField';
import { cn } from '@/lib/utils';

export type PaymentMethod = 'card' | 'pix';

export interface CardFields {
    number: string;
    exp: string;
    cvv: string;
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

export type CardErrors = Partial<
    Record<'number' | 'exp' | 'cvv' | 'name' | 'document', string>
>;

/** Agrupa os dígitos do cartão em blocos de 4 (máx. 19 dígitos). */
function maskCardNumber(value: string): string {
    const digits = value.replace(/\D/g, '').slice(0, 19);

    return digits.replace(/(\d{4})(?=\d)/g, '$1 ').trim();
}

/** Formata a validade como MM/AA, inserindo a barra sozinha. */
function maskExpiry(value: string): string {
    const digits = value.replace(/\D/g, '').slice(0, 4);

    if (digits.length <= 2) {
        return digits;
    }

    return `${digits.slice(0, 2)}/${digits.slice(2)}`;
}

/** Mantém só dígitos no CVV (3 ou 4). */
function maskCvv(value: string): string {
    return value.replace(/\D/g, '').slice(0, 4);
}

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
    errors?: CardErrors;
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

function copy(text: string) {
    void navigator.clipboard?.writeText(text);
}

/**
 * Pagamento (Mercado Pago). Cartão: campos controlados tokenizados no cliente.
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
                <div className="mt-5 flex flex-col gap-4">
                    <FormField
                        label="Número do cartão"
                        htmlFor="cc-number"
                        error={errors?.number}
                    >
                        <div className="relative">
                            <Input
                                id="cc-number"
                                inputMode="numeric"
                                autoComplete="cc-number"
                                placeholder="0000 0000 0000 0000"
                                value={card.number}
                                onChange={(e) =>
                                    onCardChange({
                                        number: maskCardNumber(e.target.value),
                                    })
                                }
                            />
                            <Icon
                                name="credit-card"
                                size={18}
                                className="pointer-events-none absolute top-1/2 right-3.5 -translate-y-1/2 text-faint"
                            />
                        </div>
                    </FormField>
                    <div className="grid grid-cols-2 gap-4">
                        <FormField
                            label="Validade"
                            htmlFor="cc-exp"
                            error={errors?.exp}
                        >
                            <Input
                                id="cc-exp"
                                placeholder="MM/AA"
                                inputMode="numeric"
                                autoComplete="cc-exp"
                                value={card.exp}
                                onChange={(e) =>
                                    onCardChange({
                                        exp: maskExpiry(e.target.value),
                                    })
                                }
                            />
                        </FormField>
                        <FormField
                            label="CVV"
                            htmlFor="cc-cvv"
                            error={errors?.cvv}
                        >
                            <Input
                                id="cc-cvv"
                                placeholder="123"
                                inputMode="numeric"
                                autoComplete="cc-csc"
                                value={card.cvv}
                                onChange={(e) =>
                                    onCardChange({ cvv: maskCvv(e.target.value) })
                                }
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
                </div>
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
                Pagamento criptografado. Os dados do cartão são tokenizados no
                navegador e não passam pelo nosso servidor.
            </p>
        </div>
    );
}

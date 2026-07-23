// Ciclo de vida dos Secure Fields do Mercado Pago (PCI SAQ A). Monta os três
// iframes (número, validade, CVV) nos contêineres do formulário, acompanha a
// validade de cada campo e o BIN, e expõe a tokenização. Os dados sensíveis
// nunca tocam o React nem o nosso servidor.
import { useEffect, useRef, useState } from 'react';
import { createMercadoPago } from '@/lib/veludo/mercadopago';
import type {
    CardTokenParams,
    MercadoPagoInstance,
    SecureField,
    SecureFieldStyle,
    SecureFieldType,
} from '@/lib/veludo/mercadopago';

/** IDs dos contêineres onde os iframes do MP são montados. */
export const SECURE_FIELD_IDS = {
    cardNumber: 'mp-field-card-number',
    expirationDate: 'mp-field-expiration',
    securityCode: 'mp-field-security-code',
} as const;

const PLACEHOLDERS: Record<SecureFieldType, string> = {
    cardNumber: '0000 0000 0000 0000',
    expirationDate: 'MM/AA',
    securityCode: 'CVV',
};

/** Mensagem por campo quando o MP sinaliza invalidez. */
const FIELD_ERROR: Record<SecureFieldType, string> = {
    cardNumber: 'Número de cartão inválido.',
    expirationDate: 'Validade inválida (MM/AA).',
    securityCode: 'Código de segurança inválido.',
};

export type SecureFieldErrors = Partial<Record<SecureFieldType, string>>;

export interface SecureCardFields {
    /** Todos os iframes montaram e estão prontos para digitação. */
    ready: boolean;
    /** Erros de validade por campo, para feedback inline. */
    errors: SecureFieldErrors;
    /** Tokeniza o cartão (throw se algum campo estiver inválido). */
    createToken: (params: CardTokenParams) => Promise<string>;
    /** payment_method_id derivado do BIN (undefined se ainda desconhecido). */
    resolvePaymentMethodId: () => Promise<string | undefined>;
}

/** Lê cor/fonte do contêiner para estilizar o texto dentro do iframe. */
function styleFor(id: string): SecureFieldStyle {
    const el = document.getElementById(id);

    if (el === null) {
        return {};
    }

    const cs = getComputedStyle(el);
    const placeholder = getComputedStyle(document.documentElement)
        .getPropertyValue('--color-faint')
        .trim();

    return {
        color: cs.color,
        'font-size': cs.fontSize || '14px',
        'font-family': cs.fontFamily,
        ...(placeholder !== '' ? { placeholderColor: placeholder } : {}),
    };
}

export function useSecureCardFields(
    publicKey: string | null,
    active: boolean,
): SecureCardFields {
    const mpRef = useRef<MercadoPagoInstance | null>(null);
    const fieldsRef = useRef<SecureField[]>([]);
    const binRef = useRef<string | null>(null);
    const [ready, setReady] = useState(false);
    const [errors, setErrors] = useState<SecureFieldErrors>({});

    useEffect(() => {
        if (!active || publicKey === null) {
            return;
        }

        let cancelled = false;
        let readyCount = 0;
        const types: SecureFieldType[] = [
            'cardNumber',
            'expirationDate',
            'securityCode',
        ];

        async function mount() {
            try {
                const mp =
                    mpRef.current ?? (await createMercadoPago(publicKey ?? ''));
                mpRef.current = mp;

                if (cancelled) {
                    return;
                }

                for (const type of types) {
                    const id = SECURE_FIELD_IDS[type];
                    const field = mp.fields.create(type, {
                        placeholder: PLACEHOLDERS[type],
                        style: styleFor(id),
                    });

                    field.on('ready', () => {
                        readyCount += 1;

                        if (readyCount >= types.length && !cancelled) {
                            setReady(true);
                        }
                    });
                    field.on('validityChange', (data) => {
                        const invalid = (data.errorMessages?.length ?? 0) > 0;
                        setErrors((prev) => ({
                            ...prev,
                            [type]: invalid ? FIELD_ERROR[type] : undefined,
                        }));
                    });

                    if (type === 'cardNumber') {
                        field.on('binChange', (data) => {
                            binRef.current = data.bin ?? null;
                        });
                    }

                    field.mount(id);
                    fieldsRef.current.push(field);
                }
            } catch {
                /* Se o SDK não montar, o confirm() avisa ao tentar tokenizar. */
            }
        }

        void mount();

        return () => {
            cancelled = true;

            for (const field of fieldsRef.current) {
                try {
                    field.unmount();
                } catch {
                    /* iframe já removido */
                }
            }

            fieldsRef.current = [];
            binRef.current = null;
            setReady(false);
            setErrors({});
        };
    }, [publicKey, active]);

    return {
        ready,
        errors,
        createToken: async (params) => {
            const mp = mpRef.current;

            if (mp === null) {
                throw new Error(
                    'Os campos de pagamento não carregaram. Recarregue a página.',
                );
            }

            const token = await mp.fields.createCardToken(params);

            return token.id;
        },
        resolvePaymentMethodId: async () => {
            const mp = mpRef.current;
            const bin = binRef.current;

            if (mp === null || bin === null) {
                return undefined;
            }

            try {
                const methods = await mp.getPaymentMethods({ bin });

                return methods.results[0]?.id;
            } catch {
                return undefined;
            }
        },
    };
}

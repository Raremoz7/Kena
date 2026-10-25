// Carrega o SDK do Mercado Pago (v2) e expõe um wrapper tipado para os
// Secure Fields (PCI SAQ A): número, validade e CVV são renderizados pelo
// SDK dentro de iframes — os dados sensíveis do cartão nunca tocam o nosso
// DOM nem o nosso servidor.

/** Tipos de campo seguro suportados pelo SDK. */
export type SecureFieldType = 'cardNumber' | 'expirationDate' | 'securityCode';

/** Estilos aplicáveis ao conteúdo do iframe (subconjunto permitido pelo MP). */
export type SecureFieldStyle = Record<string, string>;

export interface SecureFieldOptions {
    placeholder?: string;
    style?: SecureFieldStyle;
}

/** Dado emitido pelos eventos dos campos seguros. */
export interface SecureFieldEvent {
    bin?: string;
    errorMessages?: Array<{ message: string; cause?: string }>;
}

export interface SecureField {
    mount(idOrElement: string | HTMLElement): SecureField;
    unmount(): SecureField;
    on(
        event:
            | 'ready'
            | 'binChange'
            | 'validityChange'
            | 'error'
            | 'focus'
            | 'blur',
        callback: (data: SecureFieldEvent) => void,
    ): SecureField;
    update(options: SecureFieldOptions): SecureField;
}

export interface CardTokenParams {
    cardholderName?: string;
    identificationType?: string;
    identificationNumber?: string;
}

export interface MercadoPagoFields {
    create(type: SecureFieldType, options?: SecureFieldOptions): SecureField;
    createCardToken(params: CardTokenParams): Promise<{ id: string }>;
}

export interface MercadoPagoInstance {
    fields: MercadoPagoFields;
    getPaymentMethods(params: {
        bin: string;
    }): Promise<{ results: Array<{ id: string }> }>;
}

type MercadoPagoCtor = new (
    publicKey: string,
    options?: { locale?: string },
) => MercadoPagoInstance;

declare global {
    interface Window {
        MercadoPago?: MercadoPagoCtor;
    }
}

const SDK_URL = 'https://sdk.mercadopago.com/js/v2';
let loader: Promise<MercadoPagoCtor> | null = null;

function loadSdk(): Promise<MercadoPagoCtor> {
    if (window.MercadoPago) {
        return Promise.resolve(window.MercadoPago);
    }

    if (loader) {
        return loader;
    }

    loader = new Promise<MercadoPagoCtor>((resolve, reject) => {
        const existing = document.querySelector<HTMLScriptElement>(
            `script[src="${SDK_URL}"]`,
        );
        const script = existing ?? document.createElement('script');
        script.src = SDK_URL;
        script.async = true;
        script.addEventListener('load', () => {
            if (window.MercadoPago) {
                resolve(window.MercadoPago);
            } else {
                reject(new Error('SDK do Mercado Pago indisponível.'));
            }
        });
        script.addEventListener('error', () =>
            reject(new Error('Falha ao carregar o Mercado Pago.')),
        );

        if (!existing) {
            document.head.appendChild(script);
        }
    });

    return loader;
}

export async function createMercadoPago(
    publicKey: string,
): Promise<MercadoPagoInstance> {
    const Ctor = await loadSdk();

    return new Ctor(publicKey, { locale: 'pt-BR' });
}

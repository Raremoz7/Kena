// Carrega o SDK do Mercado Pago (v2) e expõe um wrapper tipado mínimo para
// tokenizar cartão no cliente — o número do cartão nunca chega ao nosso servidor.

interface CardTokenData {
    cardNumber: string;
    cardholderName: string;
    cardExpirationMonth: string;
    cardExpirationYear: string;
    securityCode: string;
}

export interface MercadoPagoInstance {
    createCardToken(data: CardTokenData): Promise<{ id: string }>;
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

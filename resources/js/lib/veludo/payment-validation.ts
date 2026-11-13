import type { CardErrors, CardFields } from '@/components/organisms/PaymentBrick';

/** Valida o CPF (dígitos verificadores) no cliente. */
export function isValidCpf(raw: string): boolean {
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
export function validateCard(
    card: CardFields,
    needsDocument: boolean,
): CardErrors {
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

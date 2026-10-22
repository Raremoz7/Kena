import { useEffect, useState } from 'react';
import { Button } from '@/components/atoms/Button';
import { Input } from '@/components/atoms/Input';
import { Spinner } from '@/components/atoms/Spinner';
import { FormField } from '@/components/molecules/FormField';
import { api, ApiError } from '@/lib/veludo/api';

const RESEND_COOLDOWN_SECONDS = 30;

function looksLikeEmail(value: string): boolean {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim());
}

interface EmailCodeFieldProps {
    /** E-mail digitado no formulário (o código vale só para ele). */
    email: string;
    /** Rota POST que envia o código. */
    sendUrl: string;
    code: string;
    onCodeChange: (code: string) => void;
    error?: string;
    /** `name` do input quando o formulário é enviado nativamente (Inertia <Form>). */
    name?: string;
    id?: string;
}

/**
 * Confirmação do e-mail por código de 6 dígitos: botão "Enviar código" e, depois
 * do envio, o campo do código com reenvio após uma pequena espera. Trocar o
 * e-mail volta para o início — o código enviado vale só para o endereço antigo.
 */
export function EmailCodeField({
    email,
    sendUrl,
    code,
    onCodeChange,
    error,
    name,
    id = 'email-code',
}: EmailCodeFieldProps) {
    const [sentTo, setSentTo] = useState<string | null>(null);
    const [sending, setSending] = useState(false);
    const [message, setMessage] = useState<string | null>(null);
    const [sendError, setSendError] = useState<string | null>(null);
    const [cooldown, setCooldown] = useState(0);

    const normalized = email.trim().toLowerCase();
    const sent = sentTo !== null && sentTo === normalized;

    useEffect(() => {
        if (cooldown <= 0) {
            return;
        }

        const timer = window.setTimeout(() => setCooldown((c) => c - 1), 1000);

        return () => window.clearTimeout(timer);
    }, [cooldown]);

    async function send() {
        if (sending || !looksLikeEmail(email)) {
            setSendError(
                looksLikeEmail(email)
                    ? null
                    : 'Digite um e-mail válido para receber o código.',
            );

            return;
        }

        setSending(true);
        setSendError(null);

        try {
            const res = await api.post<{ message: string }>(sendUrl, { email });
            setSentTo(normalized);
            setMessage(res.message);
            setCooldown(RESEND_COOLDOWN_SECONDS);
            onCodeChange('');
        } catch (e) {
            setSendError(
                e instanceof ApiError
                    ? e.message
                    : 'Não foi possível enviar o código. Tente novamente.',
            );
        } finally {
            setSending(false);
        }
    }

    if (!sent) {
        return (
            <div className="flex flex-col gap-1.5">
                <Button
                    type="button"
                    variant="secondary"
                    onClick={send}
                    disabled={sending}
                >
                    {sending ? <Spinner /> : 'Enviar código de confirmação'}
                </Button>
                <p
                    className={
                        sendError || error
                            ? 'font-body text-xs text-danger-text'
                            : 'font-body text-xs text-faint'
                    }
                >
                    {sendError ??
                        error ??
                        'Enviamos um código para o seu e-mail para confirmar que ele está certo.'}
                </p>
            </div>
        );
    }

    return (
        <FormField
            label="Código de confirmação"
            htmlFor={id}
            error={sendError ?? error}
            helper={message ?? undefined}
            action={
                <button
                    type="button"
                    onClick={send}
                    disabled={sending || cooldown > 0}
                    className="font-body text-xs text-accent-text hover:underline disabled:text-faint disabled:no-underline"
                >
                    {cooldown > 0
                        ? `Reenviar em ${cooldown}s`
                        : 'Reenviar código'}
                </button>
            }
        >
            <Input
                id={id}
                name={name}
                value={code}
                onChange={(e) =>
                    onCodeChange(e.target.value.replace(/\D/g, '').slice(0, 6))
                }
                placeholder="000000"
                inputMode="numeric"
                autoComplete="one-time-code"
                maxLength={6}
                required
                autoFocus
            />
        </FormField>
    );
}

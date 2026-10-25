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
    /** Para onde voltar depois de entrar (ex.: a tela de assentos). */
    loginRedirect?: string;
}

interface ExistingAccount {
    message: string;
    google: boolean;
    password: boolean;
}

/** E-mail que já entra com Google/senha: o código não serve, mostra como entrar. */
function ExistingAccountNotice({
    account,
    email,
    loginRedirect,
}: {
    account: ExistingAccount;
    email: string;
    loginRedirect?: string;
}) {
    const back = loginRedirect
        ? `&redirect=${encodeURIComponent(loginRedirect)}`
        : '';

    return (
        <div
            role="alert"
            className="flex flex-col gap-3 rounded-btn border border-warning bg-warning/15 p-4"
        >
            <p className="font-body text-sm text-foreground">
                {account.message}
            </p>
            <div className="flex flex-wrap gap-2">
                {account.google && (
                    <Button asChild size="sm">
                        <a href="/auth/google/redirect">Continuar com Google</a>
                    </Button>
                )}
                {account.password && (
                    <Button
                        asChild
                        size="sm"
                        variant={account.google ? 'secondary' : 'primary'}
                    >
                        <a
                            href={`/login?email=${encodeURIComponent(email.trim())}${back}`}
                        >
                            Entrar com senha
                        </a>
                    </Button>
                )}
                {account.password && (
                    <Button asChild size="sm" variant="ghost">
                        <a href="/forgot-password">Esqueci minha senha</a>
                    </Button>
                )}
            </div>
        </div>
    );
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
    loginRedirect,
}: EmailCodeFieldProps) {
    const [sentTo, setSentTo] = useState<string | null>(null);
    const [sending, setSending] = useState(false);
    const [message, setMessage] = useState<string | null>(null);
    const [sendError, setSendError] = useState<string | null>(null);
    const [cooldown, setCooldown] = useState(0);
    const [existing, setExisting] = useState<{
        email: string;
        account: ExistingAccount;
    } | null>(null);
    // Anúncio para leitor de tela (WCAG 4.1.3): "código enviado" e erros de envio.
    const [announcement, setAnnouncement] = useState('');

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
            const invalid = looksLikeEmail(email)
                ? null
                : 'Digite um e-mail válido para receber o código.';
            setSendError(invalid);
            setAnnouncement(invalid ?? '');

            return;
        }

        setSending(true);
        setSendError(null);
        setExisting(null);

        try {
            const res = await api.post<{ message: string }>(sendUrl, { email });
            setSentTo(normalized);
            setMessage(res.message);
            setAnnouncement(
                `${res.message} Digite os 6 números no campo do código.`,
            );
            setCooldown(RESEND_COOLDOWN_SECONDS);
            onCodeChange('');
        } catch (e) {
            // 409: o e-mail já entra com Google/senha — mostra como entrar.
            if (e instanceof ApiError && e.status === 409 && e.data.account) {
                setExisting({
                    email: normalized,
                    account: e.data.account as ExistingAccount,
                });
                setAnnouncement(e.message);

                return;
            }

            const msg =
                e instanceof ApiError
                    ? e.message
                    : 'Não foi possível enviar o código. Tente novamente.';
            setSendError(msg);
            setAnnouncement(msg);
        } finally {
            setSending(false);
        }
    }

    // Fica sempre no mesmo lugar da árvore: a região viva já existe antes da mensagem.
    const liveRegion = (
        <p role="status" aria-live="polite" className="sr-only">
            {announcement}
        </p>
    );

    // O aviso vale só para o e-mail que gerou ele; trocar o e-mail volta ao normal.
    if (existing !== null && existing.email === normalized) {
        return (
            <>
                {liveRegion}
                <ExistingAccountNotice
                    account={existing.account}
                    email={email}
                    loginRedirect={loginRedirect}
                />
            </>
        );
    }

    if (!sent) {
        return (
            <>
                {liveRegion}
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
            </>
        );
    }

    return (
        <>
            {liveRegion}
            <FormField
                label="Código de confirmação"
                htmlFor={id}
                error={sendError ?? error}
                helper={
                    message
                        ? `${message} Não chegou em 1 minuto? Confira se o e-mail está certo e olhe o spam.`
                        : undefined
                }
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
                        onCodeChange(
                            e.target.value.replace(/\D/g, '').slice(0, 6),
                        )
                    }
                    placeholder="000000"
                    inputMode="numeric"
                    autoComplete="one-time-code"
                    maxLength={6}
                    required
                    autoFocus
                />
            </FormField>
        </>
    );
}

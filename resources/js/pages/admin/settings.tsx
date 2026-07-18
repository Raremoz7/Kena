import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Badge } from '@/components/atoms/Badge';
import { Button } from '@/components/atoms/Button';
import { Icon } from '@/components/atoms/Icon';
import { Input } from '@/components/atoms/Input';
import { Select } from '@/components/atoms/Select';
import { Textarea } from '@/components/atoms/Textarea';
import { FormField } from '@/components/molecules/FormField';
import { api, ApiError } from '@/lib/veludo/api';
import { veludoToast } from '@/lib/veludo/toast';

interface Mp {
    publicKey: string;
    accessTokenConfigured: boolean;
    webhookSecretConfigured: boolean;
    statementDescriptor: string;
    pixExpiration: number;
}

interface Mail {
    mailer: string;
    host: string;
    port: number;
    username: string;
    encryption: string;
    fromAddress: string;
    fromName: string;
    passwordConfigured: boolean;
}

interface Setup {
    mpAccessToken: boolean;
    mpPublicKey: boolean;
    mpWebhookSecret: boolean;
    mail: boolean;
}

interface Gw {
    issuerId: string;
    classId: string;
    saEmail: string;
    privateKeyConfigured: boolean;
    configured: boolean;
}

interface SettingsProps {
    mp: Mp;
    mail: Mail;
    gw: Gw;
    webhookUrl: string;
    setup: Setup;
    testMailUrl: string;
    testMpUrl: string;
}

type CheckStatus = 'ok' | 'warn' | 'fail';

interface MpCheck {
    key: string;
    label: string;
    status: CheckStatus;
    detail: string;
}

interface MpDiagnostic {
    ok: boolean;
    checks: MpCheck[];
}

const checkTone: Record<
    CheckStatus,
    { icon: 'check' | 'alert' | 'close'; dot: string; text: string }
> = {
    ok: {
        icon: 'check',
        dot: 'bg-success text-white',
        text: 'text-foreground',
    },
    warn: {
        icon: 'alert',
        dot: 'bg-warning text-warning-fg',
        text: 'text-foreground',
    },
    fail: {
        icon: 'close',
        dot: 'bg-danger text-white',
        text: 'text-foreground',
    },
};

/** Resultado de uma checagem: o que falhou e o que fazer a respeito. */
function DiagnosticItem({ check }: { check: MpCheck }) {
    const tone = checkTone[check.status];

    return (
        <li className="flex items-start gap-2.5">
            <span
                className={`mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full ${tone.dot}`}
            >
                <Icon name={tone.icon} size={12} />
            </span>
            <div className="min-w-0">
                <p className={`font-body text-sm font-medium ${tone.text}`}>
                    {check.label}
                </p>
                <p className="font-body text-xs text-muted-foreground">
                    {check.detail}
                </p>
            </div>
        </li>
    );
}

function ChecklistItem({ done, label }: { done: boolean; label: string }) {
    return (
        <li className="flex items-center gap-2.5 font-body text-sm">
            <span
                className={`flex size-5 shrink-0 items-center justify-center rounded-full ${
                    done
                        ? 'bg-success text-white'
                        : 'border border-border text-faint'
                }`}
            >
                <Icon name={done ? 'check' : 'clock'} size={12} />
            </span>
            <span
                className={done ? 'text-foreground' : 'text-muted-foreground'}
            >
                {label}
            </span>
        </li>
    );
}

export default function Settings({
    mp,
    mail,
    gw,
    webhookUrl,
    setup,
    testMailUrl,
    testMpUrl,
}: SettingsProps) {
    const [testingMail, setTestingMail] = useState(false);
    const [testingMp, setTestingMp] = useState(false);
    const [mpDiagnostic, setMpDiagnostic] = useState<MpDiagnostic | null>(null);

    async function testMercadoPago() {
        if (testingMp) {
            return;
        }

        setTestingMp(true);

        try {
            const res = await api.post<MpDiagnostic>(testMpUrl);
            setMpDiagnostic(res);

            if (res.ok) {
                veludoToast.success('Mercado Pago', 'Integração funcionando.');
            } else {
                veludoToast.error(
                    'Mercado Pago',
                    'Encontramos problemas — veja os detalhes abaixo.',
                );
            }
        } catch (e) {
            const message =
                e instanceof ApiError
                    ? e.message
                    : 'Não foi possível rodar o diagnóstico.';
            veludoToast.error('Mercado Pago', message);
        } finally {
            setTestingMp(false);
        }
    }

    async function sendTestMail() {
        if (testingMail) {
            return;
        }

        setTestingMail(true);

        try {
            const res = await api.post<{ message: string }>(testMailUrl);
            veludoToast.success('E-mail de teste', res.message);
        } catch (e) {
            const message =
                e instanceof ApiError
                    ? e.message
                    : 'Falha ao enviar o e-mail de teste.';
            veludoToast.error('E-mail de teste', message);
        } finally {
            setTestingMail(false);
        }
    }

    const form = useForm({
        mp_access_token: '',
        mp_public_key: mp.publicKey,
        mp_webhook_secret: '',
        mp_statement_descriptor: mp.statementDescriptor,
        mp_pix_expiration: String(mp.pixExpiration || 30),

        mail_mailer: mail.mailer || 'smtp',
        mail_host: mail.host || '',
        mail_port: String(mail.port || 587),
        mail_username: mail.username || '',
        mail_password: '',
        mail_encryption: mail.encryption || 'tls',
        mail_from_address: mail.fromAddress || '',
        mail_from_name: mail.fromName || '',

        gw_issuer_id: gw.issuerId || '',
        gw_class_id: gw.classId || '',
        gw_sa_email: gw.saEmail || '',
        gw_private_key: '',
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        form.post('/painel/config', {
            preserveScroll: true,
            onSuccess: () => {
                veludoToast.success(
                    'Configurações salvas',
                    'Parâmetros atualizados.',
                );
                form.setData('mp_access_token', '');
                form.setData('mp_webhook_secret', '');
                form.setData('mail_password', '');
                form.setData('gw_private_key', '');
            },
        });
    }

    const allReady =
        setup.mpAccessToken &&
        setup.mpPublicKey &&
        setup.mpWebhookSecret &&
        setup.mail;

    return (
        <>
            <Head title="Painel — Configurações" />
            <div className="mx-auto max-w-2xl px-6 py-8 sm:px-8">
                <h1 className="font-display text-display-lg text-foreground uppercase">
                    Configurações
                </h1>
                <p className="mt-1 font-body text-sm text-muted-foreground">
                    Preencha as etapas abaixo para deixar o sistema pronto para
                    vender.
                </p>

                {/* Checklist */}
                <div className="mt-6 rounded-card border border-border bg-surface p-6">
                    <div className="flex items-center justify-between gap-3">
                        <h2 className="font-display text-display-sm text-foreground uppercase">
                            Pronto para vender
                        </h2>
                        <Badge tone={allReady ? 'success' : 'warning'}>
                            {allReady ? 'Tudo pronto' : 'Faltam etapas'}
                        </Badge>
                    </div>
                    <ul className="mt-4 flex flex-col gap-2.5">
                        <ChecklistItem
                            done={setup.mpAccessToken}
                            label="Mercado Pago — Access Token"
                        />
                        <ChecklistItem
                            done={setup.mpPublicKey}
                            label="Mercado Pago — Public Key"
                        />
                        <ChecklistItem
                            done={setup.mpWebhookSecret}
                            label="Mercado Pago — Webhook Secret"
                        />
                        <ChecklistItem
                            done={setup.mail}
                            label="E-mail (SMTP) configurado"
                        />
                    </ul>

                    <div className="mt-5 border-t border-border pt-4">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div className="min-w-0">
                                <p className="kicker text-faint">
                                    Diagnóstico da integração
                                </p>
                                <p className="mt-1 font-body text-xs text-muted-foreground">
                                    Confere as credenciais direto na API do
                                    Mercado Pago. Não cria cobrança nem deixa
                                    registro na sua conta.
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="secondary"
                                size="sm"
                                onClick={testMercadoPago}
                                disabled={testingMp}
                            >
                                {testingMp ? 'Testando…' : 'Testar integração'}
                            </Button>
                        </div>

                        {mpDiagnostic && (
                            <div
                                className={`mt-4 rounded-card border p-4 ${
                                    mpDiagnostic.ok
                                        ? 'border-success bg-success/5'
                                        : 'border-danger bg-danger/5'
                                }`}
                            >
                                <p className="font-body text-sm font-medium text-foreground">
                                    {mpDiagnostic.ok
                                        ? 'Integração pronta para receber pagamentos.'
                                        : 'A integração não está pronta:'}
                                </p>
                                <ul className="mt-3 flex flex-col gap-3">
                                    {mpDiagnostic.checks.map((check) => (
                                        <DiagnosticItem
                                            key={check.key}
                                            check={check}
                                        />
                                    ))}
                                </ul>
                            </div>
                        )}
                    </div>

                    <div className="mt-5 border-t border-border pt-4">
                        <p className="kicker text-faint">
                            URL do webhook (cole no painel do Mercado Pago)
                        </p>
                        <div className="mt-2 flex items-center gap-2">
                            <code className="min-w-0 flex-1 truncate rounded-btn border border-border bg-bg px-3 py-2 font-mono text-xs text-muted-foreground">
                                {webhookUrl}
                            </code>
                            <Button
                                type="button"
                                variant="secondary"
                                size="sm"
                                onClick={() => {
                                    void navigator.clipboard?.writeText(
                                        webhookUrl,
                                    );
                                    veludoToast.success(
                                        'Copiado',
                                        'URL do webhook na área de transferência.',
                                    );
                                }}
                            >
                                Copiar
                            </Button>
                        </div>
                    </div>

                    <p className="mt-4 flex items-start gap-1.5 font-body text-[11px] text-faint">
                        <Icon name="info" size={14} className="mt-px" />
                        Os e-mails são enviados em segundo plano — mantenha um{' '}
                        <strong>worker de fila</strong> rodando no servidor
                        (`php artisan queue:work`).
                    </p>
                </div>

                <form onSubmit={submit} className="mt-6 flex flex-col gap-6">
                    {/* Mercado Pago */}
                    <div className="rounded-card border border-border bg-surface p-6">
                        <div className="flex items-center justify-between gap-3 border-b border-border pb-4">
                            <div className="flex items-center gap-2">
                                <Icon
                                    name="credit-card"
                                    size={18}
                                    className="text-accent"
                                />
                                <h2 className="font-display text-display-sm text-foreground uppercase">
                                    Mercado Pago
                                </h2>
                            </div>
                            <Badge
                                tone={
                                    mp.accessTokenConfigured
                                        ? 'success'
                                        : 'warning'
                                }
                            >
                                {mp.accessTokenConfigured
                                    ? 'Configurado'
                                    : 'Pendente'}
                            </Badge>
                        </div>

                        <div className="mt-5 flex flex-col gap-4">
                            <FormField
                                label="Public Key"
                                htmlFor="mp_public_key"
                                helper="Chave pública usada no checkout (Bricks)."
                            >
                                <Input
                                    id="mp_public_key"
                                    value={form.data.mp_public_key}
                                    onChange={(e) =>
                                        form.setData(
                                            'mp_public_key',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="APP_USR-..."
                                />
                            </FormField>

                            <FormField
                                label="Access Token"
                                htmlFor="mp_access_token"
                                helper={
                                    mp.accessTokenConfigured
                                        ? 'Já configurado — deixe em branco para manter.'
                                        : 'Token secreto do servidor.'
                                }
                                error={form.errors.mp_access_token}
                            >
                                <Input
                                    id="mp_access_token"
                                    type="password"
                                    autoComplete="off"
                                    value={form.data.mp_access_token}
                                    onChange={(e) =>
                                        form.setData(
                                            'mp_access_token',
                                            e.target.value,
                                        )
                                    }
                                    placeholder={
                                        mp.accessTokenConfigured
                                            ? '•••••••• (configurado)'
                                            : 'APP_USR-...'
                                    }
                                />
                            </FormField>

                            <FormField
                                label="Webhook Secret"
                                htmlFor="mp_webhook_secret"
                                helper={
                                    mp.webhookSecretConfigured
                                        ? 'Já configurado — deixe em branco para manter.'
                                        : 'Assinatura dos webhooks do Mercado Pago.'
                                }
                                error={form.errors.mp_webhook_secret}
                            >
                                <Input
                                    id="mp_webhook_secret"
                                    type="password"
                                    autoComplete="off"
                                    value={form.data.mp_webhook_secret}
                                    onChange={(e) =>
                                        form.setData(
                                            'mp_webhook_secret',
                                            e.target.value,
                                        )
                                    }
                                    placeholder={
                                        mp.webhookSecretConfigured
                                            ? '•••••••• (configurado)'
                                            : ''
                                    }
                                />
                            </FormField>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <FormField
                                    label="Descritor na fatura"
                                    htmlFor="mp_statement_descriptor"
                                >
                                    <Input
                                        id="mp_statement_descriptor"
                                        value={
                                            form.data.mp_statement_descriptor
                                        }
                                        onChange={(e) =>
                                            form.setData(
                                                'mp_statement_descriptor',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="KENA INGRESSOS"
                                    />
                                </FormField>
                                <FormField
                                    label="Expiração do Pix (min)"
                                    htmlFor="mp_pix_expiration"
                                    error={form.errors.mp_pix_expiration}
                                >
                                    <Input
                                        id="mp_pix_expiration"
                                        type="number"
                                        inputMode="numeric"
                                        value={form.data.mp_pix_expiration}
                                        onChange={(e) =>
                                            form.setData(
                                                'mp_pix_expiration',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                            </div>
                        </div>
                    </div>

                    {/* E-mail (SMTP) */}
                    <div className="rounded-card border border-border bg-surface p-6">
                        <div className="flex items-center justify-between gap-3 border-b border-border pb-4">
                            <div className="flex items-center gap-2">
                                <Icon
                                    name="info"
                                    size={18}
                                    className="text-accent"
                                />
                                <h2 className="font-display text-display-sm text-foreground uppercase">
                                    E-mail (SMTP)
                                </h2>
                            </div>
                            <Badge tone={setup.mail ? 'success' : 'warning'}>
                                {setup.mail ? 'Configurado' : 'Pendente'}
                            </Badge>
                        </div>

                        <div className="mt-5 flex flex-col gap-4">
                            <div className="grid gap-4 sm:grid-cols-[1fr_120px]">
                                <FormField
                                    label="Servidor (host)"
                                    htmlFor="mail_host"
                                    error={form.errors.mail_host}
                                >
                                    <Input
                                        id="mail_host"
                                        value={form.data.mail_host}
                                        onChange={(e) =>
                                            form.setData(
                                                'mail_host',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="smtp.seuprovedor.com"
                                    />
                                </FormField>
                                <FormField
                                    label="Porta"
                                    htmlFor="mail_port"
                                    error={form.errors.mail_port}
                                >
                                    <Input
                                        id="mail_port"
                                        type="number"
                                        inputMode="numeric"
                                        value={form.data.mail_port}
                                        onChange={(e) =>
                                            form.setData(
                                                'mail_port',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="587"
                                    />
                                </FormField>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <FormField
                                    label="Usuário"
                                    htmlFor="mail_username"
                                    error={form.errors.mail_username}
                                >
                                    <Input
                                        id="mail_username"
                                        value={form.data.mail_username}
                                        onChange={(e) =>
                                            form.setData(
                                                'mail_username',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="usuario@provedor.com"
                                    />
                                </FormField>
                                <FormField
                                    label="Senha"
                                    htmlFor="mail_password"
                                    helper={
                                        mail.passwordConfigured
                                            ? 'Já configurada — deixe em branco para manter.'
                                            : undefined
                                    }
                                    error={form.errors.mail_password}
                                >
                                    <Input
                                        id="mail_password"
                                        type="password"
                                        autoComplete="off"
                                        value={form.data.mail_password}
                                        onChange={(e) =>
                                            form.setData(
                                                'mail_password',
                                                e.target.value,
                                            )
                                        }
                                        placeholder={
                                            mail.passwordConfigured
                                                ? '•••••••• (configurada)'
                                                : ''
                                        }
                                    />
                                </FormField>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <FormField
                                    label="Criptografia"
                                    htmlFor="mail_encryption"
                                >
                                    <Select
                                        id="mail_encryption"
                                        value={form.data.mail_encryption}
                                        onChange={(e) =>
                                            form.setData(
                                                'mail_encryption',
                                                e.target.value,
                                            )
                                        }
                                    >
                                        <option value="tls">
                                            TLS (porta 587)
                                        </option>
                                        <option value="ssl">
                                            SSL (porta 465)
                                        </option>
                                        <option value="none">Nenhuma</option>
                                    </Select>
                                </FormField>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <FormField
                                    label="Remetente (e-mail)"
                                    htmlFor="mail_from_address"
                                    error={form.errors.mail_from_address}
                                >
                                    <Input
                                        id="mail_from_address"
                                        type="email"
                                        value={form.data.mail_from_address}
                                        onChange={(e) =>
                                            form.setData(
                                                'mail_from_address',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="contato@kena.com.br"
                                    />
                                </FormField>
                                <FormField
                                    label="Remetente (nome)"
                                    htmlFor="mail_from_name"
                                >
                                    <Input
                                        id="mail_from_name"
                                        value={form.data.mail_from_name}
                                        onChange={(e) =>
                                            form.setData(
                                                'mail_from_name',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Kena"
                                    />
                                </FormField>
                            </div>
                        </div>

                        <div className="mt-5 flex flex-wrap items-center gap-3 border-t border-border pt-4">
                            <Button
                                type="button"
                                variant="secondary"
                                size="sm"
                                onClick={sendTestMail}
                                disabled={testingMail}
                            >
                                <Icon name="info" size={15} />
                                {testingMail
                                    ? 'Enviando…'
                                    : 'Enviar e-mail de teste'}
                            </Button>
                            <span className="font-body text-[11px] text-faint">
                                Vai para o seu e-mail. Salve as configurações
                                antes de testar.
                            </span>
                        </div>

                        <p className="mt-4 flex items-start gap-1.5 font-body text-[11px] text-faint">
                            <Icon
                                name="shield"
                                size={14}
                                className="mt-px text-success"
                            />
                            Segredos são guardados encriptados no banco (AES via
                            APP_KEY) e nunca retornam ao navegador.
                        </p>
                    </div>

                    {/* Google Wallet (opcional) */}
                    <div className="rounded-card border border-border bg-surface p-6">
                        <div className="flex items-center justify-between gap-3 border-b border-border pb-4">
                            <div className="flex items-center gap-2">
                                <Icon
                                    name="ticket"
                                    size={18}
                                    className="text-accent"
                                />
                                <h2 className="font-display text-display-sm text-foreground uppercase">
                                    Google Wallet
                                </h2>
                            </div>
                            <Badge tone={gw.configured ? 'success' : 'neutral'}>
                                {gw.configured ? 'Configurado' : 'Opcional'}
                            </Badge>
                        </div>
                        <p className="mt-3 font-body text-xs text-muted-foreground">
                            Habilita o botão "Adicionar ao Google Wallet" nos
                            ingressos. Antes de preencher os campos abaixo, é
                            preciso fazer um cadastro único no Google (fora
                            daqui):
                        </p>

                        <ol className="mt-3 flex flex-col gap-2 border-l-2 border-border pl-4 font-body text-xs text-muted-foreground">
                            <li>
                                <strong className="text-foreground">1.</strong>{' '}
                                Crie (ou acesse) sua conta de emissor no{' '}
                                <a
                                    href="https://pay.google.com/business/console"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-accent-text hover:underline"
                                >
                                    Google Wallet Console
                                </a>{' '}
                                — lá você encontra o{' '}
                                <strong className="text-foreground">
                                    Issuer ID
                                </strong>
                                .
                            </li>
                            <li>
                                <strong className="text-foreground">2.</strong>{' '}
                                Ainda no Wallet Console, crie uma{' '}
                                <strong className="text-foreground">
                                    Generic Class
                                </strong>{' '}
                                para o evento — o identificador dela é o{' '}
                                <strong className="text-foreground">
                                    Class ID
                                </strong>
                                .
                            </li>
                            <li>
                                <strong className="text-foreground">3.</strong>{' '}
                                No{' '}
                                <a
                                    href="https://console.cloud.google.com/iam-admin/serviceaccounts"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-accent-text hover:underline"
                                >
                                    Google Cloud Console
                                </a>
                                , crie uma service account e gere uma chave em
                                formato JSON. Libere o e-mail dela como
                                colaboradora no Wallet Console (passo 1).
                            </li>
                            <li>
                                <strong className="text-foreground">4.</strong>{' '}
                                Abra o JSON baixado: copie{' '}
                                <code className="rounded bg-bg px-1 py-0.5 font-mono">
                                    client_email
                                </code>{' '}
                                para "Service account" e{' '}
                                <code className="rounded bg-bg px-1 py-0.5 font-mono">
                                    private_key
                                </code>{' '}
                                (inteira, com as linhas{' '}
                                <code className="rounded bg-bg px-1 py-0.5 font-mono">
                                    BEGIN/END
                                </code>
                                ) para "Chave privada" abaixo.
                            </li>
                        </ol>

                        <div className="mt-5 flex flex-col gap-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <FormField
                                    label="Issuer ID"
                                    htmlFor="gw_issuer_id"
                                    error={form.errors.gw_issuer_id}
                                >
                                    <Input
                                        id="gw_issuer_id"
                                        value={form.data.gw_issuer_id}
                                        onChange={(e) =>
                                            form.setData(
                                                'gw_issuer_id',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="3388000000000000000"
                                    />
                                </FormField>
                                <FormField
                                    label="Class ID (sufixo)"
                                    htmlFor="gw_class_id"
                                    error={form.errors.gw_class_id}
                                >
                                    <Input
                                        id="gw_class_id"
                                        value={form.data.gw_class_id}
                                        onChange={(e) =>
                                            form.setData(
                                                'gw_class_id',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="kena_evento"
                                    />
                                </FormField>
                            </div>
                            <FormField
                                label="Service account (e-mail)"
                                htmlFor="gw_sa_email"
                                error={form.errors.gw_sa_email}
                            >
                                <Input
                                    id="gw_sa_email"
                                    type="email"
                                    value={form.data.gw_sa_email}
                                    onChange={(e) =>
                                        form.setData(
                                            'gw_sa_email',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="kena@projeto.iam.gserviceaccount.com"
                                />
                            </FormField>
                            <FormField
                                label="Chave privada (PEM)"
                                htmlFor="gw_private_key"
                                helper={
                                    gw.privateKeyConfigured
                                        ? 'Já configurada — deixe em branco para manter.'
                                        : 'Cole a private_key da service account (-----BEGIN PRIVATE KEY----- …).'
                                }
                                error={form.errors.gw_private_key}
                            >
                                <Textarea
                                    id="gw_private_key"
                                    rows={4}
                                    className="font-mono text-xs"
                                    value={form.data.gw_private_key}
                                    onChange={(e) =>
                                        form.setData(
                                            'gw_private_key',
                                            e.target.value,
                                        )
                                    }
                                    placeholder={
                                        gw.privateKeyConfigured
                                            ? '•••••••• (configurada)'
                                            : '-----BEGIN PRIVATE KEY-----'
                                    }
                                />
                            </FormField>
                        </div>
                    </div>

                    <div className="flex justify-end">
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? 'Salvando…' : 'Salvar'}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

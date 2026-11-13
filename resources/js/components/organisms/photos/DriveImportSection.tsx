import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/atoms/Button';
import { Icon } from '@/components/atoms/Icon';
import { Input } from '@/components/atoms/Input';
import { FormField } from '@/components/molecules/FormField';

export interface DriveState {
    configured: boolean;
    email: string | null;
    hasFolder: boolean;
    status: 'queued' | 'running' | 'done' | 'failed' | null;
    finishedAt: string | null;
    error: string | null;
    result: {
        imported: number;
        skipped: number;
        people: number;
        failed: { ref: string; reason: string }[];
        failedCount: number;
    } | null;
}

interface Props {
    drive: DriveState;
    eventId: number;
    value: string;
    savedValue: string;
    onChange: (value: string) => void;
    error?: string;
}

/** Link da pasta do Drive, e-mail da conta de serviço e importação em fila. */
export function DriveImportSection({
    drive,
    eventId,
    value,
    savedValue,
    onChange,
    error,
}: Props) {
    const [copied, setCopied] = useState(false);
    const [starting, setStarting] = useState(false);
    const running = drive.status === 'queued' || drive.status === 'running';
    const unsaved = value.trim() !== savedValue.trim();

    // Enquanto a fila trabalha, pergunta o andamento a cada poucos segundos.
    useEffect(() => {
        if (!running) {
return;
}

        const timer = setInterval(
            () => router.reload({ only: ['drive'] }),
            3000,
        );

        return () => clearInterval(timer);
    }, [running]);

    function copyEmail() {
        if (!drive.email) {
return;
}

        navigator.clipboard
            ?.writeText(drive.email)
            .then(() => {
                setCopied(true);
                setTimeout(() => setCopied(false), 2000);
            })
            .catch(() => undefined);
    }

    function importNow() {
        router.post(
            `/painel/fotos/${eventId}/importar-drive`,
            {},
            {
                preserveScroll: true,
                onStart: () => setStarting(true),
                onFinish: () => setStarting(false),
            },
        );
    }

    return (
        <section className="flex flex-col gap-4 rounded-card border border-border bg-surface p-4 sm:p-6">
            <div>
                <h2 className="font-display text-display-sm text-foreground uppercase">
                    Fotos do Google Drive
                </h2>
                <p className="mt-1 font-body text-sm text-muted-foreground">
                    O editor exporta do Lightroom para uma pasta do Drive, uma
                    subpasta por turma. Cole o link da pasta do evento, salve e
                    importe.
                </p>
            </div>

            {drive.configured ? (
                <div className="flex flex-col gap-2 rounded-card border border-border bg-bg px-4 py-3">
                    <p className="font-body text-sm text-muted-foreground">
                        Compartilhe a pasta, como leitor, com este e-mail:
                    </p>
                    <div className="flex flex-wrap items-center gap-2">
                        <code className="font-mono text-sm break-all text-foreground">
                            {drive.email}
                        </code>
                        <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            onClick={copyEmail}
                        >
                            {copied ? 'Copiado' : 'Copiar'}
                        </Button>
                    </div>
                </div>
            ) : (
                <p
                    role="status"
                    className="flex items-start gap-2 rounded-card border border-warning bg-warning/10 px-4 py-3 font-body text-sm text-foreground"
                >
                    <Icon name="alert" size={18} className="mt-0.5 shrink-0" />
                    <span>
                        O Google Drive ainda não foi configurado neste servidor.
                        O passo a passo está em{' '}
                        <code className="font-mono">
                            docs/fotos/configurar-google-drive.md
                        </code>
                        .
                    </span>
                </p>
            )}

            <FormField
                label="Link da pasta do Drive"
                htmlFor="drive_folder"
                helper="Pode colar o link completo da pasta. Deixe vazio se as fotos vierem de outro lugar."
                error={error}
            >
                <Input
                    id="drive_folder"
                    inputMode="url"
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    placeholder="https://drive.google.com/drive/folders/..."
                />
            </FormField>

            <div className="flex flex-wrap items-center gap-3">
                <Button
                    type="button"
                    variant="secondary"
                    onClick={importNow}
                    disabled={
                        !drive.hasFolder || unsaved || running || starting
                    }
                >
                    {running ? 'Importando…' : 'Importar agora'}
                </Button>
                {unsaved && (
                    <span className="font-body text-xs text-faint">
                        Salve as alterações para importar a pasta nova.
                    </span>
                )}
            </div>

            {running && (
                <p
                    role="status"
                    className="font-body text-sm text-muted-foreground"
                >
                    {drive.status === 'queued'
                        ? 'Na fila, começa em instantes…'
                        : 'Importando as fotos. Pode levar alguns minutos, e a página se atualiza sozinha.'}
                </p>
            )}

            {drive.status === 'failed' && drive.error && (
                <p
                    role="alert"
                    className="rounded-card border border-danger bg-danger/10 px-4 py-3 font-body text-sm text-danger-text"
                >
                    {drive.error}
                </p>
            )}

            {drive.status === 'done' && drive.result && (
                <div
                    role="status"
                    className="flex flex-col gap-2 rounded-card border border-border bg-bg px-4 py-3"
                >
                    <p className="font-body text-sm text-foreground">
                        Última importação{' '}
                        {drive.finishedAt ? `(${drive.finishedAt})` : ''}:{' '}
                        <strong>{drive.result.imported}</strong> importadas,{' '}
                        <strong>{drive.result.skipped}</strong> já existiam,{' '}
                        <strong>{drive.result.failedCount}</strong> falharam,{' '}
                        <strong>{drive.result.people}</strong> pessoas novas.
                    </p>
                    {drive.result.failed.length > 0 && (
                        <ul className="flex flex-col gap-1 font-body text-xs text-danger-text">
                            {drive.result.failed.map((f) => (
                                <li key={f.ref}>
                                    {f.ref}: {f.reason}
                                </li>
                            ))}
                            {drive.result.failedCount >
                                drive.result.failed.length && (
                                <li>
                                    e mais{' '}
                                    {drive.result.failedCount -
                                        drive.result.failed.length}
                                    …
                                </li>
                            )}
                        </ul>
                    )}
                </div>
            )}
        </section>
    );
}

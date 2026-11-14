import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/atoms/Button';
import { Icon } from '@/components/atoms/Icon';
import { ConfirmDialog } from '@/components/molecules/ConfirmDialog';

export interface NotifyState {
    published: boolean;
    audience: number;
    sentAt: string | null;
    sentCount: number | null;
}

interface Props {
    notify: NotifyState;
    eventId: number;
}

const people = (n: number) => (n === 1 ? '1 pessoa' : `${n} pessoas`);

const sentDate = (iso: string) =>
    new Date(iso).toLocaleString('pt-BR', {
        day: '2-digit',
        month: 'long',
        hour: '2-digit',
        minute: '2-digit',
    });

/**
 * Aviso por e-mail de que as fotos chegaram, para quem comprou ingresso.
 * Sai uma vez por galeria e só com ela publicada: o e-mail leva direto para lá.
 */
export function NotifyBuyersSection({ notify, eventId }: Props) {
    const [confirming, setConfirming] = useState(false);
    const [sending, setSending] = useState(false);
    const error = usePage().props.errors?.notify as string | undefined;

    function send() {
        setConfirming(false);
        router.post(
            `/painel/fotos/${eventId}/avisar-compradores`,
            {},
            {
                preserveScroll: true,
                onStart: () => setSending(true),
                onFinish: () => setSending(false),
            },
        );
    }

    return (
        <section className="flex flex-col gap-3 rounded-card border border-border bg-surface p-4 sm:p-6">
            <div>
                <h2 className="font-display text-display-sm text-foreground uppercase">
                    Avisar quem comprou ingresso
                </h2>
                <p className="mt-1 font-body text-sm text-muted-foreground">
                    Um e-mail com prévias das fotos, o preço e o botão para abrir
                    a galeria. Sai uma vez só.
                </p>
            </div>

            {notify.sentAt ? (
                <p className="flex items-center gap-2 rounded-card border border-border bg-bg px-4 py-3 font-body text-sm text-foreground">
                    <Icon name="check" size={18} className="shrink-0 text-accent" />
                    Enviado para {people(notify.sentCount ?? 0)} em{' '}
                    {sentDate(notify.sentAt)}.
                </p>
            ) : !notify.published ? (
                <p className="font-body text-sm text-muted-foreground">
                    Publique a galeria primeiro. Hoje o aviso iria para{' '}
                    {people(notify.audience)}.
                </p>
            ) : notify.audience === 0 ? (
                <p className="font-body text-sm text-muted-foreground">
                    Ninguém comprou ingresso deste evento ainda, então não há
                    para quem avisar.
                </p>
            ) : (
                <div>
                    <Button
                        type="button"
                        onClick={() => setConfirming(true)}
                        disabled={sending}
                    >
                        {sending
                            ? 'Enviando…'
                            : `Avisar ${people(notify.audience)}`}
                    </Button>
                </div>
            )}

            {error && (
                <p role="alert" className="font-body text-sm text-danger-text">
                    {error}
                </p>
            )}

            <ConfirmDialog
                open={confirming}
                onOpenChange={setConfirming}
                title="Enviar o aviso das fotos?"
                description={`O e-mail vai para ${
                    notify.audience === 1
                        ? 'a pessoa que comprou'
                        : `as ${notify.audience} pessoas que compraram`
                } ingresso deste evento. Não dá para enviar de novo depois.`}
                confirmLabel={`Enviar para ${people(notify.audience)}`}
                tone="default"
                onConfirm={send}
            />
        </section>
    );
}

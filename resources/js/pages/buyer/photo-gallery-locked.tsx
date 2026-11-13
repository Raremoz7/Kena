import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Button, buttonVariants } from '@/components/atoms/Button';
import { Icon } from '@/components/atoms/Icon';
import { Input } from '@/components/atoms/Input';
import { Tag } from '@/components/atoms/Tag';
import { FormField } from '@/components/molecules/FormField';
import { photoCount } from '@/lib/photos/pricing';
import type { PhotoEvent } from '@/lib/photos/types';
import { cn } from '@/lib/utils';

interface Props {
    event: PhotoEvent;
    photoCount: number;
    loginUrl: string;
    /** E-mail da conta logada que não tem ingresso deste evento, se houver. */
    account: string | null;
}

/**
 * Entrada da galeria. As fotos mostram rosto e nome de crianças, então a
 * galeria só abre com o código da escola ou com a conta que comprou o ingresso.
 */
export default function PhotoGalleryLockedPage({
    event,
    photoCount: total,
    loginUrl,
    account,
}: Props) {
    const form = useForm({ code: '' });

    function submit(e: FormEvent) {
        e.preventDefault();
        form.post(`/fotos/${event.slug}/acesso`, { preserveScroll: true });
    }

    return (
        <>
            <Head title={`Fotos · ${event.title}`} />

            <div className="relative isolate h-56 overflow-hidden bg-surface-2 sm:h-72">
                {event.bannerImage && (
                    <img
                        src={event.bannerImage}
                        alt=""
                        className="absolute inset-0 -z-10 size-full scale-105 object-cover blur-[2px]"
                    />
                )}
                <div className="absolute inset-0 -z-10 bg-gradient-to-b from-black/30 via-black/40 to-bg" />
            </div>

            <div className="relative mx-auto -mt-28 max-w-md px-4 pb-16 sm:-mt-36 sm:px-6">
                <div className="rounded-card border border-border bg-surface p-6 shadow-2xl sm:p-8">
                    <span
                        aria-hidden="true"
                        className="flex size-12 items-center justify-center rounded-full bg-accent/15 text-accent-text"
                    >
                        <Icon name="lock" size={22} />
                    </span>

                    <Tag className="mt-5 block">Fotos do espetáculo</Tag>
                    <h1 className="mt-1.5 font-display text-display-md text-foreground uppercase">
                        {event.title}
                    </h1>
                    <p className="mt-1 flex items-center gap-1.5 font-body text-sm text-muted-foreground">
                        <Icon name="calendar" size={15} />
                        {event.dateLabel}
                        <span aria-hidden="true">·</span>
                        {photoCount(total)}
                    </p>

                    <p className="mt-5 font-body text-sm text-muted-foreground">
                        As fotos têm o rosto e o nome das crianças, por isso a
                        galeria é só para as famílias. Digite o código que a
                        escola enviou.
                    </p>

                    <form onSubmit={submit} className="mt-5" noValidate>
                        <FormField
                            label="Código de acesso"
                            htmlFor="code"
                            error={form.errors.code}
                            helper="Parecido com NOZES-4821. Maiúscula e hífen não importam."
                        >
                            <Input
                                id="code"
                                name="code"
                                value={form.data.code}
                                onChange={(e) =>
                                    form.setData(
                                        'code',
                                        e.target.value.toUpperCase(),
                                    )
                                }
                                placeholder="PALAVRA-0000"
                                autoComplete="off"
                                autoCapitalize="characters"
                                autoCorrect="off"
                                spellCheck={false}
                                enterKeyHint="go"
                                className="py-3.5 text-center font-display text-xl tracking-[0.18em] uppercase placeholder:tracking-[0.12em]"
                            />
                        </FormField>
                        <Button
                            type="submit"
                            block
                            size="lg"
                            className="mt-4"
                            disabled={form.processing || !form.data.code.trim()}
                        >
                            Ver as fotos
                            <Icon name="arrow-right" size={18} />
                        </Button>
                    </form>

                    <div className="my-6 flex items-center gap-3">
                        <span className="h-px flex-1 bg-border" />
                        <span className="font-body text-xs text-faint">
                            ou
                        </span>
                        <span className="h-px flex-1 bg-border" />
                    </div>

                    <div className="flex items-start gap-3">
                        <span
                            aria-hidden="true"
                            className="mt-0.5 text-accent-text"
                        >
                            <Icon name="ticket" size={20} />
                        </span>
                        <div className="min-w-0 flex-1">
                            <p className="font-body text-sm font-semibold text-foreground">
                                Comprou o ingresso aqui?
                            </p>
                            <p className="mt-0.5 font-body text-sm text-muted-foreground">
                                Entre com a conta do ingresso e a galeria abre
                                sem código.
                            </p>
                            {account && (
                                <p
                                    role="status"
                                    className="mt-2 rounded-input border border-border bg-bg px-3 py-2 font-body text-xs text-muted-foreground"
                                >
                                    A conta{' '}
                                    <span className="font-semibold break-all text-foreground">
                                        {account}
                                    </span>{' '}
                                    não tem ingresso pago deste espetáculo. Tente
                                    com a conta usada na compra.
                                </p>
                            )}
                            <Link
                                href={loginUrl}
                                className={cn(
                                    buttonVariants({
                                        variant: 'secondary',
                                        block: true,
                                    }),
                                    'mt-3',
                                )}
                            >
                                {account
                                    ? 'Entrar com outra conta'
                                    : 'Entrar com a conta do ingresso'}
                            </Link>
                        </div>
                    </div>
                </div>

                <Link
                    href="/fotos"
                    className="mt-6 inline-flex items-center gap-1 font-body text-sm text-muted-foreground transition-colors hover:text-foreground"
                >
                    <Icon name="chevron-left" size={16} />
                    Todas as galerias
                </Link>
            </div>
        </>
    );
}

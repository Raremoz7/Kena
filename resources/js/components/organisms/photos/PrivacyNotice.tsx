import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/atoms/Button';
import { Icon } from '@/components/atoms/Icon';
import { Input } from '@/components/atoms/Input';
import { Textarea } from '@/components/atoms/Textarea';
import { FormField } from '@/components/molecules/FormField';
import { Modal } from '@/components/molecules/Modal';

interface Props {
    slug: string;
    /** Nomes das crianças da galeria, para sugerir ao responsável. */
    childNames: string[];
}

/**
 * Aviso sobre o filtro por rosto e porta de saída: o responsável pede para a
 * criança sair do filtro. Aqui só registra o pedido; o painel trata depois.
 */
export function PrivacyNotice({ slug, childNames }: Props) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        guardian_name: '',
        contact: '',
        child_name: '',
        message: '',
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        form.post(`/fotos/${slug}/privacidade`, {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                form.reset();
            },
        });
    }

    return (
        <>
            <aside
                aria-label="Privacidade das crianças"
                className="mt-5 flex items-start gap-3 rounded-card border border-border bg-surface px-4 py-3.5"
            >
                <span aria-hidden="true" className="mt-0.5 text-accent-text">
                    <Icon name="shield" size={18} />
                </span>
                <p className="min-w-0 font-body text-sm text-muted-foreground">
                    O filtro mostra o rosto e o primeiro nome de cada criança
                    para você achar as fotos dela, e só abre para as famílias
                    deste espetáculo.{' '}
                    <button
                        type="button"
                        onClick={() => setOpen(true)}
                        className="rounded-sm font-semibold text-accent-text underline underline-offset-4 outline-none focus-visible:ring-2 focus-visible:ring-accent"
                    >
                        Não quero minha filha no filtro
                    </button>
                </p>
            </aside>

            <Modal
                open={open}
                onOpenChange={setOpen}
                title="Tirar do filtro por rosto"
                description="A criança deixa de aparecer no filtro de rostos. As fotos continuam na galeria, achadas pela turma."
            >
                <form onSubmit={submit} className="flex flex-col gap-4" noValidate>
                    <FormField
                        label="Seu nome"
                        htmlFor="privacy-guardian"
                        error={form.errors.guardian_name}
                    >
                        <Input
                            id="privacy-guardian"
                            value={form.data.guardian_name}
                            onChange={(e) =>
                                form.setData('guardian_name', e.target.value)
                            }
                            autoComplete="name"
                        />
                    </FormField>
                    <FormField
                        label="Nome da criança"
                        htmlFor="privacy-child"
                        error={form.errors.child_name}
                    >
                        <Input
                            id="privacy-child"
                            list="privacy-child-names"
                            value={form.data.child_name}
                            onChange={(e) =>
                                form.setData('child_name', e.target.value)
                            }
                            autoComplete="off"
                        />
                        <datalist id="privacy-child-names">
                            {childNames.map((n) => (
                                <option key={n} value={n} />
                            ))}
                        </datalist>
                    </FormField>
                    <FormField
                        label="E-mail ou WhatsApp"
                        htmlFor="privacy-contact"
                        helper="Para a gente confirmar com você."
                        error={form.errors.contact}
                    >
                        <Input
                            id="privacy-contact"
                            value={form.data.contact}
                            onChange={(e) =>
                                form.setData('contact', e.target.value)
                            }
                            autoComplete="email"
                        />
                    </FormField>
                    <FormField
                        label="Quer nos contar mais? (opcional)"
                        htmlFor="privacy-message"
                        error={form.errors.message}
                    >
                        <Textarea
                            id="privacy-message"
                            rows={3}
                            value={form.data.message}
                            onChange={(e) =>
                                form.setData('message', e.target.value)
                            }
                        />
                    </FormField>
                    <div className="mt-1 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end sm:gap-3">
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={() => setOpen(false)}
                        >
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            Enviar pedido
                        </Button>
                    </div>
                </form>
            </Modal>
        </>
    );
}

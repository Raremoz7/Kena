import { Head, Link, useForm } from '@inertiajs/react';
import type {FormEvent} from 'react';
import { Button } from '@/components/atoms/Button';
import { Icon } from '@/components/atoms/Icon';
import { Input } from '@/components/atoms/Input';
import { FormField } from '@/components/molecules/FormField';
import { veludoToast } from '@/lib/veludo/toast';

export default function SetPassword() {
    const form = useForm({ password: '', password_confirmation: '' });

    function submit(e: FormEvent) {
        e.preventDefault();
        form.post('/definir-senha', {
            onSuccess: () => {
                veludoToast.success('Senha criada', 'Agora você pode entrar com e-mail e senha.');
            },
        });
    }

    return (
        <>
            <Head title="Criar senha" />
            <div className="mx-auto max-w-md px-4 py-12 sm:px-6">
                <Link
                    href="/meus-ingressos"
                    className="inline-flex items-center gap-1.5 font-body text-sm text-muted-foreground hover:text-foreground"
                >
                    <Icon name="chevron-left" size={16} /> Meus ingressos
                </Link>
                <h1 className="mt-3 font-display text-display-lg text-foreground uppercase">Criar senha</h1>
                <p className="mt-2 font-body text-sm text-muted-foreground">
                    Defina uma senha para entrar na sua conta sem precisar do link por e-mail.
                </p>

                <form onSubmit={submit} className="mt-6 flex flex-col gap-4 rounded-card border border-border bg-surface p-6">
                    <FormField label="Senha" htmlFor="password" error={form.errors.password}>
                        <Input
                            id="password"
                            type="password"
                            autoComplete="new-password"
                            value={form.data.password}
                            onChange={(e) => form.setData('password', e.target.value)}
                            placeholder="Mínimo 8 caracteres"
                        />
                    </FormField>
                    <FormField label="Confirmar senha" htmlFor="password_confirmation">
                        <Input
                            id="password_confirmation"
                            type="password"
                            autoComplete="new-password"
                            value={form.data.password_confirmation}
                            onChange={(e) => form.setData('password_confirmation', e.target.value)}
                            placeholder="Repita a senha"
                        />
                    </FormField>
                    <Button type="submit" block disabled={form.processing}>
                        {form.processing ? 'Salvando…' : 'Salvar senha'}
                    </Button>
                </form>
            </div>
        </>
    );
}

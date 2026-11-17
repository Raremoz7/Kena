import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Button } from '@/components/atoms/Button';
import { Input } from '@/components/atoms/Input';
import { FormField } from '@/components/molecules/FormField';
import { AdminPage, PageHeader } from '@/components/molecules/PageHeader';
import PasswordInput from '@/components/password-input';

/** Minha conta no painel: nome e senha de quem está logado. */
export default function Account({
    account,
}: {
    account: { name: string; email: string };
}) {
    const profile = useForm({ name: account.name });
    const password = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    function saveProfile(e: FormEvent) {
        e.preventDefault();
        profile.put('/painel/conta', { preserveScroll: true });
    }

    function savePassword(e: FormEvent) {
        e.preventDefault();
        password.put('/painel/conta/senha', {
            preserveScroll: true,
            onSuccess: () => password.reset(),
        });
    }

    return (
        <>
            <Head title="Painel — Minha conta" />
            <AdminPage>
                <PageHeader
                    title="Minha conta"
                    description={`Você entra no painel com ${account.email}.`}
                />

                <div className="flex max-w-xl flex-col gap-6">
                    <form
                        onSubmit={saveProfile}
                        className="flex flex-col gap-4 rounded-card border border-border bg-surface p-4 sm:p-6"
                    >
                        <FormField
                            label="Nome"
                            htmlFor="acc-name"
                            error={profile.errors.name}
                        >
                            <Input
                                id="acc-name"
                                value={profile.data.name}
                                maxLength={120}
                                onChange={(e) =>
                                    profile.setData('name', e.target.value)
                                }
                            />
                        </FormField>
                        <div>
                            <Button type="submit" disabled={profile.processing}>
                                Salvar nome
                            </Button>
                        </div>
                    </form>

                    <form
                        onSubmit={savePassword}
                        className="flex flex-col gap-4 rounded-card border border-border bg-surface p-4 sm:p-6"
                    >
                        <FormField
                            label="Senha atual"
                            htmlFor="acc-current"
                            error={password.errors.current_password}
                        >
                            <PasswordInput
                                id="acc-current"
                                value={password.data.current_password}
                                autoComplete="current-password"
                                onChange={(e) =>
                                    password.setData(
                                        'current_password',
                                        e.target.value,
                                    )
                                }
                            />
                        </FormField>
                        <FormField
                            label="Nova senha"
                            htmlFor="acc-new"
                            helper="Mínimo de 8 caracteres, com letra e número."
                            error={password.errors.password}
                        >
                            <PasswordInput
                                id="acc-new"
                                value={password.data.password}
                                autoComplete="new-password"
                                onChange={(e) =>
                                    password.setData('password', e.target.value)
                                }
                            />
                        </FormField>
                        <FormField
                            label="Confirme a nova senha"
                            htmlFor="acc-confirm"
                        >
                            <PasswordInput
                                id="acc-confirm"
                                value={password.data.password_confirmation}
                                autoComplete="new-password"
                                onChange={(e) =>
                                    password.setData(
                                        'password_confirmation',
                                        e.target.value,
                                    )
                                }
                            />
                        </FormField>
                        <div>
                            <Button
                                type="submit"
                                disabled={password.processing}
                            >
                                Trocar senha
                            </Button>
                        </div>
                    </form>
                </div>
            </AdminPage>
        </>
    );
}

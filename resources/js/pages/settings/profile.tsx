import { Form, Head, usePage } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import { Button } from '@/components/atoms/Button';
import { Input } from '@/components/atoms/Input';
import { Spinner } from '@/components/atoms/Spinner';
import DeleteUser from '@/components/delete-user';
import Heading from '@/components/heading';
import { FormField } from '@/components/molecules/FormField';
import { send } from '@/routes/verification';
import type { Auth } from '@/types';

type PageProps = {
    auth: Auth;
};

export default function Profile(
    {
        mustVerifyEmail,
        status,
    }: {
        mustVerifyEmail: boolean;
        status?: string;
    },
) {
    const { auth } = usePage<PageProps>().props;
    // Rota autenticada (auth:web): o comprador sempre existe aqui.
    const user = auth.user!;

    return (
        <>
            <Head title="Perfil" />

            <h1 className="sr-only">Configurações do perfil</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Perfil"
                    description="Atualize seu nome e e-mail"
                />

                <Form
                    {...ProfileController.update.form()}
                    options={{
                        preserveScroll: true,
                    }}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <FormField
                                label="Nome"
                                htmlFor="name"
                                error={errors.name}
                            >
                                <Input
                                    id="name"
                                    defaultValue={user.name}
                                    name="name"
                                    required
                                    autoComplete="name"
                                    placeholder="Nome completo"
                                />
                            </FormField>

                            <FormField
                                label="E-mail"
                                htmlFor="email"
                                error={errors.email}
                            >
                                <Input
                                    id="email"
                                    type="email"
                                    defaultValue={user.email}
                                    name="email"
                                    required
                                    autoComplete="username"
                                    placeholder="voce@email.com"
                                />
                            </FormField>

                            {mustVerifyEmail &&
                                user.email_verified_at === null && (
                                    <div>
                                        <p className="-mt-4 text-sm text-muted-foreground">
                                            Seu e-mail ainda não foi
                                            verificado.{' '}
                                            <Link
                                                href={send()}
                                                as="button"
                                                className="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                                            >
                                                Clique aqui para reenviar o
                                                e-mail de verificação.
                                            </Link>
                                        </p>

                                        {status ===
                                            'verification-link-sent' && (
                                            <div className="mt-2 text-sm font-medium text-success-text">
                                                Um novo link de verificação foi
                                                enviado para o seu e-mail.
                                            </div>
                                        )}
                                    </div>
                                )}

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    data-test="update-profile-button"
                                >
                                    {processing && <Spinner />}
                                    Salvar
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>

            <DeleteUser />
        </>
    );
}


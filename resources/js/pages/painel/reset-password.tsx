import { Form, Head } from '@inertiajs/react';
import { Button } from '@/components/atoms/Button';
import { Spinner } from '@/components/atoms/Spinner';
import { FormField } from '@/components/molecules/FormField';
import PasswordInput from '@/components/password-input';

/** Nova senha do painel, a partir do link do e-mail. */
export default function PanelResetPassword({
    token,
    email,
}: {
    token: string;
    email: string;
}) {
    return (
        <>
            <Head title="Nova senha" />
            <Form
                action="/painel/redefinir-senha"
                method="post"
                transform={(data) => ({ ...data, token, email })}
                resetOnSuccess={['password', 'password_confirmation']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <div className="grid gap-6">
                        {errors.email && (
                            <p
                                role="alert"
                                className="font-body text-sm text-danger-text"
                            >
                                {errors.email}
                            </p>
                        )}
                        <FormField
                            label="Nova senha"
                            htmlFor="password"
                            helper="Mínimo de 8 caracteres, com letra e número."
                            error={errors.password}
                        >
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                autoFocus
                                autoComplete="new-password"
                            />
                        </FormField>
                        <FormField
                            label="Confirme a nova senha"
                            htmlFor="password_confirmation"
                        >
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                required
                                autoComplete="new-password"
                            />
                        </FormField>
                        <Button type="submit" block disabled={processing}>
                            {processing && <Spinner />}
                            Salvar nova senha
                        </Button>
                    </div>
                )}
            </Form>
        </>
    );
}

PanelResetPassword.layout = {
    title: 'Criar nova senha',
    description: 'Escolha a nova senha do seu acesso ao painel.',
};

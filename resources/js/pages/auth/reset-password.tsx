import { Form, Head } from '@inertiajs/react';
import { Button } from '@/components/atoms/Button';
import { Input } from '@/components/atoms/Input';
import { Spinner } from '@/components/atoms/Spinner';
import { FormField } from '@/components/molecules/FormField';
import PasswordInput from '@/components/password-input';
import { update } from '@/routes/password';

type Props = {
    token: string;
    email: string;
    passwordRules: string;
};

export default function ResetPassword({ token, email, passwordRules }: Props) {
    return (
        <>
            <Head title="Redefinir senha" />

            <Form
                {...update.form()}
                transform={(data) => ({ ...data, token, email })}
                resetOnSuccess={['password', 'password_confirmation']}
            >
                {({ processing, errors }) => (
                    <div className="grid gap-6">
                        <FormField
                            label="E-mail"
                            htmlFor="email"
                            error={errors.email}
                        >
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                autoComplete="email"
                                value={email}
                                readOnly
                            />
                        </FormField>

                        <FormField
                            label="Senha"
                            htmlFor="password"
                            error={errors.password}
                        >
                            <PasswordInput
                                id="password"
                                name="password"
                                autoComplete="new-password"
                                autoFocus
                                placeholder="Nova senha"
                                passwordrules={passwordRules}
                            />
                        </FormField>

                        <FormField
                            label="Confirmar senha"
                            htmlFor="password_confirmation"
                            error={errors.password_confirmation}
                        >
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                autoComplete="new-password"
                                placeholder="Confirmar senha"
                                passwordrules={passwordRules}
                            />
                        </FormField>

                        <Button
                            type="submit"
                            block
                            className="mt-4"
                            disabled={processing}
                            data-test="reset-password-button"
                        >
                            {processing && <Spinner />}
                            Redefinir senha
                        </Button>
                    </div>
                )}
            </Form>
        </>
    );
}

ResetPassword.layout = {
    title: 'Redefinir senha',
    description: 'Digite sua nova senha abaixo',
};

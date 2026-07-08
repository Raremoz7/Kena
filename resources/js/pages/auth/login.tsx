import { Form, Head } from '@inertiajs/react';
import { Button } from '@/components/atoms/Button';
import { Input } from '@/components/atoms/Input';
import { Spinner } from '@/components/atoms/Spinner';
import SocialAuth from '@/components/auth/social-auth';
import { FormField } from '@/components/molecules/FormField';
import PasskeyVerify from '@/components/passkey-verify';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

export default function Login({ status, canResetPassword }: Props) {
    return (
        <>
            <Head title="Entrar" />

            <PasskeyVerify />

            <div className="flex flex-col gap-6">
                <SocialAuth label="Continuar com Google" />

                <Form
                    {...store.form()}
                    resetOnSuccess={['password']}
                    className="flex flex-col gap-6"
                >
                    {({ processing, errors }) => (
                        <>
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
                                        required
                                        autoFocus
                                        tabIndex={1}
                                        autoComplete="email"
                                        placeholder="voce@email.com"
                                    />
                                </FormField>

                                <FormField
                                    label="Senha"
                                    htmlFor="password"
                                    error={errors.password}
                                    action={
                                        canResetPassword ? (
                                            <TextLink
                                                href={request()}
                                                className="text-sm"
                                                tabIndex={5}
                                            >
                                                Esqueci a senha?
                                            </TextLink>
                                        ) : undefined
                                    }
                                >
                                    <PasswordInput
                                        id="password"
                                        name="password"
                                        required
                                        tabIndex={2}
                                        autoComplete="current-password"
                                        placeholder="Sua senha"
                                    />
                                </FormField>

                                <div className="flex items-center space-x-3">
                                    <Checkbox
                                        id="remember"
                                        name="remember"
                                        tabIndex={3}
                                    />
                                    <Label htmlFor="remember">
                                        Lembrar de mim
                                    </Label>
                                </div>

                                <Button
                                    type="submit"
                                    block
                                    className="mt-2"
                                    tabIndex={4}
                                    disabled={processing}
                                    data-test="login-button"
                                >
                                    {processing && <Spinner />}
                                    Entrar
                                </Button>
                            </div>

                            <div className="text-center text-sm text-muted-foreground">
                                Não tem conta?{' '}
                                <TextLink href={register()} tabIndex={5}>
                                    Criar conta
                                </TextLink>
                            </div>
                        </>
                    )}
                </Form>
            </div>

            {status && (
                <div className="mt-4 text-center text-sm font-medium text-success">
                    {status}
                </div>
            )}
        </>
    );
}

Login.layout = {
    title: 'Bem-vindo de volta',
    description: 'Entre para garantir seu lugar',
};

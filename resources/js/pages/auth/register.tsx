import { Form, Head } from '@inertiajs/react';
import { Button } from '@/components/atoms/Button';
import { Input } from '@/components/atoms/Input';
import { Spinner } from '@/components/atoms/Spinner';
import SocialAuth from '@/components/auth/social-auth';
import MaskedInput from '@/components/masked-input';
import { FormField } from '@/components/molecules/FormField';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { maskCpf, maskPhone } from '@/lib/masks';
import { login } from '@/routes';
import { store } from '@/routes/register';

type Props = {
    passwordRules: string;
};

export default function Register({ passwordRules }: Props) {
    return (
        <>
            <Head title="Criar conta" />

            <div className="flex flex-col gap-6">
                <SocialAuth label="Continuar com Google" />

                <Form
                    {...store.form()}
                    resetOnSuccess={['password']}
                    disableWhileProcessing
                    className="flex flex-col gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-6">
                                <FormField
                                    label="Nome completo"
                                    htmlFor="name"
                                    error={errors.name}
                                >
                                    <Input
                                        id="name"
                                        type="text"
                                        name="name"
                                        required
                                        autoFocus
                                        tabIndex={1}
                                        autoComplete="name"
                                        placeholder="Seu nome"
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
                                        name="email"
                                        required
                                        tabIndex={2}
                                        autoComplete="email"
                                        placeholder="voce@email.com"
                                    />
                                </FormField>

                                <FormField
                                    label="Telefone / WhatsApp"
                                    htmlFor="phone"
                                    error={errors.phone}
                                >
                                    <MaskedInput
                                        id="phone"
                                        name="phone"
                                        type="tel"
                                        required
                                        tabIndex={3}
                                        autoComplete="tel"
                                        mask={maskPhone}
                                        placeholder="(61) 99999-9999"
                                    />
                                </FormField>

                                <FormField
                                    label="CPF"
                                    htmlFor="cpf"
                                    error={errors.cpf}
                                >
                                    <MaskedInput
                                        id="cpf"
                                        name="cpf"
                                        required
                                        tabIndex={4}
                                        mask={maskCpf}
                                        placeholder="000.000.000-00"
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
                                        required
                                        tabIndex={5}
                                        autoComplete="new-password"
                                        placeholder="Crie uma senha"
                                        passwordrules={passwordRules}
                                    />
                                </FormField>

                                <Button
                                    type="submit"
                                    block
                                    className="mt-2"
                                    tabIndex={6}
                                    data-test="register-user-button"
                                >
                                    {processing && <Spinner />}
                                    Criar conta
                                </Button>
                            </div>

                            <div className="text-center text-sm text-muted-foreground">
                                Já tem conta?{' '}
                                <TextLink href={login()} tabIndex={7}>
                                    Entrar
                                </TextLink>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

Register.layout = {
    title: 'Criar sua conta',
    description: 'Seus dados para emissão e contato',
};

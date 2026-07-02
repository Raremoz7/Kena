import { Form, Head } from '@inertiajs/react';
import SocialAuth from '@/components/auth/social-auth';
import InputError from '@/components/input-error';
import MaskedInput from '@/components/masked-input';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
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
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Nome completo</Label>
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
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="email">E-mail</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        name="email"
                                        required
                                        tabIndex={2}
                                        autoComplete="email"
                                        placeholder="voce@email.com"
                                    />
                                    <InputError message={errors.email} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="phone">
                                        Telefone / WhatsApp
                                    </Label>
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
                                    <InputError message={errors.phone} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="cpf">CPF</Label>
                                    <MaskedInput
                                        id="cpf"
                                        name="cpf"
                                        required
                                        tabIndex={4}
                                        mask={maskCpf}
                                        placeholder="000.000.000-00"
                                    />
                                    <InputError message={errors.cpf} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="password">Senha</Label>
                                    <PasswordInput
                                        id="password"
                                        name="password"
                                        required
                                        tabIndex={5}
                                        autoComplete="new-password"
                                        placeholder="Crie uma senha"
                                        passwordrules={passwordRules}
                                    />
                                    <InputError message={errors.password} />
                                </div>

                                <Button
                                    type="submit"
                                    className="mt-2 w-full"
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

import { Form, Head, Link } from '@inertiajs/react';
import { Button } from '@/components/atoms/Button';
import { Checkbox } from '@/components/atoms/Checkbox';
import { Input } from '@/components/atoms/Input';
import { Label } from '@/components/atoms/Label';
import { Spinner } from '@/components/atoms/Spinner';
import { FormField } from '@/components/molecules/FormField';
import PasswordInput from '@/components/password-input';

/**
 * Cadastro aberto de organizador: cria a organização e a conta do painel.
 * Entra depois de confirmar o e-mail; para vender, conecta o Mercado Pago.
 */
export default function OrganizerSignup() {
    return (
        <>
            <Head title="Criar conta de organizador" />

            <Form
                action="/organizador/cadastro"
                method="post"
                resetOnSuccess={['password', 'password_confirmation']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <div className="grid gap-5">
                        <FormField
                            label="Nome do organizador ou produtora"
                            htmlFor="organization_name"
                            helper="Aparece para o público na página dos seus eventos."
                            error={errors.organization_name}
                        >
                            <Input
                                id="organization_name"
                                name="organization_name"
                                required
                                autoFocus
                                maxLength={120}
                                placeholder="Ex.: Cia. Teatro Aberto"
                            />
                        </FormField>

                        <FormField
                            label="Seu nome"
                            htmlFor="name"
                            error={errors.name}
                        >
                            <Input
                                id="name"
                                name="name"
                                required
                                maxLength={120}
                                autoComplete="name"
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
                                autoComplete="email"
                                placeholder="voce@produtora.com.br"
                            />
                        </FormField>

                        <FormField
                            label="Senha"
                            htmlFor="password"
                            helper="Mínimo de 8 caracteres, com letra e número."
                            error={errors.password}
                        >
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                autoComplete="new-password"
                            />
                        </FormField>

                        <FormField
                            label="Confirme a senha"
                            htmlFor="password_confirmation"
                        >
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                required
                                autoComplete="new-password"
                            />
                        </FormField>

                        <div>
                            <div className="flex items-start gap-3">
                                <Checkbox
                                    id="accept_terms"
                                    name="accept_terms"
                                    value="1"
                                />
                                <Label
                                    htmlFor="accept_terms"
                                    className="font-body text-sm leading-snug font-normal text-muted-foreground"
                                >
                                    Li e aceito os{' '}
                                    <a
                                        href="/termos"
                                        target="_blank"
                                        rel="noreferrer"
                                        className="text-accent-text underline"
                                    >
                                        Termos de Uso
                                    </a>
                                    . Sou responsável pelos meus eventos e pelos
                                    reembolsos aos compradores.
                                </Label>
                            </div>
                            {errors.accept_terms && (
                                <p
                                    role="alert"
                                    className="mt-1.5 font-body text-xs text-danger-text"
                                >
                                    {errors.accept_terms}
                                </p>
                            )}
                        </div>

                        <Button type="submit" block disabled={processing}>
                            {processing && <Spinner />}
                            Criar conta
                        </Button>

                        <p className="text-center font-body text-sm text-muted-foreground">
                            Já tem conta?{' '}
                            <Link
                                href="/painel/login"
                                className="font-medium text-accent-text underline-offset-4 hover:underline"
                            >
                                Entrar no painel
                            </Link>
                        </p>
                    </div>
                )}
            </Form>
        </>
    );
}

OrganizerSignup.layout = {
    title: 'Venda ingressos no Kena',
    description:
        'Crie sua conta, conecte o Mercado Pago e publique seu evento.',
};

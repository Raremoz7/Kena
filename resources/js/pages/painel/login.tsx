import { Form, Head, Link } from '@inertiajs/react';
import { Button } from '@/components/atoms/Button';
import { Checkbox } from '@/components/atoms/Checkbox';
import { Input } from '@/components/atoms/Input';
import { Label } from '@/components/atoms/Label';
import { Spinner } from '@/components/atoms/Spinner';
import { FormField } from '@/components/molecules/FormField';
import PasswordInput from '@/components/password-input';

/**
 * Entrada do painel — e-mail e senha, guard próprio. Sem Google, passkey ou
 * magic link. Conta nasce pelo /painel/equipe ou, com o cadastro aberto
 * ligado, por /organizador/cadastro.
 */
export default function PainelLogin({
    signupEnabled = false,
    status = null,
}: {
    signupEnabled?: boolean;
    status?: string | null;
}) {
    return (
        <>
            <Head title="Entrar no painel" />

            <div className="flex flex-col gap-6">
                {status && (
                    <p
                        role="status"
                        className="rounded-card border border-success bg-success/5 p-3 font-body text-sm text-foreground"
                    >
                        {status}
                    </p>
                )}

                <Form
                    action="/painel/login"
                    method="post"
                    resetOnSuccess={['password']}
                    className="flex flex-col gap-6"
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
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="email"
                                    placeholder="voce@somo.tec.br"
                                />
                            </FormField>

                            <FormField
                                label="Senha"
                                htmlFor="password"
                                error={errors.password}
                                action={
                                    <Link
                                        href="/painel/esqueci-senha"
                                        className="font-body text-xs text-accent-text underline-offset-4 hover:underline"
                                    >
                                        Esqueci minha senha
                                    </Link>
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
                                <Label htmlFor="remember">Lembrar de mim</Label>
                            </div>

                            <Button
                                type="submit"
                                block
                                className="mt-2"
                                tabIndex={4}
                                disabled={processing}
                                data-test="painel-login-button"
                            >
                                {processing && <Spinner />}
                                Entrar no painel
                            </Button>
                        </div>
                    )}
                </Form>

                {signupEnabled && (
                    <p className="text-center font-body text-sm text-muted-foreground">
                        Quer vender ingressos?{' '}
                        <Link
                            href="/organizador/cadastro"
                            className="font-medium text-accent-text underline-offset-4 hover:underline"
                        >
                            Crie sua conta de organizador
                        </Link>
                    </p>
                )}
            </div>
        </>
    );
}

PainelLogin.layout = {
    title: 'Painel Kena',
    description: 'Acesso restrito à equipe',
};

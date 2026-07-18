import { Form, Head } from '@inertiajs/react';
import { Button } from '@/components/atoms/Button';
import { Checkbox } from '@/components/atoms/Checkbox';
import { Input } from '@/components/atoms/Input';
import { Label } from '@/components/atoms/Label';
import { Spinner } from '@/components/atoms/Spinner';
import { FormField } from '@/components/molecules/FormField';
import PasswordInput from '@/components/password-input';

/**
 * Entrada do painel — e-mail e senha, guard próprio. Sem Google, passkey,
 * magic link ou criar conta: conta de painel nasce pelo /painel/equipe.
 */
export default function PainelLogin() {
    return (
        <>
            <Head title="Entrar no painel" />

            <div className="flex flex-col gap-6">
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
            </div>
        </>
    );
}

PainelLogin.layout = {
    title: 'Painel Kena',
    description: 'Acesso restrito à equipe',
};

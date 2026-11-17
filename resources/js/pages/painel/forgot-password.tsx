import { Form, Head, Link } from '@inertiajs/react';
import { Button } from '@/components/atoms/Button';
import { Input } from '@/components/atoms/Input';
import { Spinner } from '@/components/atoms/Spinner';
import { FormField } from '@/components/molecules/FormField';

/** Pede o link de nova senha do painel. */
export default function PanelForgotPassword() {
    return (
        <>
            <Head title="Esqueci minha senha" />
            <Form
                action="/painel/esqueci-senha"
                method="post"
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <div className="grid gap-6">
                        <FormField
                            label="E-mail do painel"
                            htmlFor="email"
                            error={errors.email}
                        >
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                required
                                autoFocus
                                autoComplete="email"
                            />
                        </FormField>
                        <Button type="submit" block disabled={processing}>
                            {processing && <Spinner />}
                            Enviar link
                        </Button>
                        <p className="text-center font-body text-sm text-muted-foreground">
                            <Link
                                href="/painel/login"
                                className="font-medium text-accent-text underline-offset-4 hover:underline"
                            >
                                Voltar para o login
                            </Link>
                        </p>
                    </div>
                )}
            </Form>
        </>
    );
}

PanelForgotPassword.layout = {
    title: 'Esqueci minha senha',
    description: 'Enviamos um link para você criar uma nova senha.',
};

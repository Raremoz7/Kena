import { Form, Head } from '@inertiajs/react';
import { Button } from '@/components/atoms/Button';
import { Checkbox } from '@/components/atoms/Checkbox';
import { Input } from '@/components/atoms/Input';
import { Label } from '@/components/atoms/Label';
import { Spinner } from '@/components/atoms/Spinner';
import { FormField } from '@/components/molecules/FormField';
import PasswordInput from '@/components/password-input';

/** Entrada do super admin (/admin): conta própria, separada do painel. */
export default function SuperAdminLogin() {
    return (
        <>
            <Head title="Super admin" />
            <Form
                action="/admin/login"
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
                                autoComplete="email"
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
                                autoComplete="current-password"
                            />
                        </FormField>
                        <div className="flex items-center space-x-3">
                            <Checkbox id="remember" name="remember" />
                            <Label htmlFor="remember">Lembrar de mim</Label>
                        </div>
                        <Button type="submit" block disabled={processing}>
                            {processing && <Spinner />}
                            Entrar
                        </Button>
                    </div>
                )}
            </Form>
        </>
    );
}

SuperAdminLogin.layout = {
    title: 'Kena · Super admin',
    description: 'Acesso restrito à administração da plataforma',
};

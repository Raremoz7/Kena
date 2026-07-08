import { Form, Head } from '@inertiajs/react';
import {
    index as confirmOptions,
    store as confirmStore,
} from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyConfirmationController';
import { Button } from '@/components/atoms/Button';
import { Spinner } from '@/components/atoms/Spinner';
import { FormField } from '@/components/molecules/FormField';
import PasskeyVerify from '@/components/passkey-verify';
import PasswordInput from '@/components/password-input';
import { store } from '@/routes/password/confirm';

export default function ConfirmPassword() {
    return (
        <>
            <Head title="Confirmar senha" />

            <PasskeyVerify
                routes={{
                    options: confirmOptions(),
                    submit: confirmStore(),
                }}
                label="Confirmar com passkey"
                loadingLabel="Confirmando..."
                separator="Ou confirme com a senha"
            />

            <Form {...store.form()} resetOnSuccess={['password']}>
                {({ processing, errors }) => (
                    <div className="space-y-6">
                        <FormField
                            label="Senha"
                            htmlFor="password"
                            error={errors.password}
                        >
                            <PasswordInput
                                id="password"
                                name="password"
                                placeholder="Sua senha"
                                autoComplete="current-password"
                                autoFocus
                            />
                        </FormField>

                        <Button
                            block
                            disabled={processing}
                            data-test="confirm-password-button"
                        >
                            {processing && <Spinner />}
                            Confirmar senha
                        </Button>
                    </div>
                )}
            </Form>
        </>
    );
}

ConfirmPassword.layout = {
    title: 'Confirmar senha',
    description:
        'Esta é uma área segura. Confirme sua senha antes de continuar.',
};

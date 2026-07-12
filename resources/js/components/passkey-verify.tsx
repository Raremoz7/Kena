import type { UrlMethodPair } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { usePasskeyVerify } from '@laravel/passkeys/react';
import { Button } from '@/components/atoms/Button';
import { Icon } from '@/components/atoms/Icon';
import { Spinner } from '@/components/atoms/Spinner';
import InputError from '@/components/input-error';

type Props = {
    routes?: {
        options: UrlMethodPair;
        submit: UrlMethodPair;
    };
    label?: string;
    loadingLabel?: string;
    separator?: string;
};

export default function PasskeyVerify({
    routes,
    label,
    loadingLabel,
    separator,
}: Props = {}) {
    const { verify, isLoading, error, isSupported } = usePasskeyVerify({
        ...(routes && {
            routes: {
                options: routes.options.url,
                submit: routes.submit.url,
            },
        }),
        onSuccess: (response) => {
            // O fallback é a home do comprador — /dashboard é o painel do organizador.
            router.visit(response.redirect ?? '/');
        },
    });

    if (!isSupported) {
        return null;
    }

    return (
        <>
            <div className="grid gap-2">
                <Button
                    type="button"
                    variant="secondary"
                    block
                    onClick={verify}
                    disabled={isLoading}
                >
                    {isLoading ? <Spinner /> : <Icon name="lock" size={16} />}
                    {isLoading ? (loadingLabel ?? 'Autenticando…') : (label ?? 'Entrar com passkey')}
                </Button>
                {error && <InputError message={error} className="text-center" />}
            </div>

            <div className="relative my-6">
                <div className="absolute inset-0 flex items-center">
                    <div className="h-px w-full bg-border" />
                </div>
                <div className="relative flex justify-center">
                    <span className="kicker bg-surface px-2 text-muted-foreground">
                        {separator ?? 'Ou continue com e-mail'}
                    </span>
                </div>
            </div>
        </>
    );
}

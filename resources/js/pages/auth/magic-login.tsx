import { Form, Head } from '@inertiajs/react';
import { Button } from '@/components/atoms/Button';
import { Spinner } from '@/components/atoms/Spinner';

interface MagicLoginProps {
    firstName: string | null;
    /** A própria URL assinada do e-mail: o POST nela gasta o token e entra. */
    confirmUrl: string;
}

/**
 * Página do link de acesso do e-mail. Entrar exige um toque: antivírus de
 * e-mail abrem o link sozinhos e, se abrir já entrasse, o link chegava gasto.
 */
export default function MagicLogin({ firstName, confirmUrl }: MagicLoginProps) {
    return (
        <>
            <Head title="Entrar no Kena" />

            <Form action={confirmUrl} method="post">
                {({ processing }) => (
                    <div className="flex flex-col gap-4">
                        <Button block autoFocus disabled={processing}>
                            {processing && <Spinner />}
                            {firstName ? `Entrar como ${firstName}` : 'Entrar'}
                        </Button>
                        <p className="text-center font-body text-xs text-faint">
                            O link vale uma vez. Depois de entrar, seus
                            ingressos ficam em Meus ingressos.
                        </p>
                    </div>
                )}
            </Form>
        </>
    );
}

MagicLogin.layout = {
    title: 'Seus ingressos',
    description: 'Toque no botão para entrar e ver seus ingressos.',
};

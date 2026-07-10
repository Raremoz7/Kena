import { usePasskeyRegister } from '@laravel/passkeys/react';
import { useState } from 'react';
import { Button } from '@/components/atoms/Button';
import { Input } from '@/components/atoms/Input';
import InputError from '@/components/input-error';
import { FormField } from '@/components/molecules/FormField';

type Props = {
    onSuccess: () => void;
};

export default function PasskeyRegistration({ onSuccess }: Props) {
    const [name, setName] = useState(() => {
        const ua = navigator.userAgent;

        const browser = ['Chrome', 'Firefox', 'Safari', 'Edge', 'Opera'].find((browser) =>
            new RegExp(browser).test(ua),
        );

        const os = ['iPhone', 'iPad', 'Android', 'Mac', 'Windows'].find((os) =>
            new RegExp(os).test(ua),
        );

        return [browser, os].filter(Boolean).join(' no ') || '';
    });

    const [showForm, setShowForm] = useState(false);
    const { register, isLoading, error, isSupported } = usePasskeyRegister({
        onSuccess: () => {
            setName('');
            setShowForm(false);
            onSuccess();
        },
    });

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        if (!name.trim()) {
            return;
        }

        await register(name);
    };

    const handleCancel = () => {
        setShowForm(false);
        setName('');
    };

    if (!isSupported) {
        return (
            <p className="font-body text-sm text-muted-foreground">
                Este navegador não suporta passkeys.
            </p>
        );
    }

    if (!showForm) {
        return (
            <Button variant="secondary" onClick={() => setShowForm(true)}>
                Adicionar passkey
            </Button>
        );
    }

    return (
        <form
            onSubmit={handleSubmit}
            className="space-y-4 rounded-card border border-border bg-surface-2 p-4"
        >
            <FormField
                label="Nome da passkey"
                htmlFor="passkey-name"
                helper="Um nome ajuda a identificar este dispositivo depois."
            >
                <Input
                    id="passkey-name"
                    type="text"
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    placeholder="ex.: MacBook Pro, iPhone"
                    autoFocus
                />
            </FormField>

            {error && <InputError message={error} />}

            <div className="flex gap-2">
                <Button type="submit" disabled={isLoading || !name.trim()}>
                    {isLoading ? 'Registrando…' : 'Registrar passkey'}
                </Button>
                <Button type="button" variant="ghost" onClick={handleCancel}>
                    Cancelar
                </Button>
            </div>
        </form>
    );
}

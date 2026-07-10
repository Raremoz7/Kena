import { Form } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import AlertError from '@/components/alert-error';
import { Button } from '@/components/atoms/Button';
import { Icon } from '@/components/atoms/Icon';
import { Card } from '@/components/molecules/Card';
import { regenerateRecoveryCodes } from '@/routes/two-factor';

type Props = {
    recoveryCodesList: string[];
    fetchRecoveryCodes: () => Promise<void>;
    errors: string[];
};

export default function TwoFactorRecoveryCodes({
    recoveryCodesList,
    fetchRecoveryCodes,
    errors,
}: Props) {
    const [codesAreVisible, setCodesAreVisible] = useState<boolean>(false);
    const codesSectionRef = useRef<HTMLDivElement | null>(null);
    const canRegenerateCodes = recoveryCodesList.length > 0 && codesAreVisible;

    const toggleCodesVisibility = useCallback(async () => {
        if (!codesAreVisible && !recoveryCodesList.length) {
            await fetchRecoveryCodes();
        }

        setCodesAreVisible(!codesAreVisible);

        if (!codesAreVisible) {
            setTimeout(() => {
                codesSectionRef.current?.scrollIntoView({
                    behavior: 'smooth',
                    block: 'nearest',
                });
            });
        }
    }, [codesAreVisible, recoveryCodesList.length, fetchRecoveryCodes]);

    useEffect(() => {
        if (!recoveryCodesList.length) {
            fetchRecoveryCodes();
        }
    }, [recoveryCodesList.length, fetchRecoveryCodes]);

    return (
        <Card className="w-full p-6">
            <h3 className="flex items-center gap-2 font-display text-display-sm text-foreground uppercase">
                <Icon name="lock" size={18} aria-hidden="true" />
                Códigos de recuperação
            </h3>
            <p className="mt-1.5 font-body text-sm text-muted-foreground">
                Servem para recuperar o acesso caso você perca o aparelho com o autenticador.
                Guarde-os num gerenciador de senhas.
            </p>

            <div className="mt-5">
                <div className="flex flex-col gap-3 select-none sm:flex-row sm:items-center sm:justify-between">
                    <Button
                        onClick={toggleCodesVisibility}
                        className="w-fit"
                        aria-expanded={codesAreVisible}
                        aria-controls="recovery-codes-section"
                    >
                        <Icon name="eye" size={16} aria-hidden="true" />
                        {codesAreVisible ? 'Ocultar códigos' : 'Ver códigos'}
                    </Button>

                    {canRegenerateCodes && (
                        <Form
                            {...regenerateRecoveryCodes.form()}
                            options={{ preserveScroll: true }}
                            onSuccess={fetchRecoveryCodes}
                        >
                            {({ processing }) => (
                                <Button
                                    variant="secondary"
                                    type="submit"
                                    disabled={processing}
                                    aria-describedby="regenerate-warning"
                                >
                                    <Icon name="refund" size={16} aria-hidden="true" />
                                    Gerar novos
                                </Button>
                            )}
                        </Form>
                    )}
                </div>

                <div
                    id="recovery-codes-section"
                    className={`relative overflow-hidden transition-all duration-300 ${codesAreVisible ? 'h-auto opacity-100' : 'h-0 opacity-0'}`}
                    aria-hidden={!codesAreVisible}
                >
                    <div className="mt-3 space-y-3">
                        {errors?.length ? (
                            <AlertError errors={errors} />
                        ) : (
                            <>
                                <div
                                    ref={codesSectionRef}
                                    className="grid gap-1 rounded-btn bg-surface-2 p-4 font-mono text-sm text-foreground"
                                    role="list"
                                    aria-label="Códigos de recuperação"
                                >
                                    {recoveryCodesList.length ? (
                                        recoveryCodesList.map((code, index) => (
                                            <div key={index} role="listitem" className="select-text">
                                                {code}
                                            </div>
                                        ))
                                    ) : (
                                        <div className="space-y-2" aria-label="Carregando códigos">
                                            {Array.from({ length: 8 }, (_, index) => (
                                                <div
                                                    key={index}
                                                    className="h-4 animate-pulse rounded bg-muted-foreground/20"
                                                    aria-hidden="true"
                                                />
                                            ))}
                                        </div>
                                    )}
                                </div>

                                <p
                                    id="regenerate-warning"
                                    className="font-body text-xs text-muted-foreground select-none"
                                >
                                    Cada código só pode ser usado uma vez e some depois do uso. Se
                                    precisar de mais, clique em{' '}
                                    <span className="font-semibold">Gerar novos</span> acima.
                                </p>
                            </>
                        )}
                    </div>
                </div>
            </div>
        </Card>
    );
}

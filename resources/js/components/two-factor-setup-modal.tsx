import { Form } from '@inertiajs/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import AlertError from '@/components/alert-error';
import { Button } from '@/components/atoms/Button';
import { Icon } from '@/components/atoms/Icon';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/atoms/OtpInput';
import { Spinner } from '@/components/atoms/Spinner';
import InputError from '@/components/input-error';
import { Modal } from '@/components/molecules/Modal';
import { useClipboard } from '@/hooks/use-clipboard';
import { OTP_MAX_LENGTH } from '@/hooks/use-two-factor-auth';
import { confirm } from '@/routes/two-factor';

/** Selo decorativo do topo do modal (grade + linha de leitura). */
function GridScanIcon() {
    return (
        <div className="mx-auto mb-4 w-fit rounded-full border border-border bg-surface p-0.5">
            <div className="relative overflow-hidden rounded-full border border-border bg-surface-2 p-2.5">
                <div className="absolute inset-0 grid grid-cols-5 opacity-50" aria-hidden="true">
                    {Array.from({ length: 5 }, (_, i) => (
                        <div key={`col-${i + 1}`} className="border-r border-border last:border-r-0" />
                    ))}
                </div>
                <div className="absolute inset-0 grid grid-rows-5 opacity-50" aria-hidden="true">
                    {Array.from({ length: 5 }, (_, i) => (
                        <div key={`row-${i + 1}`} className="border-b border-border last:border-b-0" />
                    ))}
                </div>
                <Icon name="qr" size={24} className="relative z-20 text-foreground" />
            </div>
        </div>
    );
}

function TwoFactorSetupStep({
    qrCodeSvg,
    manualSetupKey,
    buttonText,
    onNextStep,
    errors,
}: {
    qrCodeSvg: string | null;
    manualSetupKey: string | null;
    buttonText: string;
    onNextStep: () => void;
    errors: string[];
}) {
    const [copiedText, copy] = useClipboard();
    const copied = copiedText === manualSetupKey;

    if (errors?.length) {
        return <AlertError errors={errors} />;
    }

    return (
        <div className="flex flex-col items-center space-y-5">
            <div className="mx-auto flex max-w-md overflow-hidden">
                <div className="mx-auto aspect-square w-64 rounded-card border border-border">
                    <div className="flex size-full items-center justify-center p-5">
                        {qrCodeSvg ? (
                            // O QR precisa de fundo claro para ser lido — não segue o tema.
                            <div
                                className="aspect-square w-full rounded-btn bg-white p-2 [&_svg]:size-full"
                                dangerouslySetInnerHTML={{ __html: qrCodeSvg }}
                            />
                        ) : (
                            <Spinner />
                        )}
                    </div>
                </div>
            </div>

            <Button block onClick={onNextStep}>
                {buttonText}
            </Button>

            <div className="relative flex w-full items-center justify-center">
                <div className="absolute inset-0 top-1/2 h-px w-full bg-border" />
                <span className="relative bg-surface px-2 py-1 font-body text-xs text-muted-foreground">
                    ou digite o código manualmente
                </span>
            </div>

            <div className="flex w-full items-stretch overflow-hidden rounded-input border border-border-strong">
                {!manualSetupKey ? (
                    <div className="flex size-full items-center justify-center bg-surface-2 p-3">
                        <Spinner />
                    </div>
                ) : (
                    <>
                        <input
                            type="text"
                            readOnly
                            value={manualSetupKey}
                            aria-label="Chave de configuração"
                            className="size-full bg-bg p-3 font-mono text-sm text-foreground outline-none"
                        />
                        <button
                            type="button"
                            onClick={() => copy(manualSetupKey)}
                            aria-label={copied ? 'Chave copiada' : 'Copiar chave'}
                            className="border-l border-border-strong px-3 text-muted-foreground transition-colors hover:bg-surface-2 hover:text-foreground"
                        >
                            <Icon name={copied ? 'check' : 'tag'} size={16} />
                        </button>
                    </>
                )}
            </div>
        </div>
    );
}

function TwoFactorVerificationStep({
    onClose,
    onBack,
}: {
    onClose: () => void;
    onBack: () => void;
}) {
    const [code, setCode] = useState<string>('');
    const pinInputContainerRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        setTimeout(() => {
            pinInputContainerRef.current?.querySelector('input')?.focus();
        }, 0);
    }, []);

    return (
        <Form {...confirm.form()} onSuccess={() => onClose()} resetOnError resetOnSuccess>
            {({
                processing,
                errors,
            }: {
                processing: boolean;
                errors?: { confirmTwoFactorAuthentication?: { code?: string } };
            }) => (
                <div ref={pinInputContainerRef} className="relative w-full space-y-3">
                    <div className="flex w-full flex-col items-center space-y-3 py-2">
                        <InputOTP
                            id="otp"
                            name="code"
                            maxLength={OTP_MAX_LENGTH}
                            onChange={setCode}
                            disabled={processing}
                            pattern={REGEXP_ONLY_DIGITS}
                            autoFocus
                        >
                            <InputOTPGroup>
                                {Array.from({ length: OTP_MAX_LENGTH }, (_, index) => (
                                    <InputOTPSlot key={index} index={index} />
                                ))}
                            </InputOTPGroup>
                        </InputOTP>
                        <InputError message={errors?.confirmTwoFactorAuthentication?.code} />
                    </div>

                    <div className="flex w-full gap-3">
                        <Button
                            type="button"
                            variant="secondary"
                            className="flex-1"
                            onClick={onBack}
                            disabled={processing}
                        >
                            Voltar
                        </Button>
                        <Button
                            type="submit"
                            className="flex-1"
                            disabled={processing || code.length < OTP_MAX_LENGTH}
                        >
                            Confirmar
                        </Button>
                    </div>
                </div>
            )}
        </Form>
    );
}

type Props = {
    isOpen: boolean;
    onClose: () => void;
    requiresConfirmation: boolean;
    twoFactorEnabled: boolean;
    qrCodeSvg: string | null;
    manualSetupKey: string | null;
    clearSetupData: () => void;
    fetchSetupData: () => Promise<void>;
    errors: string[];
};

export default function TwoFactorSetupModal({
    isOpen,
    onClose,
    requiresConfirmation,
    twoFactorEnabled,
    qrCodeSvg,
    manualSetupKey,
    clearSetupData,
    fetchSetupData,
    errors,
}: Props) {
    const [showVerificationStep, setShowVerificationStep] = useState<boolean>(false);

    const modalConfig = useMemo<{
        title: string;
        description: string;
        buttonText: string;
    }>(() => {
        if (twoFactorEnabled) {
            return {
                title: 'Duas etapas ativada',
                description:
                    'Pronto. Escaneie o QR ou informe a chave no seu aplicativo autenticador.',
                buttonText: 'Fechar',
            };
        }

        if (showVerificationStep) {
            return {
                title: 'Confirme o código',
                description: 'Digite os 6 dígitos gerados pelo seu aplicativo autenticador.',
                buttonText: 'Continuar',
            };
        }

        return {
            title: 'Ativar duas etapas',
            description:
                'Para concluir, escaneie o QR ou informe a chave no seu aplicativo autenticador.',
            buttonText: 'Continuar',
        };
    }, [twoFactorEnabled, showVerificationStep]);

    const resetModalState = useCallback(() => {
        setShowVerificationStep(false);
        clearSetupData();
    }, [clearSetupData]);

    const handleClose = useCallback(() => {
        resetModalState();
        onClose();
    }, [onClose, resetModalState]);

    const handleModalNextStep = useCallback(() => {
        if (requiresConfirmation) {
            setShowVerificationStep(true);

            return;
        }

        handleClose();
    }, [requiresConfirmation, handleClose]);

    const fetchSetupDataRef = useRef(fetchSetupData);

    useEffect(() => {
        fetchSetupDataRef.current = fetchSetupData;
    }, [fetchSetupData]);

    useEffect(() => {
        if (isOpen && !qrCodeSvg) {
            fetchSetupDataRef.current();
        }
    }, [isOpen, qrCodeSvg]);

    return (
        <Modal
            open={isOpen}
            onOpenChange={(open) => !open && handleClose()}
            title={modalConfig.title}
            description={modalConfig.description}
        >
            <GridScanIcon />

            {showVerificationStep ? (
                <TwoFactorVerificationStep
                    onClose={handleClose}
                    onBack={() => setShowVerificationStep(false)}
                />
            ) : (
                <TwoFactorSetupStep
                    qrCodeSvg={qrCodeSvg}
                    manualSetupKey={manualSetupKey}
                    buttonText={modalConfig.buttonText}
                    onNextStep={handleModalNextStep}
                    errors={errors}
                />
            )}
        </Modal>
    );
}

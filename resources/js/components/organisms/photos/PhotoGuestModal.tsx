import { Button } from '@/components/atoms/Button';
import { Input } from '@/components/atoms/Input';
import { Spinner } from '@/components/atoms/Spinner';
import { EmailCodeField } from '@/components/molecules/EmailCodeField';
import { EmailSuggestion } from '@/components/molecules/EmailSuggestion';
import { FormField } from '@/components/molecules/FormField';
import { Modal } from '@/components/molecules/Modal';

export interface PhotoGuest {
    name: string;
    email: string;
    cpf: string;
    code: string;
}

interface PhotoGuestModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    guest: PhotoGuest;
    onGuestChange: (guest: PhotoGuest) => void;
    errors: Record<string, string>;
    emailCodeUrl: string;
    /** Para onde voltar depois de entrar com a conta (a própria galeria). */
    loginRedirect: string;
    submitting: boolean;
    onSubmit: () => void;
}

/**
 * Compra de fotos sem conta: os mesmos dados do convidado dos ingressos
 * (nome, e-mail confirmado por código e CPF). A conta leve é criada na hora.
 */
export function PhotoGuestModal({
    open,
    onOpenChange,
    guest,
    onGuestChange,
    errors,
    emailCodeUrl,
    loginRedirect,
    submitting,
    onSubmit,
}: PhotoGuestModalProps) {
    return (
        <Modal
            open={open}
            onOpenChange={(o) => !o && !submitting && onOpenChange(false)}
            title="Quase lá"
            description="Seus dados para liberar as fotos. Sem senha: criamos seu acesso na hora."
        >
            <form
                onSubmit={(e) => {
                    e.preventDefault();

                    if (!submitting) {
                        onSubmit();
                    }
                }}
                className="flex flex-col gap-4"
            >
                {errors.photos && (
                    <p
                        role="alert"
                        className="rounded-card border border-danger/40 bg-danger/10 px-3 py-2 font-body text-sm text-foreground"
                    >
                        {errors.photos}
                    </p>
                )}
                <FormField
                    label="Nome completo"
                    htmlFor="pg-name"
                    error={errors['guest.name']}
                >
                    <Input
                        id="pg-name"
                        autoComplete="name"
                        value={guest.name}
                        onChange={(e) =>
                            onGuestChange({ ...guest, name: e.target.value })
                        }
                        placeholder="Como no documento"
                        required
                    />
                </FormField>
                <FormField
                    label="E-mail"
                    htmlFor="pg-email"
                    error={errors['guest.email']}
                    helper="As fotos e o acesso chegam por aqui."
                >
                    <Input
                        id="pg-email"
                        type="email"
                        autoComplete="email"
                        value={guest.email}
                        onChange={(e) =>
                            onGuestChange({ ...guest, email: e.target.value })
                        }
                        placeholder="nome@email.com"
                        required
                    />
                    <EmailSuggestion
                        email={guest.email}
                        onAccept={(email) => onGuestChange({ ...guest, email })}
                    />
                </FormField>
                <FormField label="CPF" htmlFor="pg-cpf" error={errors['guest.cpf']}>
                    <Input
                        id="pg-cpf"
                        value={guest.cpf}
                        onChange={(e) =>
                            onGuestChange({ ...guest, cpf: e.target.value })
                        }
                        placeholder="000.000.000-00"
                        inputMode="numeric"
                        required
                    />
                </FormField>
                <EmailCodeField
                    id="pg-code"
                    email={guest.email}
                    sendUrl={emailCodeUrl}
                    code={guest.code}
                    onCodeChange={(code) => onGuestChange({ ...guest, code })}
                    error={errors['guest.code']}
                    loginRedirect={loginRedirect}
                />
                <p className="font-body text-xs text-faint">
                    Já tem conta?{' '}
                    <a
                        href={`/login?redirect=${encodeURIComponent(loginRedirect)}`}
                        className="text-accent-text hover:underline"
                    >
                        Entrar
                    </a>
                    . Seu carrinho fica guardado.
                </p>
                <p className="font-body text-xs text-faint">
                    Ao continuar, você concorda com os{' '}
                    <a
                        href="/termos"
                        target="_blank"
                        className="text-accent-text hover:underline"
                    >
                        Termos de uso
                    </a>{' '}
                    e a{' '}
                    <a
                        href="/privacidade"
                        target="_blank"
                        className="text-accent-text hover:underline"
                    >
                        Política de privacidade
                    </a>
                    .
                </p>
                <div className="flex flex-col-reverse gap-2 *:w-full sm:flex-row sm:justify-end sm:gap-3 sm:*:w-auto">
                    <Button
                        type="button"
                        variant="secondary"
                        onClick={() => onOpenChange(false)}
                        disabled={submitting}
                    >
                        Cancelar
                    </Button>
                    <Button
                        type="submit"
                        disabled={submitting || guest.code.length !== 6}
                    >
                        {submitting ? <Spinner /> : 'Continuar para o pagamento'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}

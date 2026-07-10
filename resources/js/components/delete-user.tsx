import { Form } from '@inertiajs/react';
import { useRef, useState } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import { Button } from '@/components/atoms/Button';
import { Label } from '@/components/atoms/Label';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Modal } from '@/components/molecules/Modal';
import PasswordInput from '@/components/password-input';

export default function DeleteUser() {
    const passwordInput = useRef<HTMLInputElement>(null);
    const [open, setOpen] = useState(false);

    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title="Excluir conta"
                description="Apague sua conta e todos os dados associados a ela"
            />
            <div className="space-y-4 rounded-card border border-danger/40 bg-danger/10 p-4">
                <div className="relative space-y-0.5">
                    <p className="font-body font-medium text-danger-text">Atenção</p>
                    <p className="font-body text-sm text-muted-foreground">
                        Esta ação não pode ser desfeita.
                    </p>
                </div>

                <Button variant="danger" data-test="delete-user-button" onClick={() => setOpen(true)}>
                    Excluir conta
                </Button>

                <Modal
                    open={open}
                    onOpenChange={setOpen}
                    title="Excluir sua conta?"
                    description="Todos os seus dados serão apagados permanentemente. Digite sua senha para confirmar."
                >
                    <Form
                        {...ProfileController.destroy.form()}
                        options={{ preserveScroll: true }}
                        onError={() => passwordInput.current?.focus()}
                        resetOnSuccess
                        className="space-y-6"
                    >
                        {({ resetAndClearErrors, processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="password" className="sr-only">
                                        Senha
                                    </Label>

                                    <PasswordInput
                                        id="password"
                                        name="password"
                                        ref={passwordInput}
                                        placeholder="Sua senha"
                                        autoComplete="current-password"
                                    />

                                    <InputError message={errors.password} />
                                </div>

                                <div className="flex justify-end gap-3">
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        onClick={() => {
                                            resetAndClearErrors();
                                            setOpen(false);
                                        }}
                                    >
                                        Cancelar
                                    </Button>

                                    <Button
                                        type="submit"
                                        variant="danger"
                                        disabled={processing}
                                        data-test="confirm-delete-user-button"
                                    >
                                        Excluir conta
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                </Modal>
            </div>
        </div>
    );
}

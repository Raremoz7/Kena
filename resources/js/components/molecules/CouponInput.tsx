import { useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/atoms/Button';
import { Icon } from '@/components/atoms/Icon';
import { Input } from '@/components/atoms/Input';
import { Spinner } from '@/components/atoms/Spinner';
import { cn } from '@/lib/utils';

export interface CouponFeedback {
    tone: 'success' | 'danger';
    message: string;
}

interface CouponInputProps {
    /** Validação é server-side: o parent decide o feedback. */
    onApply: (code: string) => void;
    busy?: boolean;
    feedback?: CouponFeedback | null;
    className?: string;
}

export function CouponInput({
    onApply,
    busy = false,
    feedback = null,
    className,
}: CouponInputProps) {
    const [code, setCode] = useState('');

    function submit(e: FormEvent) {
        e.preventDefault();
        const normalized = code.trim().toUpperCase();

        if (!normalized || busy) {
            return;
        }

        onApply(normalized);
    }

    return (
        <form
            onSubmit={submit}
            className={cn('flex flex-col gap-2', className)}
        >
            <div className="flex gap-2">
                <Input
                    value={code}
                    onChange={(e) => setCode(e.target.value)}
                    placeholder="Tem um cupom?"
                    aria-label="Código do cupom"
                    className="uppercase"
                    invalid={feedback?.tone === 'danger'}
                />
                <Button
                    type="submit"
                    variant="secondary"
                    className="shrink-0"
                    disabled={busy}
                >
                    {busy ? <Spinner /> : 'Aplicar'}
                </Button>
            </div>
            {feedback && (
                <p
                    className={cn(
                        'flex items-center gap-1.5 font-body text-xs',
                        feedback.tone === 'success'
                            ? 'text-success-text'
                            : 'text-danger-text',
                    )}
                >
                    <Icon
                        name={feedback.tone === 'success' ? 'check' : 'alert'}
                        size={14}
                    />
                    {feedback.message}
                </p>
            )}
        </form>
    );
}

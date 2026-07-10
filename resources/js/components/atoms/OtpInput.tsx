import { OTPInput, OTPInputContext } from 'input-otp';
import { forwardRef, useContext } from 'react';
import type { ComponentPropsWithoutRef, ElementRef } from 'react';
import { cn } from '@/lib/utils';

/**
 * Campo de código (2FA). A lib `input-otp` cuida do teclado, colar e caret;
 * aqui só vestimos os slots com os tokens do Veludo.
 */
export const InputOTP = forwardRef<
    ElementRef<typeof OTPInput>,
    ComponentPropsWithoutRef<typeof OTPInput>
>(function InputOTP({ className, containerClassName, ...props }, ref) {
    return (
        <OTPInput
            ref={ref}
            containerClassName={cn('flex items-center gap-2 has-[:disabled]:opacity-50', containerClassName)}
            className={cn('disabled:cursor-not-allowed', className)}
            {...props}
        />
    );
});

export function InputOTPGroup({ className, ...props }: ComponentPropsWithoutRef<'div'>) {
    return <div className={cn('flex items-center gap-2', className)} {...props} />;
}

export function InputOTPSlot({
    index,
    className,
    ...props
}: ComponentPropsWithoutRef<'div'> & { index: number }) {
    const { slots } = useContext(OTPInputContext);
    const { char, hasFakeCaret, isActive } = slots[index];

    return (
        <div
            className={cn(
                'relative flex size-11 items-center justify-center rounded-input border border-border-strong',
                'bg-bg font-display text-lg text-foreground transition-colors',
                isActive && 'z-10 border-accent ring-[3px] ring-accent/20',
                className,
            )}
            {...props}
        >
            {char}
            {hasFakeCaret && (
                <span className="pointer-events-none absolute inset-0 flex items-center justify-center">
                    <span className="h-5 w-px animate-pulse bg-foreground" />
                </span>
            )}
        </div>
    );
}

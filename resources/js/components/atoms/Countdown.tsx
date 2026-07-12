import { Icon } from '@/components/atoms/Icon';
import { cn } from '@/lib/utils';
import { useCountdown } from '@/lib/veludo/useCountdown';

interface CountdownProps {
    expiresAt: string;
    onExpire?: () => void;
    className?: string;
    /** Mostra o ícone de relógio antes do tempo. */
    withIcon?: boolean;
    label?: string;
}

/**
 * Contador regressivo da reserva. Âmbar e pulsando quando faltam menos de 2 min.
 */
export function Countdown({ expiresAt, onExpire, className, withIcon = true, label }: CountdownProps) {
    const { minutes, seconds, total, expired } = useCountdown(expiresAt, onExpire);
    const urgent = total > 0 && total < 120;

    return (
        <span
            role="timer"
            suppressHydrationWarning
            aria-live={urgent ? 'assertive' : 'off'}
            className={cn(
                'inline-flex items-center gap-1.5 font-body text-sm font-semibold tabular',
                expired ? 'text-danger-text' : urgent ? 'text-warning-text' : 'text-muted-foreground',
                urgent && !expired && 'motion-safe:animate-pulse',
                className,
            )}
        >
            {withIcon && <Icon name="clock" size={15} className={cn(urgent && 'text-warning')} />}
            {label && <span className="text-faint font-normal">{label}</span>}
            {expired ? 'Expirada' : `${minutes}:${String(seconds).padStart(2, '0')}`}
        </span>
    );
}

import { useEffect, useRef, useState } from 'react';

export interface Countdown {
    total: number; // segundos restantes
    minutes: number;
    seconds: number;
    expired: boolean;
}

function remaining(target: number): number {
    return Math.max(0, Math.round((target - Date.now()) / 1000));
}

/**
 * Conta regressiva até `expiresAt` (ISO). Atualiza a cada segundo e dispara
 * `onExpire` uma única vez ao zerar.
 */
export function useCountdown(expiresAt: string | null | undefined, onExpire?: () => void): Countdown {
    const target = expiresAt ? new Date(expiresAt).getTime() : 0;
    const [total, setTotal] = useState(() => (expiresAt ? remaining(target) : 0));
    const firedRef = useRef(false);
    const onExpireRef = useRef(onExpire);
    onExpireRef.current = onExpire;

    useEffect(() => {
        if (!expiresAt) {
return;
}

        firedRef.current = false;
        setTotal(remaining(target));

        const id = window.setInterval(() => {
            const left = remaining(target);
            setTotal(left);

            if (left <= 0 && !firedRef.current) {
                firedRef.current = true;
                onExpireRef.current?.();
                window.clearInterval(id);
            }
        }, 1000);

        return () => window.clearInterval(id);
    }, [expiresAt, target]);

    return {
        total,
        minutes: Math.floor(total / 60),
        seconds: total % 60,
        expired: total <= 0,
    };
}

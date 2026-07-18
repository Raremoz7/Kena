import { usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { veludoToast } from '@/lib/veludo/toast';

interface FlashProps {
    flash?: {
        success?: string | null;
        warning?: string | null;
        error?: string | null;
    };
    [key: string]: unknown;
}

/**
 * Converte as mensagens flash do servidor (props compartilhados) em toasts do
 * design system. Evita repetir o mesmo flash em re-renders com um ref.
 */
export function useFlashToasts(): void {
    const { flash } = usePage<FlashProps>().props;
    const last = useRef<string>('');

    useEffect(() => {
        if (!flash) {
            return;
        }

        const key = `${flash.success ?? ''}|${flash.warning ?? ''}|${flash.error ?? ''}`;

        if (key === '|' || key === last.current) {
            return;
        }

        last.current = key;

        if (flash.success) {
            veludoToast.success(flash.success);
        }

        if (flash.warning) {
            veludoToast.warning(flash.warning);
        }

        if (flash.error) {
            veludoToast.error(flash.error);
        }
    }, [flash]);
}

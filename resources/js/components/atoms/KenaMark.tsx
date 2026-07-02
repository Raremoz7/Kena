import { cn } from '@/lib/utils';

/** Marca Kena renderizada como máscara vinho sobre /kena-mark.svg. */
export function KenaMark({ className }: { className?: string }) {
    return (
        <span
            aria-hidden="true"
            className={cn('bg-accent', className)}
            style={{
                maskImage: 'url(/kena-mark.svg)',
                WebkitMaskImage: 'url(/kena-mark.svg)',
                maskRepeat: 'no-repeat',
                WebkitMaskRepeat: 'no-repeat',
                maskPosition: 'center',
                WebkitMaskPosition: 'center',
                maskSize: 'contain',
                WebkitMaskSize: 'contain',
            }}
        />
    );
}

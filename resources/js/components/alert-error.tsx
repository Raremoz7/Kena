import { Icon } from '@/components/atoms/Icon';

/** Caixa de erro de formulário/fluxo. Um só padrão de uso — não vira átomo. */
export default function AlertError({
    errors,
    title,
}: {
    errors: string[];
    title?: string;
}) {
    return (
        <div
            role="alert"
            className="flex items-start gap-3 rounded-card border border-danger/40 bg-danger/10 p-4"
        >
            <Icon name="alert" size={18} className="mt-0.5 shrink-0 text-danger-text" />
            <div className="min-w-0">
                <p className="font-body text-sm font-semibold text-foreground">
                    {title || 'Algo deu errado.'}
                </p>
                <ul className="mt-1 list-inside list-disc font-body text-sm text-muted-foreground">
                    {Array.from(new Set(errors)).map((error, index) => (
                        <li key={index}>{error}</li>
                    ))}
                </ul>
            </div>
        </div>
    );
}

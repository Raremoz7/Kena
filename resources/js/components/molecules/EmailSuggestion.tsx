import { emailSuggestion } from '@/lib/email-suggestion';

/** "Você quis dizer ana@gmail.com?" embaixo do campo; um toque corrige. */
export function EmailSuggestion({
    email,
    onAccept,
}: {
    email: string;
    onAccept: (email: string) => void;
}) {
    const suggestion = emailSuggestion(email);

    if (suggestion === null) {
        return null;
    }

    return (
        <p role="status" className="font-body text-xs text-warning-text">
            Você quis dizer{' '}
            <button
                type="button"
                onClick={() => onAccept(suggestion)}
                className="font-semibold underline underline-offset-2 hover:text-foreground"
            >
                {suggestion}
            </button>
            ?
        </p>
    );
}

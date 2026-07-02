import GoogleIcon from '@/components/icons/google-icon';

export default function SocialAuth({ label }: { label: string }) {
    return (
        <div className="flex flex-col gap-6">
            <a
                href="/auth/google/redirect"
                className="inline-flex h-10 w-full items-center justify-center gap-2 rounded-btn bg-foreground text-sm font-medium text-background transition-opacity hover:opacity-90"
            >
                <GoogleIcon className="size-4" />
                {label}
            </a>
            <div className="flex items-center gap-3">
                <span className="h-px flex-1 bg-border" />
                <span className="text-xs text-muted-foreground">ou</span>
                <span className="h-px flex-1 bg-border" />
            </div>
        </div>
    );
}

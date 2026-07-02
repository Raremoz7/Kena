import { cn } from '@/lib/utils';

interface AvatarProps {
    name: string;
    size?: number;
    className?: string;
}

function initials(name: string): string {
    const parts = name.trim().split(/\s+/);

    if (parts.length === 1) {
return parts[0].slice(0, 2).toUpperCase();
}

    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}

export function Avatar({ name, size = 36, className }: AvatarProps) {
    return (
        <span
            aria-hidden="true"
            style={{ width: size, height: size }}
            className={cn(
                'inline-flex items-center justify-center rounded-full bg-surface-2 font-body',
                'text-xs font-semibold text-muted-foreground select-none',
                className,
            )}
        >
            {initials(name)}
        </span>
    );
}

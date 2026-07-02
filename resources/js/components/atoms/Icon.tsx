import type { ReactElement, SVGProps } from 'react';
import { cn } from '@/lib/utils';

/**
 * Set de ícones curado do Veludo — SVG de linha fina, currentColor.
 * Sem Lucide por reflexo, sem emoji. Stroke 1.5 por padrão.
 */
export type IconName =
    | 'check'
    | 'close'
    | 'alert'
    | 'lock'
    | 'wheelchair'
    | 'clock'
    | 'calendar'
    | 'map-pin'
    | 'ticket'
    | 'user'
    | 'qr'
    | 'chevron-right'
    | 'chevron-left'
    | 'arrow-right'
    | 'credit-card'
    | 'pix'
    | 'tag'
    | 'shield'
    | 'transfer'
    | 'refund'
    | 'search'
    | 'menu'
    | 'info'
    | 'minus'
    | 'plus'
    | 'maximize'
    | 'home'
    | 'agenda'
    | 'sparkle'
    | 'trash'
    | 'eye'
    | 'image';

const paths: Record<IconName, ReactElement> = {
    check: <path d="M4 12.5l5 5 11-11" />,
    image: (
        <>
            <rect x="3" y="5" width="18" height="14" rx="2" />
            <circle cx="8.5" cy="10" r="1.5" />
            <path d="M21 16l-5-5-4 4-2-2-7 7" />
        </>
    ),
    close: <path d="M5 5l14 14M19 5L5 19" />,
    alert: (
        <>
            <circle cx="12" cy="12" r="9" />
            <path d="M12 7.5v6" />
            <circle cx="12" cy="16.5" r=".6" fill="currentColor" stroke="none" />
        </>
    ),
    lock: (
        <>
            <rect x="4.5" y="10" width="15" height="10" rx="2" />
            <path d="M7.5 10V7a4.5 4.5 0 019 0v3" />
        </>
    ),
    wheelchair: (
        <>
            <circle cx="11" cy="4" r="1.6" fill="currentColor" stroke="none" />
            <path d="M11 6v5h4l2 5" />
            <circle cx="8.5" cy="16" r="4.5" />
            <path d="M15 11h-4" />
        </>
    ),
    clock: (
        <>
            <circle cx="12" cy="12" r="9" />
            <path d="M12 7v5.2l3.4 2" />
        </>
    ),
    calendar: (
        <>
            <rect x="4" y="5.5" width="16" height="15" rx="2.5" />
            <path d="M4 10h16M8.5 3.5v4M15.5 3.5v4" />
        </>
    ),
    'map-pin': (
        <>
            <path d="M12 21s7-5.5 7-11a7 7 0 10-14 0c0 5.5 7 11 7 11z" />
            <circle cx="12" cy="10" r="2.6" />
        </>
    ),
    ticket: (
        <>
            <path d="M3 8.5a2 2 0 012-2h14a2 2 0 012 2V11a2 2 0 000 2v2.5a2 2 0 01-2 2H5a2 2 0 01-2-2V13a2 2 0 000-2V8.5z" />
            <path d="M14.5 6.5v11" strokeDasharray="2 2.4" />
        </>
    ),
    user: (
        <>
            <circle cx="12" cy="8" r="3.6" />
            <path d="M5 20a7 7 0 0114 0" />
        </>
    ),
    qr: (
        <>
            <rect x="4" y="4" width="6" height="6" rx="1" />
            <rect x="14" y="4" width="6" height="6" rx="1" />
            <rect x="4" y="14" width="6" height="6" rx="1" />
            <path d="M14 14h3v3M20 14v.01M17 20h3v-3M14 20v.01" />
        </>
    ),
    'chevron-right': <path d="M9 5l7 7-7 7" />,
    'chevron-left': <path d="M15 5l-7 7 7 7" />,
    'arrow-right': <path d="M4 12h15m-6-7l7 7-7 7" />,
    'credit-card': (
        <>
            <rect x="3" y="5.5" width="18" height="13" rx="2.5" />
            <path d="M3 10h18M7 15h3" />
        </>
    ),
    pix: <path d="M12 3l3.4 3.4a3 3 0 010 4.2L12 14l-3.4-3.4a3 3 0 010-4.2L12 3zM5.5 9.5L3 12l2.5 2.5M18.5 9.5L21 12l-2.5 2.5M12 14l3.4 3.4a3 3 0 01-4.8 0L12 14z" />,
    tag: (
        <>
            <path d="M4 12.7V5a1 1 0 011-1h7.7a1 1 0 01.7.3l6 6a1 1 0 010 1.4l-6.3 6.3a1 1 0 01-1.4 0l-6-6a1 1 0 01-.3-.7z" />
            <circle cx="8.5" cy="8.5" r="1.3" fill="currentColor" stroke="none" />
        </>
    ),
    shield: (
        <>
            <path d="M12 3l7 2.5v5.5c0 4.5-3 8.5-7 10-4-1.5-7-5.5-7-10V5.5L12 3z" />
            <path d="M9 12l2 2 4-4.5" />
        </>
    ),
    transfer: <path d="M4 8h12m-4-4l4 4-4 4M20 16H8m4 4l-4-4 4-4" />,
    refund: (
        <>
            <path d="M5 12a7 7 0 117 7H8" />
            <path d="M8 15l-3 4-1-4.5" fill="none" />
            <path d="M4 9.5l1.5 3 3-1" />
        </>
    ),
    search: (
        <>
            <circle cx="11" cy="11" r="6.5" />
            <path d="M16 16l4.5 4.5" />
        </>
    ),
    menu: <path d="M4 7h16M4 12h16M4 17h16" />,
    info: (
        <>
            <circle cx="12" cy="12" r="9" />
            <path d="M12 11v5.5" />
            <circle cx="12" cy="7.8" r=".7" fill="currentColor" stroke="none" />
        </>
    ),
    minus: <path d="M5 12h14" />,
    plus: <path d="M12 5v14M5 12h14" />,
    maximize: <path d="M4 9V5a1 1 0 011-1h4M15 4h4a1 1 0 011 1v4M20 15v4a1 1 0 01-1 1h-4M9 20H5a1 1 0 01-1-1v-4" />,
    home: <path d="M4 11l8-7 8 7M6 9.5V20h12V9.5" />,
    agenda: (
        <>
            <rect x="4" y="5" width="16" height="15" rx="2.5" />
            <path d="M4 9.5h16M8 3.5v3M16 3.5v3M8 13h4" />
        </>
    ),
    sparkle: <path d="M12 3l1.8 5.4L19 10l-5.2 1.6L12 17l-1.8-5.4L5 10l5.2-1.6L12 3z" />,
    trash: (
        <>
            <path d="M4.5 7h15M9 7V5.5A1.5 1.5 0 0110.5 4h3A1.5 1.5 0 0115 5.5V7" />
            <path d="M6 7l1 12.5A1.5 1.5 0 008.5 21h7a1.5 1.5 0 001.5-1.5L18 7" />
        </>
    ),
    eye: (
        <>
            <path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z" />
            <circle cx="12" cy="12" r="2.8" />
        </>
    ),
};

interface IconProps extends Omit<SVGProps<SVGSVGElement>, 'name'> {
    name: IconName;
    size?: number;
}

export function Icon({ name, size = 18, className, strokeWidth = 1.5, ...props }: IconProps) {
    return (
        <svg
            width={size}
            height={size}
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={strokeWidth}
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
            className={cn('shrink-0', className)}
            {...props}
        >
            {paths[name]}
        </svg>
    );
}

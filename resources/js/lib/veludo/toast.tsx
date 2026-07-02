import { toast as sonner } from 'sonner';
import { Icon  } from '@/components/atoms/Icon';
import type {IconName} from '@/components/atoms/Icon';

type Tone = 'success' | 'warning' | 'danger' | 'info';

const toneMap: Record<Tone, { icon: IconName; color: string }> = {
    success: { icon: 'check', color: 'var(--success)' },
    warning: { icon: 'alert', color: 'var(--warning)' },
    danger: { icon: 'close', color: 'var(--destructive)' },
    info: { icon: 'info', color: 'var(--info)' },
};

/**
 * Toast Veludo — só a borda esquerda (3px) carrega a cor; sem fundo tintado.
 * Renderizado via sonner.custom para controle total do visual.
 */
function notify(tone: Tone, title: string, description?: string) {
    const { icon, color } = toneMap[tone];

    return sonner.custom(
        (id) => (
            <div
                role="status"
                className="flex w-[min(360px,90vw)] items-start gap-3 rounded-btn border border-border bg-surface-2 py-3.5 pr-3 pl-4 shadow-xl"
                style={{ borderLeft: `3px solid ${color}` }}
            >
                <span style={{ color }} className="mt-0.5">
                    <Icon name={icon} size={18} />
                </span>
                <div className="min-w-0 flex-1">
                    <p className="font-body text-[13px] font-semibold text-foreground">{title}</p>
                    {description && (
                        <p className="mt-0.5 font-body text-xs text-muted-foreground">{description}</p>
                    )}
                </div>
                <button
                    type="button"
                    onClick={() => sonner.dismiss(id)}
                    aria-label="Fechar"
                    className="text-faint transition-colors hover:text-foreground"
                >
                    <Icon name="close" size={14} />
                </button>
            </div>
        ),
        { duration: 5000 },
    );
}

export const veludoToast = {
    success: (title: string, description?: string) => notify('success', title, description),
    warning: (title: string, description?: string) => notify('warning', title, description),
    error: (title: string, description?: string) => notify('danger', title, description),
    info: (title: string, description?: string) => notify('info', title, description),
};

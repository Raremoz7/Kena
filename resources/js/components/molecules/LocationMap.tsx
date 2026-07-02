import { motion } from 'framer-motion';
import { useState } from 'react';
import { Icon } from '@/components/atoms/Icon';
import { cn } from '@/lib/utils';

interface LocationMapProps {
    name: string;
    city: string;
    address?: string;
    /** Termo de busca usado no Google Maps. */
    query: string;
    className?: string;
}

/**
 * Card de local com Google Maps. O mapa fica montado e carregando desde que a
 * página abre (pré-carregado), apenas escondido na altura zero — ao expandir já
 * aparece pronto. Tudo nos tokens do design system.
 */
export function LocationMap({ name, city, address, query, className }: LocationMapProps) {
    const [open, setOpen] = useState(false);

    const embed = `https://www.google.com/maps?q=${encodeURIComponent(query)}&output=embed`;
    const external = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(query)}`;

    return (
        <div className={cn('overflow-hidden rounded-card border border-border bg-surface', className)}>
            <button
                type="button"
                onClick={() => setOpen((o) => !o)}
                aria-expanded={open}
                className="group relative flex w-full items-center gap-3 p-4 text-left outline-none focus-visible:ring-2 focus-visible:ring-accent"
            >
                {/* fundo de grade (estética de mapa) */}
                <span
                    aria-hidden="true"
                    className="pointer-events-none absolute inset-0 opacity-[0.06] transition-opacity group-hover:opacity-[0.1]"
                    style={{
                        backgroundImage:
                            'linear-gradient(var(--faint) 1px, transparent 1px), linear-gradient(90deg, var(--faint) 1px, transparent 1px)',
                        backgroundSize: '18px 18px',
                    }}
                />
                <span className="relative flex size-10 shrink-0 items-center justify-center rounded-btn bg-accent text-accent-fg">
                    <Icon name="map-pin" size={20} />
                </span>
                <span className="relative min-w-0 flex-1">
                    <span className="kicker block text-faint">Local</span>
                    <span className="mt-1 block truncate font-body text-sm font-medium text-foreground">
                        {name}, {city}
                    </span>
                </span>
                <span className="relative flex shrink-0 items-center gap-1 font-body text-xs text-muted-foreground">
                    {open ? 'Recolher' : 'Ver no mapa'}
                    <Icon
                        name="chevron-right"
                        size={15}
                        className={cn('transition-transform duration-300', open ? '-rotate-90' : 'rotate-90')}
                    />
                </span>
            </button>

            {/* Sempre montado: o iframe carrega no load da página; só a altura anima. */}
            <motion.div
                initial={false}
                animate={{ height: open ? 'auto' : 0, opacity: open ? 1 : 0 }}
                transition={{ duration: 0.35, ease: [0.22, 1, 0.36, 1] }}
                className="overflow-hidden"
                inert={!open}
            >
                <div className="px-4 pb-4">
                    <div className="overflow-hidden rounded-btn border border-border">
                        <iframe
                            title={`Mapa — ${name}`}
                            src={embed}
                            width="100%"
                            height="260"
                            loading="eager"
                            referrerPolicy="no-referrer-when-downgrade"
                            style={{ border: 0, display: 'block', filter: 'grayscale(0.25) contrast(1.05)' }}
                        />
                    </div>
                    {address && <p className="mt-3 font-body text-xs text-muted-foreground">{address}</p>}
                    <a
                        href={external}
                        target="_blank"
                        rel="noreferrer"
                        className="mt-2 inline-flex items-center gap-1.5 font-body text-xs font-semibold text-accent hover:underline"
                    >
                        Abrir no Google Maps
                        <Icon name="arrow-right" size={14} />
                    </a>
                </div>
            </motion.div>
        </div>
    );
}

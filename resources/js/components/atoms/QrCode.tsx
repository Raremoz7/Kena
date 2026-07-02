import QRCodeStyling from 'qr-code-styling';
import { useEffect, useRef } from 'react';
import { cn } from '@/lib/utils';

interface QrCodeProps {
    value: string;
    size?: number;
    /** Moldura tracejada de "selo de ingresso" ao redor do QR. */
    frame?: boolean;
    className?: string;
}

const INK = '#3a2a24';
const CREAM = '#F4EFE7';

/**
 * QR no estilo Kena: dots com cantos arredondados (não círculos), marcadores
 * de canto na mesma tinta (sem destaque de cor), fundo creme e o monograma
 * Kena sobreposto no centro. Correção de erro alta (H) compensa a área
 * coberta pelo logo.
 */
export function QrCode({
    value,
    size = 72,
    frame = false,
    className,
}: QrCodeProps) {
    const containerRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const container = containerRef.current;

        if (!container) {
            return;
        }

        const qr = new QRCodeStyling({
            width: size,
            height: size,
            type: 'svg',
            data: value,
            margin: 4,
            qrOptions: { errorCorrectionLevel: 'H' },
            dotsOptions: { type: 'rounded', color: INK },
            cornersSquareOptions: { type: 'extra-rounded', color: INK },
            cornersDotOptions: { type: 'dot', color: INK },
            backgroundOptions: { color: CREAM },
            image: '/kena-mark-vinho.svg',
            imageOptions: {
                crossOrigin: 'anonymous',
                margin: 4,
                imageSize: 0.3,
                hideBackgroundDots: true,
            },
        });

        container.innerHTML = '';
        qr.append(container);

        return () => {
            container.innerHTML = '';
        };
    }, [value, size]);

    return (
        <div
            className={cn(
                'relative flex items-center justify-center rounded-btn p-2',
                frame && 'border-2 border-dashed border-accent/30',
                className,
            )}
            style={{ background: CREAM }}
            aria-label="QR code do ingresso"
        >
            <div ref={containerRef} />
        </div>
    );
}

import { Link } from '@inertiajs/react';
import type { CSSProperties, PropsWithChildren } from 'react';
import { Card } from '@/components/molecules/Card';
import { home } from '@/routes';

const MARK: CSSProperties = {
    maskImage: 'url(/kena-mark.svg)',
    WebkitMaskImage: 'url(/kena-mark.svg)',
    maskRepeat: 'no-repeat',
    WebkitMaskRepeat: 'no-repeat',
    maskPosition: 'center',
    WebkitMaskPosition: 'center',
    maskSize: 'contain',
    WebkitMaskSize: 'contain',
};

export default function AuthCardLayout({
    children,
    title,
    description,
}: PropsWithChildren<{
    name?: string;
    title?: string;
    description?: string;
}>) {
    return (
        <div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-bg p-6 md:p-10">
            <div className="flex w-full max-w-md flex-col gap-6">
                <Link href={home()} className="self-center" aria-label="Kena — início">
                    <span aria-hidden="true" className="block size-10 bg-accent" style={MARK} />
                </Link>

                <Card className="p-8">
                    <div className="text-center">
                        {title && (
                            <h1 className="font-display text-display-sm text-foreground uppercase">
                                {title}
                            </h1>
                        )}
                        {description && (
                            <p className="mt-1.5 font-body text-sm text-muted-foreground">
                                {description}
                            </p>
                        )}
                    </div>
                    <div className="mt-8">{children}</div>
                </Card>
            </div>
        </div>
    );
}

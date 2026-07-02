import { Link } from '@inertiajs/react';
import type { CSSProperties, PropsWithChildren } from 'react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
        <div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
            <div className="flex w-full max-w-md flex-col gap-6">
                <Link
                    href={home()}
                    className="self-center"
                    aria-label="Kena — início"
                >
                    <span
                        aria-hidden="true"
                        className="block size-10 bg-accent"
                        style={MARK}
                    />
                </Link>

                <Card className="rounded-card border-border bg-card">
                    <CardHeader className="px-8 pt-8 pb-0 text-center">
                        <CardTitle className="font-display text-2xl">
                            {title}
                        </CardTitle>
                        <CardDescription className="text-muted-foreground">
                            {description}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="px-8 py-8">{children}</CardContent>
                </Card>
            </div>
        </div>
    );
}

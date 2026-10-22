import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

/** Props comuns de Ajuda, Termos e Privacidade (LegalController). */
export interface LegalPageProps {
    company: { name: string; document: string | null };
    support: { email: string | null; whatsapp: string | null };
    policy: {
        holdMinutes: number;
        pixMinutes: number;
        refundDeadlineHours: number;
        maxTickets: number | null;
    };
    updatedAt: string;
}

export function whatsappUrl(number: string): string {
    return `https://wa.me/${number}`;
}

/** "5561999998888" → "+55 61 99999-8888" (só para exibir). */
export function formatWhatsapp(number: string): string {
    const m = number.match(/^(\d{2})(\d{2})(\d{4,5})(\d{4})$/);

    return m ? `+${m[1]} ${m[2]} ${m[3]}-${m[4]}` : `+${number}`;
}

/** Linha de contato reaproveitada nos três textos. */
export function SupportContact({
    support,
}: {
    support: LegalPageProps['support'];
}) {
    if (!support.email && !support.whatsapp) {
        return <>pelos canais informados na página do evento</>;
    }

    return (
        <>
            {support.email && (
                <a
                    href={`mailto:${support.email}`}
                    className="text-foreground underline underline-offset-2"
                >
                    {support.email}
                </a>
            )}
            {support.email && support.whatsapp && ' ou '}
            {support.whatsapp && (
                <a
                    href={whatsappUrl(support.whatsapp)}
                    target="_blank"
                    rel="noreferrer"
                    className="text-foreground underline underline-offset-2"
                >
                    WhatsApp {formatWhatsapp(support.whatsapp)}
                </a>
            )}
        </>
    );
}

/** Casca de página de texto: título, data de atualização e seções. */
export function LegalShell({
    title,
    intro,
    updatedAt,
    children,
}: {
    title: string;
    intro?: ReactNode;
    updatedAt?: string;
    children: ReactNode;
}) {
    return (
        <>
            <Head title={title} />
            <article className="mx-auto max-w-3xl px-4 py-10 sm:px-6 sm:py-12">
                <h1 className="font-display text-display-lg text-foreground uppercase">
                    {title}
                </h1>
                {updatedAt && (
                    <p className="mt-2 font-body text-xs text-faint">
                        Atualizado em {updatedAt}
                    </p>
                )}
                {intro && (
                    <div className="mt-4 font-body text-[15px] leading-relaxed text-muted-foreground">
                        {intro}
                    </div>
                )}
                <div className="mt-8 flex flex-col gap-8">{children}</div>
                <nav className="mt-12 flex flex-wrap gap-x-6 gap-y-2 border-t border-border pt-6 font-body text-sm">
                    <Link
                        href="/ajuda"
                        className="text-muted-foreground hover:text-foreground"
                    >
                        Ajuda
                    </Link>
                    <Link
                        href="/termos"
                        className="text-muted-foreground hover:text-foreground"
                    >
                        Termos de uso
                    </Link>
                    <Link
                        href="/privacidade"
                        className="text-muted-foreground hover:text-foreground"
                    >
                        Política de privacidade
                    </Link>
                </nav>
            </article>
        </>
    );
}

export function LegalSection({
    title,
    id,
    children,
}: {
    title: string;
    id?: string;
    children: ReactNode;
}) {
    return (
        <section id={id} className="scroll-mt-24">
            <h2 className="font-display text-xl text-foreground uppercase">
                {title}
            </h2>
            <div className="mt-3 flex flex-col gap-3 font-body text-[15px] leading-relaxed text-muted-foreground [&_li]:ml-5 [&_li]:list-disc">
                {children}
            </div>
        </section>
    );
}

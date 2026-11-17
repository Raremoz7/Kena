import { Head, Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Icon } from '@/components/atoms/Icon';
import type { IconName } from '@/components/atoms/Icon';
import { KenaMark } from '@/components/atoms/KenaMark';
import { SkipLink } from '@/components/atoms/SkipLink';
import { isNavActive } from '@/components/organisms/AdminSidebar';
import { cn } from '@/lib/utils';
import { useFlashToasts } from '@/lib/veludo/use-flash-toasts';

const nav: { label: string; href: string; icon: IconName }[] = [
    { label: 'Visão geral', href: '/admin', icon: 'home' },
    { label: 'Organizações', href: '/admin/organizacoes', icon: 'users' },
    { label: 'Eventos', href: '/admin/eventos', icon: 'ticket' },
    { label: 'Pedidos', href: '/admin/pedidos', icon: 'receipt' },
    { label: 'Configurações', href: '/admin/config', icon: 'settings' },
];

/** Área do super admin (/admin): todos os organizadores, separada do painel. */
export default function SuperAdminLayout({
    children,
}: {
    children: ReactNode;
}) {
    useFlashToasts();
    const { url, props } = usePage();
    const admin = props.auth?.superAdmin;
    const active = (href: string) =>
        href === '/admin'
            ? url.split('?')[0] === '/admin'
            : isNavActive(url, href);

    return (
        <div className="flex min-h-dvh flex-col bg-bg md:flex-row">
            <Head>
                <meta name="robots" content="noindex" />
            </Head>
            <SkipLink />
            <aside className="border-b border-border bg-sidebar md:sticky md:top-0 md:flex md:h-dvh md:w-60 md:shrink-0 md:flex-col md:border-r md:border-b-0">
                <div className="flex h-14 items-center gap-2.5 px-4 md:h-16 md:border-b md:border-border md:px-5">
                    <KenaMark className="size-7" />
                    <span className="kicker text-faint">Super admin</span>
                </div>
                <nav
                    aria-label="Super admin"
                    className="flex gap-1 overflow-x-auto px-3 pb-3 md:flex-1 md:flex-col md:gap-0.5 md:overflow-visible md:p-3"
                >
                    {nav.map((item) => (
                        <Link
                            key={item.href}
                            href={item.href}
                            aria-current={
                                active(item.href) ? 'page' : undefined
                            }
                            className={cn(
                                'flex shrink-0 items-center gap-3 rounded-btn px-3 py-2 font-body text-sm transition-colors',
                                active(item.href)
                                    ? 'bg-surface-2 font-medium text-foreground'
                                    : 'text-muted-foreground hover:bg-surface-2 hover:text-foreground',
                            )}
                        >
                            <Icon name={item.icon} size={18} />
                            {item.label}
                        </Link>
                    ))}
                </nav>
                {admin && (
                    <div className="hidden items-center gap-2 border-t border-border p-3 md:flex">
                        <div className="min-w-0 flex-1">
                            <p className="truncate font-body text-sm font-medium text-foreground">
                                {admin.name}
                            </p>
                            <p className="truncate font-body text-xs text-faint">
                                {admin.email}
                            </p>
                        </div>
                        <Link
                            href="/admin/logout"
                            method="post"
                            as="button"
                            aria-label="Sair do admin"
                            title="Sair do admin"
                            className="shrink-0 rounded-btn p-2 text-faint hover:bg-surface-2 hover:text-foreground"
                        >
                            <Icon name="logout" size={18} />
                        </Link>
                    </div>
                )}
            </aside>
            <main
                id="conteudo"
                tabIndex={-1}
                className="min-w-0 flex-1 outline-none"
            >
                {children}
            </main>
        </div>
    );
}

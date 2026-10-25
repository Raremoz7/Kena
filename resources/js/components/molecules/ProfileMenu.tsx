import { Link, router } from '@inertiajs/react';
import { useEffect, useId, useRef, useState } from 'react';
import { Avatar } from '@/components/atoms/Avatar';
import { Icon } from '@/components/atoms/Icon';
import type { IconName } from '@/components/atoms/Icon';
import { logout } from '@/routes';

const ITEM =
    'flex min-h-11 w-full items-center gap-2.5 rounded-btn px-3 font-body text-sm text-foreground transition-colors hover:bg-surface-2 focus-visible:bg-surface-2 focus-visible:outline-none';

function MenuLink({
    href,
    icon,
    children,
    onSelect,
}: {
    href: string;
    icon: IconName;
    children: string;
    onSelect: () => void;
}) {
    return (
        <Link href={href} role="menuitem" className={ITEM} onClick={onSelect}>
            <Icon name={icon} size={16} className="text-muted-foreground" />
            {children}
        </Link>
    );
}

/**
 * Menu do comprador logado (topo do site): ingressos, perfil e sair. Abre no
 * toque/clique do avatar; fecha com Esc, clique fora ou ao navegar.
 */
export function ProfileMenu({ name }: { name: string }) {
    const [open, setOpen] = useState(false);
    const rootRef = useRef<HTMLDivElement>(null);
    const buttonRef = useRef<HTMLButtonElement>(null);
    const menuId = useId();

    useEffect(() => {
        if (!open) {
            return;
        }

        const onPointer = (e: PointerEvent) => {
            if (!rootRef.current?.contains(e.target as Node)) {
                setOpen(false);
            }
        };
        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') {
                setOpen(false);
                buttonRef.current?.focus();
            }
        };
        document.addEventListener('pointerdown', onPointer);
        document.addEventListener('keydown', onKey);
        const stop = router.on('navigate', () => setOpen(false));

        return () => {
            document.removeEventListener('pointerdown', onPointer);
            document.removeEventListener('keydown', onKey);
            stop();
        };
    }, [open]);

    return (
        <div ref={rootRef} className="relative">
            <button
                ref={buttonRef}
                type="button"
                aria-haspopup="menu"
                aria-expanded={open}
                aria-controls={menuId}
                aria-label={`Menu da conta de ${name}`}
                onClick={() => setOpen((v) => !v)}
                className="flex min-h-11 items-center gap-2.5 rounded-full px-1 focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none"
            >
                <span className="hidden font-body text-sm text-muted-foreground sm:block">
                    {name.split(' ')[0]}
                </span>
                <Avatar name={name} />
            </button>

            {open && (
                <div
                    id={menuId}
                    role="menu"
                    aria-label="Conta"
                    className="absolute top-full right-0 z-50 mt-2 w-56 rounded-card border border-border bg-surface p-1.5 shadow-2xl"
                >
                    <p className="truncate px-3 pt-1.5 pb-2 font-body text-xs text-faint">
                        {name}
                    </p>
                    <MenuLink
                        href="/meus-ingressos"
                        icon="ticket"
                        onSelect={() => setOpen(false)}
                    >
                        Meus ingressos
                    </MenuLink>
                    <MenuLink
                        href="/settings/profile"
                        icon="user"
                        onSelect={() => setOpen(false)}
                    >
                        Meu perfil
                    </MenuLink>
                    <div className="my-1 border-t border-border" />
                    <Link
                        href={logout()}
                        as="button"
                        role="menuitem"
                        className={`${ITEM} text-danger-text`}
                        onClick={() => setOpen(false)}
                    >
                        <Icon name="logout" size={16} />
                        Sair
                    </Link>
                </div>
            )}
        </div>
    );
}

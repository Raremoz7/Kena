import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { Button } from '@/components/atoms/Button';
import { Input } from '@/components/atoms/Input';
import { Select } from '@/components/atoms/Select';

export interface SimplePage<T> {
    data: T[];
    meta: { currentPage: number; lastPage: number; total: number };
    links: { prev: string | null; next: string | null };
}

export const brl = (value: number) =>
    value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

/** Busca + filtro por organização (e extras) das listas do super admin. */
export function Filters({
    url,
    q,
    placeholder,
    organizations,
    organizationId,
    extra,
}: {
    url: string;
    q: string;
    placeholder: string;
    organizations: { id: number; name: string }[];
    organizationId: number | null;
    extra?: { name: string; value: string; children: ReactNode };
}) {
    const [text, setText] = useState(q);
    const [org, setOrg] = useState(
        organizationId ? String(organizationId) : '',
    );
    const [extraValue, setExtraValue] = useState(extra?.value ?? '');

    function submit(e: FormEvent) {
        e.preventDefault();
        const params: Record<string, string> = {};

        if (text !== '') {
            params.q = text;
        }

        if (org !== '') {
            params.organizacao = org;
        }

        if (extra && extraValue !== '') {
            params[extra.name] = extraValue;
        }

        router.get(url, params, { preserveScroll: true });
    }

    return (
        <form onSubmit={submit} className="flex flex-col gap-2 sm:flex-row">
            <Input
                aria-label="Buscar"
                value={text}
                onChange={(e) => setText(e.target.value)}
                placeholder={placeholder}
                className="sm:flex-1"
            />
            <Select
                aria-label="Organização"
                value={org}
                onChange={(e) => setOrg(e.target.value)}
                className="sm:w-56"
            >
                <option value="">Todas as organizações</option>
                {organizations.map((o) => (
                    <option key={o.id} value={o.id}>
                        {o.name}
                    </option>
                ))}
            </Select>
            {extra && (
                <Select
                    aria-label={extra.name}
                    value={extraValue}
                    onChange={(e) => setExtraValue(e.target.value)}
                    className="sm:w-44"
                >
                    {extra.children}
                </Select>
            )}
            <Button type="submit" variant="secondary">
                Filtrar
            </Button>
        </form>
    );
}

export function Pager<T>({ page }: { page: SimplePage<T> }) {
    if (page.meta.lastPage <= 1) {
        return null;
    }

    return (
        <div className="mt-4 flex items-center justify-between font-body text-sm text-muted-foreground">
            {page.links.prev ? (
                <Link href={page.links.prev} preserveScroll>
                    ← Anterior
                </Link>
            ) : (
                <span />
            )}
            <span>
                Página {page.meta.currentPage} de {page.meta.lastPage}
            </span>
            {page.links.next ? (
                <Link href={page.links.next} preserveScroll>
                    Próxima →
                </Link>
            ) : (
                <span />
            )}
        </div>
    );
}

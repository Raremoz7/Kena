import { useCallback, useEffect, useMemo, useState } from 'react';

function read(key: string): number[] {
    try {
        const raw = window.localStorage.getItem(key);
        const parsed: unknown = raw ? JSON.parse(raw) : [];

        return Array.isArray(parsed)
            ? parsed.filter((v): v is number => typeof v === 'number')
            : [];
    } catch {
        return [];
    }
}

/**
 * Carrinho de fotos de uma galeria, guardado no navegador para não se perder
 * ao recarregar a página ou voltar depois. Ids que não existem mais na galeria
 * são descartados.
 */
export function usePhotoCart(galleryId: number, validIds: number[]) {
    const key = `kena:fotos:carrinho:${galleryId}`;
    const valid = useMemo(() => new Set(validIds), [validIds]);
    const [ids, setIds] = useState<number[]>(() =>
        typeof window === 'undefined'
            ? []
            : read(key).filter((id) => valid.has(id)),
    );

    useEffect(() => {
        try {
            window.localStorage.setItem(key, JSON.stringify(ids));
        } catch {
            // Navegação privada ou armazenamento cheio: o carrinho vale só nesta visita.
        }
    }, [key, ids]);

    const selected = useMemo(() => new Set(ids), [ids]);

    const toggle = useCallback((id: number) => {
        setIds((cur) =>
            cur.includes(id) ? cur.filter((x) => x !== id) : [...cur, id],
        );
    }, []);

    const addMany = useCallback((more: number[]) => {
        setIds((cur) => [...cur, ...more.filter((id) => !cur.includes(id))]);
    }, []);

    const remove = useCallback(
        (id: number) => setIds((cur) => cur.filter((x) => x !== id)),
        [],
    );
    const clear = useCallback(() => setIds([]), []);

    return {
        ids,
        selected,
        toggle,
        addMany,
        remove,
        clear,
        has: (id: number) => selected.has(id),
    };
}

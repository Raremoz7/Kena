import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import { Avatar } from '@/components/atoms/Avatar';
import { Badge } from '@/components/atoms/Badge';
import { Button } from '@/components/atoms/Button';
import { Icon } from '@/components/atoms/Icon';
import { Input } from '@/components/atoms/Input';
import { Select } from '@/components/atoms/Select';
import { EmptyState } from '@/components/molecules/EmptyState';
import { FormField } from '@/components/molecules/FormField';
import { Modal } from '@/components/molecules/Modal';
import { PhotoGalleryShell } from '@/components/organisms/photos/PhotoGalleryShell';
import type { GalleryShellData } from '@/components/organisms/photos/PhotoGalleryShell';
import { cn } from '@/lib/utils';

interface Person {
    id: number;
    name: string;
    face: string | null;
    photoCount: number;
    hidden: boolean;
}

function normalize(text: string): string {
    return text
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase();
}

function Face({ person }: { person: Person }) {
    if (!person.face) {
        return <Avatar name={person.name} size={56} />;
    }

    return (
        <img
            src={person.face}
            alt=""
            loading="lazy"
            className="size-14 shrink-0 rounded-full border border-border object-cover"
        />
    );
}

export default function PhotoPeople({
    shell,
    people,
}: {
    shell: GalleryShellData;
    people: Person[];
}) {
    const [query, setQuery] = useState('');
    const [renaming, setRenaming] = useState<Person | null>(null);
    const [merging, setMerging] = useState<Person | null>(null);
    const [name, setName] = useState('');
    const [target, setTarget] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);

    const visible = useMemo(() => {
        const q = normalize(query.trim());

        return q
            ? people.filter((p) => normalize(p.name).includes(q))
            : people;
    }, [people, query]);

    const hiddenCount = people.filter((p) => p.hidden).length;

    function closeDialogs() {
        setRenaming(null);
        setMerging(null);
        setErrors({});
        setBusy(false);
    }

    function openRename(person: Person) {
        setName(person.name);
        setErrors({});
        setRenaming(person);
    }

    function openMerge(person: Person) {
        setTarget('');
        setErrors({});
        setMerging(person);
    }

    function submitRename(e: FormEvent) {
        e.preventDefault();

        if (!renaming) {
            return;
        }

        setBusy(true);
        router.put(
            `/painel/fotos/pessoas/${renaming.id}`,
            { name },
            {
                preserveScroll: true,
                onSuccess: closeDialogs,
                onError: (errs) => {
                    setErrors(errs);
                    setBusy(false);
                },
            },
        );
    }

    function submitMerge(e: FormEvent) {
        e.preventDefault();

        if (!merging || !target) {
            return;
        }

        setBusy(true);
        router.post(
            `/painel/fotos/pessoas/${merging.id}/unir`,
            { into: Number(target) },
            {
                preserveScroll: true,
                onSuccess: closeDialogs,
                onError: (errs) => {
                    setErrors(errs);
                    setBusy(false);
                },
            },
        );
    }

    function toggleHidden(person: Person) {
        router.put(
            `/painel/fotos/pessoas/${person.id}/filtro`,
            { hidden: !person.hidden },
            { preserveScroll: true },
        );
    }

    return (
        <>
            <Head title={`Painel — Pessoas de ${shell.event.title}`} />
            <PhotoGalleryShell shell={shell} tab="pessoas">
                {people.length === 0 ? (
                    <div className="rounded-card border border-border py-14">
                        <EmptyState
                            icon="users"
                            title="Ninguém identificado ainda"
                            description="As pessoas aparecem aqui quando o importador lê os rostos marcados no Lightroom."
                        />
                    </div>
                ) : (
                    <>
                        <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="relative w-full sm:max-w-xs">
                                <Icon
                                    name="search"
                                    size={16}
                                    className="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-faint"
                                />
                                <Input
                                    type="search"
                                    value={query}
                                    onChange={(e) => setQuery(e.target.value)}
                                    placeholder="Buscar pessoa"
                                    aria-label="Buscar pessoa"
                                    className="pl-10"
                                />
                            </div>
                            <p className="font-body text-sm text-muted-foreground">
                                {people.length} pessoas
                                {hiddenCount > 0 &&
                                    ` · ${hiddenCount} escondida${hiddenCount === 1 ? '' : 's'} do filtro`}
                            </p>
                        </div>

                        <ul className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            {visible.map((person) => (
                                <li
                                    key={person.id}
                                    className={cn(
                                        'flex flex-col gap-3 rounded-card border border-border bg-surface p-4',
                                        person.hidden && 'bg-surface-2',
                                    )}
                                >
                                    <div className="flex items-center gap-3">
                                        <Face person={person} />
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate font-body text-sm font-semibold text-foreground">
                                                {person.name}
                                            </p>
                                            <p className="font-body text-xs text-faint tabular-nums">
                                                {person.photoCount}{' '}
                                                {person.photoCount === 1
                                                    ? 'foto'
                                                    : 'fotos'}
                                            </p>
                                        </div>
                                        {person.hidden && (
                                            <Badge tone="neutral">
                                                Escondida
                                            </Badge>
                                        )}
                                    </div>
                                    <div className="flex flex-wrap gap-1.5">
                                        <Button
                                            size="sm"
                                            variant="secondary"
                                            onClick={() => openRename(person)}
                                        >
                                            Corrigir nome
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="secondary"
                                            onClick={() => openMerge(person)}
                                            disabled={people.length < 2}
                                        >
                                            Unir com…
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            onClick={() => toggleHidden(person)}
                                            aria-pressed={person.hidden}
                                        >
                                            <Icon name="eye" size={15} />
                                            {person.hidden
                                                ? 'Mostrar no filtro'
                                                : 'Esconder do filtro'}
                                        </Button>
                                    </div>
                                </li>
                            ))}
                        </ul>

                        {visible.length === 0 && (
                            <p className="py-10 text-center font-body text-sm text-muted-foreground">
                                Ninguém com esse nome.
                            </p>
                        )}

                        <p className="mt-6 max-w-prose font-body text-xs text-faint">
                            Esconder tira a pessoa da lista de rostos em /fotos.
                            As fotos dela continuam na galeria e à venda.
                        </p>
                    </>
                )}
            </PhotoGalleryShell>

            <Modal
                open={renaming !== null}
                onOpenChange={(open) => !open && closeDialogs()}
                title="Corrigir nome"
                description="O novo nome aparece no filtro por rosto de /fotos."
            >
                <form onSubmit={submitRename} className="flex flex-col gap-5">
                    <FormField
                        label="Nome"
                        htmlFor="person-name"
                        error={errors.name}
                    >
                        <Input
                            id="person-name"
                            value={name}
                            maxLength={120}
                            onChange={(e) => setName(e.target.value)}
                            autoFocus
                        />
                    </FormField>
                    <div className="flex flex-col-reverse gap-2 *:w-full sm:flex-row sm:justify-end sm:*:w-auto">
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={closeDialogs}
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="submit"
                            disabled={
                                busy ||
                                name.trim() === '' ||
                                name.trim() === renaming?.name
                            }
                        >
                            {busy ? 'Salvando…' : 'Salvar nome'}
                        </Button>
                    </div>
                </form>
            </Modal>

            <Modal
                open={merging !== null}
                onOpenChange={(open) => !open && closeDialogs()}
                title="Unir pessoas"
                description={
                    merging
                        ? `As ${merging.photoCount} fotos de "${merging.name}" passam para a pessoa escolhida, e "${merging.name}" deixa de existir.`
                        : undefined
                }
            >
                <form onSubmit={submitMerge} className="flex flex-col gap-5">
                    <FormField
                        label="Unir com"
                        htmlFor="merge-into"
                        helper="Vale o nome e o rosto da pessoa escolhida."
                        error={errors.into}
                    >
                        <Select
                            id="merge-into"
                            value={target}
                            onChange={(e) => setTarget(e.target.value)}
                        >
                            <option value="">Escolha a pessoa…</option>
                            {people
                                .filter((p) => p.id !== merging?.id)
                                .map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.name} ({p.photoCount})
                                    </option>
                                ))}
                        </Select>
                    </FormField>
                    <div className="flex flex-col-reverse gap-2 *:w-full sm:flex-row sm:justify-end sm:*:w-auto">
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={closeDialogs}
                        >
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={busy || !target}>
                            {busy ? 'Unindo…' : 'Unir pessoas'}
                        </Button>
                    </div>
                </form>
            </Modal>
        </>
    );
}

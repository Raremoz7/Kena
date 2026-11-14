import { useMemo, useState } from 'react';
import { Icon } from '@/components/atoms/Icon';
import { Input } from '@/components/atoms/Input';
import { firstName, normalize } from '@/lib/photos/types';
import type { GalleryPerson } from '@/lib/photos/types';
import { cn } from '@/lib/utils';
import { PhotoSheet } from './PhotoSheet';

function FaceAvatar({
    person,
    size,
}: {
    person: GalleryPerson;
    size: 'md' | 'lg';
}) {
    const dim = size === 'lg' ? 'size-[4.5rem]' : 'size-14';

    return person.face ? (
        <img
            src={person.face}
            alt=""
            className={cn(dim, 'rounded-full bg-surface-2 object-cover')}
            draggable={false}
        />
    ) : (
        <span
            className={cn(
                dim,
                'flex items-center justify-center rounded-full bg-surface-2 font-display text-lg text-muted-foreground uppercase',
            )}
        >
            {person.name.slice(0, 1)}
        </span>
    );
}

interface FaceButtonProps {
    person: GalleryPerson;
    selected: boolean;
    onToggle: () => void;
    size?: 'md' | 'lg';
    fullName?: boolean;
}

/** Um rosto do filtro: toque marca/desmarca, com o anel e o check deixando claro o que está filtrando. */
function FaceButton({
    person,
    selected,
    onToggle,
    size = 'md',
    fullName = false,
}: FaceButtonProps) {
    return (
        <button
            type="button"
            onClick={onToggle}
            aria-pressed={selected}
            title={`${person.name}: ${person.count} fotos`}
            className="group flex w-[4.75rem] shrink-0 snap-start flex-col items-center gap-1.5 rounded-btn py-1 outline-none focus-visible:ring-2 focus-visible:ring-accent"
        >
            <span className="relative">
                <span
                    className={cn(
                        'block rounded-full p-[3px] transition-all duration-200',
                        selected
                            ? 'bg-accent'
                            : 'bg-transparent group-hover:bg-border-strong',
                    )}
                >
                    <span className="block rounded-full bg-bg p-[2px]">
                        <FaceAvatar person={person} size={size} />
                    </span>
                </span>
                <span
                    className={cn(
                        'absolute -right-0.5 -bottom-0.5 flex size-5 items-center justify-center rounded-full border-2 border-bg bg-accent text-accent-fg transition-all duration-200',
                        selected
                            ? 'scale-100 opacity-100'
                            : 'scale-50 opacity-0',
                    )}
                >
                    <Icon name="check" size={11} strokeWidth={3} />
                </span>
            </span>
            <span
                className={cn(
                    'w-full truncate text-center font-body text-xs leading-tight',
                    selected
                        ? 'font-semibold text-foreground'
                        : 'text-muted-foreground',
                )}
            >
                {fullName ? person.name : firstName(person.name)}
            </span>
            {fullName && (
                <span className="-mt-1 font-body text-[11px] text-faint">
                    {person.count} fotos
                </span>
            )}
        </button>
    );
}

interface FaceFilterProps {
    people: GalleryPerson[];
    selected: number[];
    onToggle: (id: number) => void;
    onClear: () => void;
    /** A grade completa abre também pelo botão "Pessoas" da barra fixa. */
    pickerOpen: boolean;
    onPickerOpenChange: (open: boolean) => void;
}

/**
 * Faixa de rostos, como a visão "Pessoas" do Lightroom: a pessoa reconhece quem
 * procura e toca. Com muitas crianças, "Ver todas" abre a grade com busca por nome.
 */
export function FaceFilter({
    people,
    selected,
    onToggle,
    onClear,
    pickerOpen: open,
    onPickerOpenChange: setOpen,
}: FaceFilterProps) {
    const [term, setTerm] = useState('');

    // Selecionados primeiro na faixa, para não sumirem no fim da rolagem.
    const ordered = useMemo(
        () =>
            [...people].sort(
                (a, b) =>
                    Number(selected.includes(b.id)) -
                    Number(selected.includes(a.id)),
            ),
        [people, selected],
    );

    const found = useMemo(() => {
        const q = normalize(term);

        return q ? people.filter((p) => normalize(p.name).includes(q)) : people;
    }, [people, term]);

    return (
        <>
            <div className="relative">
                <div
                    role="group"
                    aria-label="Filtrar por pessoa"
                    className="-mx-4 flex snap-x scroll-px-4 [scrollbar-width:none] gap-1 overflow-x-auto px-4 pb-1 sm:-mx-6 sm:scroll-px-6 sm:px-6 [&::-webkit-scrollbar]:hidden"
                >
                    <button
                        type="button"
                        onClick={() => setOpen(true)}
                        className="group flex w-[4.75rem] shrink-0 snap-start flex-col items-center gap-1.5 rounded-btn py-1 outline-none focus-visible:ring-2 focus-visible:ring-accent"
                    >
                        <span className="flex size-[3.875rem] items-center justify-center rounded-full border border-dashed border-border-strong bg-surface text-muted-foreground transition-colors group-hover:border-accent group-hover:text-accent-text">
                            <Icon name="search" size={20} />
                        </span>
                        <span className="font-body text-xs text-muted-foreground">
                            Ver todas
                        </span>
                    </button>
                    {ordered.map((p) => (
                        <FaceButton
                            key={p.id}
                            person={p}
                            selected={selected.includes(p.id)}
                            onToggle={() => onToggle(p.id)}
                        />
                    ))}
                </div>
                {/* Esmaecido na borda direita: indica que a faixa continua. */}
                <span
                    aria-hidden="true"
                    className="pointer-events-none absolute inset-y-0 -right-4 w-10 bg-gradient-to-l from-bg to-transparent sm:-right-6"
                />
            </div>

            <PhotoSheet
                open={open}
                onOpenChange={setOpen}
                title="Quem você procura?"
                description="Toque em um ou mais rostos para ver só as fotos deles."
                footer={
                    <div className="flex items-center justify-between gap-3">
                        <button
                            type="button"
                            onClick={onClear}
                            disabled={selected.length === 0}
                            className="font-body text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline disabled:opacity-40"
                        >
                            Limpar seleção
                        </button>
                        <button
                            type="button"
                            onClick={() => setOpen(false)}
                            className="rounded-btn bg-accent px-5 py-3 font-body text-sm font-semibold text-accent-fg transition-colors hover:bg-accent-hover"
                        >
                            {selected.length === 0
                                ? 'Ver todas as fotos'
                                : `Ver fotos de ${selected.length} ${selected.length === 1 ? 'pessoa' : 'pessoas'}`}
                        </button>
                    </div>
                }
            >
                <div className="relative">
                    <Icon
                        name="search"
                        size={16}
                        className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-faint"
                    />
                    <Input
                        value={term}
                        onChange={(e) => setTerm(e.target.value)}
                        placeholder="Buscar pelo nome"
                        aria-label="Buscar pessoa pelo nome"
                        className="pl-9"
                    />
                </div>
                {found.length === 0 ? (
                    <p className="py-10 text-center font-body text-sm text-muted-foreground">
                        Ninguém com esse nome. Tente pelo rosto ou pela turma.
                    </p>
                ) : (
                    <div className="mt-4 grid grid-cols-[repeat(auto-fill,minmax(4.75rem,1fr))] justify-items-center gap-x-2 gap-y-4">
                        {found.map((p) => (
                            <FaceButton
                                key={p.id}
                                person={p}
                                selected={selected.includes(p.id)}
                                onToggle={() => onToggle(p.id)}
                                size="lg"
                                fullName
                            />
                        ))}
                    </div>
                )}
            </PhotoSheet>
        </>
    );
}

import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { Icon } from '@/components/atoms/Icon';
import { Tag } from '@/components/atoms/Tag';
import { FaceFilter } from '@/components/organisms/photos/FaceFilter';
import {
    PhotoCartBar,
    PhotoCartPanel,
} from '@/components/organisms/photos/PhotoCart';
import { PhotoGrid } from '@/components/organisms/photos/PhotoGrid';
import { PhotoGuestModal } from '@/components/organisms/photos/PhotoGuestModal';
import type { PhotoGuest } from '@/components/organisms/photos/PhotoGuestModal';
import { PhotoLightbox } from '@/components/organisms/photos/PhotoLightbox';
import { photoCount, quotePhotos } from '@/lib/photos/pricing';
import { firstName } from '@/lib/photos/types';
import type {
    GalleryGroup,
    GalleryInfo,
    GalleryPerson,
    GalleryPhoto,
} from '@/lib/photos/types';
import { usePhotoCart } from '@/lib/photos/use-photo-cart';
import { cn } from '@/lib/utils';
import { formatBRL } from '@/lib/veludo/format';
import { veludoToast } from '@/lib/veludo/toast';

interface Props {
    gallery: GalleryInfo;
    groups: GalleryGroup[];
    people: GalleryPerson[];
    photos: GalleryPhoto[];
    /** POST que transforma o carrinho em pedido (preço calculado no servidor). */
    purchaseUrl: string;
    emailCodeUrl: string;
    /** Fotos que a pessoa logada já comprou: ficam fora do carrinho. */
    ownedIds: number[];
}

/** Lê o filtro da URL, para o link compartilhado e o "voltar" abrirem do mesmo jeito. */
function readFilters(): { people: number[]; group: number | null } {
    if (typeof window === 'undefined') {
        return { people: [], group: null };
    }

    const params = new URLSearchParams(window.location.search);
    const people = (params.get('pessoas') ?? '')
        .split(',')
        .map(Number)
        .filter((n) => Number.isInteger(n) && n > 0);
    const group = Number(params.get('turma')) || null;

    return { people, group };
}

/** "Alice", "Alice e Helena", "Alice, Helena e mais 2". */
function namesLabel(names: string[]): string {
    if (names.length <= 2) {
        return names.join(' e ');
    }

    return `${names.slice(0, 2).join(', ')} e mais ${names.length - 2}`;
}

function Chip({
    active,
    onClick,
    children,
}: {
    active: boolean;
    onClick: () => void;
    children: React.ReactNode;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-pressed={active}
            className={cn(
                'inline-flex min-h-9 shrink-0 items-center gap-1.5 rounded-full border px-3.5 font-body text-sm whitespace-nowrap transition-colors outline-none focus-visible:ring-2 focus-visible:ring-accent',
                active
                    ? 'border-transparent bg-foreground font-semibold text-bg'
                    : 'border-border bg-surface text-muted-foreground hover:border-border-strong hover:text-foreground',
            )}
        >
            {children}
        </button>
    );
}

export default function PhotoGalleryPage({
    gallery,
    groups,
    people,
    photos,
    purchaseUrl,
    emailCodeUrl,
    ownedIds,
}: Props) {
    const { event, tiers } = gallery;
    const [initial] = useState(readFilters);
    const [selectedPeople, setSelectedPeople] = useState<number[]>(
        initial.people,
    );
    const [groupId, setGroupId] = useState<number | null>(initial.group);
    const [pickerOpen, setPickerOpen] = useState(false);
    const [cartOpen, setCartOpen] = useState(false);
    const [viewing, setViewing] = useState<number | null>(null);

    // Foto já comprada sai do carrinho sozinha (inclusive logo depois do pagamento).
    const allIds = useMemo(() => {
        const owned = new Set(ownedIds);

        return photos.map((p) => p.id).filter((id) => !owned.has(id));
    }, [photos, ownedIds]);
    const cart = usePhotoCart(gallery.id, allIds);

    const page = usePage<{ auth: { user: { id: number } | null } }>();
    const user = page.props.auth?.user ?? null;
    const [submitting, setSubmitting] = useState(false);
    const [guestOpen, setGuestOpen] = useState(false);
    const [guest, setGuest] = useState<PhotoGuest>({
        name: '',
        email: '',
        cpf: '',
        code: '',
    });
    const [guestErrors, setGuestErrors] = useState<Record<string, string>>({});

    function startPurchase(extra: Record<string, unknown> = {}) {
        if (submitting || cart.ids.length === 0) {
            return;
        }

        setSubmitting(true);
        router.post(
            purchaseUrl,
            { photos: cart.ids, ...extra },
            {
                onError: (errors) => {
                    setGuestErrors(errors as Record<string, string>);

                    if (errors.photos && !guestOpen) {
                        veludoToast.warning(
                            'Não foi possível continuar',
                            errors.photos,
                        );
                    }
                },
                onFinish: () => setSubmitting(false),
            },
        );
    }

    function checkout() {
        if (user) {
            startPurchase();

            return;
        }

        setGuestErrors({});
        setCartOpen(false);
        setGuestOpen(true);
    }
    const peopleById = useMemo(
        () => new Map(people.map((p) => [p.id, p])),
        [people],
    );
    const photoById = useMemo(
        () => new Map(photos.map((p) => [p.id, p])),
        [photos],
    );

    // Pessoa: fotos com qualquer uma das escolhidas. Turma: soma ao filtro de pessoa.
    const visible = useMemo(
        () =>
            photos.filter(
                (p) =>
                    (groupId === null || p.group === groupId) &&
                    (selectedPeople.length === 0 ||
                        p.people.some((id) => selectedPeople.includes(id))),
            ),
        [photos, groupId, selectedPeople],
    );

    const cartPhotos = useMemo(
        () =>
            cart.ids
                .map((id) => photoById.get(id))
                .filter((p): p is GalleryPhoto => !!p),
        [cart.ids, photoById],
    );
    const quote = quotePhotos(cartPhotos.length, tiers);
    const notInCart = visible.filter((p) => !cart.has(p.id));
    const filtering = selectedPeople.length > 0 || groupId !== null;

    useEffect(() => {
        const params = new URLSearchParams(window.location.search);
        params.delete('pessoas');
        params.delete('turma');

        if (selectedPeople.length) {
            params.set('pessoas', selectedPeople.join(','));
        }

        if (groupId) {
            params.set('turma', String(groupId));
        }

        const qs = params.toString();
        window.history.replaceState(
            window.history.state,
            '',
            `${window.location.pathname}${qs ? `?${qs}` : ''}`,
        );
    }, [selectedPeople, groupId]);

    function togglePerson(id: number) {
        setSelectedPeople((cur) =>
            cur.includes(id) ? cur.filter((x) => x !== id) : [...cur, id],
        );
    }

    function clearFilters() {
        setSelectedPeople([]);
        setGroupId(null);
    }

    function filterOnly(id: number) {
        setViewing(null);
        setGroupId(null);
        setSelectedPeople([id]);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function addAllVisible() {
        cart.addMany(notInCart.map((p) => p.id));
        veludoToast.success(`${photoCount(notInCart.length)} no carrinho`);
    }

    const selectedNames = selectedPeople
        .map((id) => peopleById.get(id)?.name)
        .filter((n): n is string => !!n);
    const groupName = groups.find((g) => g.id === groupId)?.name;
    const base = tiers[0];
    const pack = tiers[tiers.length - 1];

    return (
        <>
            <Head title={`Fotos · ${event.title}`} />

            <div
                className={cn(
                    'mx-auto max-w-6xl px-4 pt-6 sm:px-6 sm:pt-8',
                    cart.ids.length > 0 ? 'pb-32' : 'pb-16',
                )}
            >
                <Link
                    href="/fotos"
                    className="inline-flex items-center gap-1 font-body text-sm text-muted-foreground transition-colors hover:text-foreground"
                >
                    <Icon name="chevron-left" size={16} />
                    Todas as galerias
                </Link>

                <div className="mt-4 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="min-w-0">
                        <Tag>Fotos do espetáculo</Tag>
                        <h1 className="mt-2 font-display text-display-lg text-foreground uppercase">
                            {event.title}
                        </h1>
                        <p className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 font-body text-sm text-muted-foreground">
                            <span className="inline-flex items-center gap-1.5">
                                <Icon name="calendar" size={15} />
                                {event.dateLabel}
                            </span>
                            <span className="inline-flex items-center gap-1.5">
                                <Icon name="image" size={15} />
                                {photoCount(photos.length)}
                            </span>
                        </p>
                    </div>
                    {base && (
                        <div className="flex shrink-0 items-stretch self-start overflow-hidden rounded-card border border-border bg-surface font-body sm:self-auto">
                            <div className="px-4 py-2.5">
                                <p className="text-[11px] tracking-wide text-faint uppercase">
                                    Por foto
                                </p>
                                <p className="font-display text-lg text-foreground tabular-nums">
                                    {formatBRL(base.unitPrice)}
                                </p>
                            </div>
                            {pack && pack !== base && (
                                <div className="border-l border-border bg-accent/10 px-4 py-2.5">
                                    <p className="text-[11px] tracking-wide text-accent-text uppercase">
                                        {pack.minQty} ou mais
                                    </p>
                                    <p className="font-display text-lg text-foreground tabular-nums">
                                        {formatBRL(pack.unitPrice)}{' '}
                                        <span className="font-body text-xs text-muted-foreground">
                                            cada
                                        </span>
                                    </p>
                                </div>
                            )}
                        </div>
                    )}
                </div>

                {people.length > 0 && (
                    <section className="mt-8" aria-labelledby="quem">
                        <div className="mb-3 flex items-baseline justify-between gap-3">
                            <h2
                                id="quem"
                                className="font-body text-sm font-semibold text-foreground"
                            >
                                Quem você procura?
                            </h2>
                            <p className="hidden font-body text-xs text-faint sm:block">
                                Toque no rosto para ver só as fotos dela
                            </p>
                        </div>
                        <FaceFilter
                            people={people}
                            selected={selectedPeople}
                            onToggle={togglePerson}
                            onClear={() => setSelectedPeople([])}
                            pickerOpen={pickerOpen}
                            onPickerOpenChange={setPickerOpen}
                        />
                    </section>
                )}
            </div>

            {/* Barra fixa: o filtro ativo continua à vista enquanto rola a grade. */}
            <div className="sticky top-[calc(4rem+env(safe-area-inset-top))] z-30 border-y border-border bg-bg/90 backdrop-blur-md">
                <div className="mx-auto flex max-w-6xl [scrollbar-width:none] items-center gap-2 overflow-x-auto px-4 py-2.5 sm:px-6 [&::-webkit-scrollbar]:hidden">
                    <button
                        type="button"
                        onClick={() => setPickerOpen(true)}
                        className={cn(
                            'inline-flex min-h-9 shrink-0 items-center gap-2 rounded-full border pr-3.5 font-body text-sm whitespace-nowrap transition-colors outline-none focus-visible:ring-2 focus-visible:ring-accent',
                            selectedPeople.length
                                ? 'border-accent bg-accent/10 pl-1 font-semibold text-foreground'
                                : 'border-border bg-surface pl-3 text-muted-foreground hover:text-foreground',
                        )}
                    >
                        {selectedPeople.length ? (
                            <span className="flex -space-x-2">
                                {selectedPeople.slice(0, 3).map((id) => {
                                    const p = peopleById.get(id);

                                    return p?.face ? (
                                        <img
                                            key={id}
                                            src={p.face}
                                            alt=""
                                            className="size-7 rounded-full border-2 border-bg object-cover"
                                        />
                                    ) : null;
                                })}
                            </span>
                        ) : (
                            <Icon name="users" size={16} />
                        )}
                        {selectedPeople.length
                            ? namesLabel(selectedNames.map(firstName))
                            : 'Pessoas'}
                    </button>
                    <span
                        aria-hidden="true"
                        className="h-5 w-px shrink-0 bg-border"
                    />
                    <Chip
                        active={groupId === null}
                        onClick={() => setGroupId(null)}
                    >
                        Todas as turmas
                    </Chip>
                    {groups.map((g) => (
                        <Chip
                            key={g.id}
                            active={groupId === g.id}
                            onClick={() =>
                                setGroupId(groupId === g.id ? null : g.id)
                            }
                        >
                            {g.name}
                            <span
                                className={cn(
                                    'text-xs tabular-nums',
                                    groupId === g.id
                                        ? 'opacity-70'
                                        : 'text-faint',
                                )}
                            >
                                {g.count}
                            </span>
                        </Chip>
                    ))}
                </div>
            </div>

            <div
                className={cn(
                    'mx-auto max-w-6xl px-4 pt-5 sm:px-6',
                    cart.ids.length > 0 ? 'pb-32' : 'pb-16',
                )}
            >
                <div className="mb-4 flex min-h-9 flex-wrap items-center justify-between gap-x-4 gap-y-2">
                    <p
                        className="font-body text-sm text-muted-foreground"
                        aria-live="polite"
                    >
                        <span className="font-semibold text-foreground">
                            {photoCount(visible.length)}
                        </span>
                        {selectedNames.length > 0 && (
                            <> com {namesLabel(selectedNames.map(firstName))}</>
                        )}
                        {groupName && <> em {groupName}</>}
                        {filtering && (
                            <button
                                type="button"
                                onClick={clearFilters}
                                className="ml-3 text-accent-text underline-offset-4 hover:underline"
                            >
                                Limpar filtros
                            </button>
                        )}
                    </p>
                    {filtering && notInCart.length > 0 && (
                        <button
                            type="button"
                            onClick={addAllVisible}
                            className="inline-flex items-center gap-1.5 rounded-btn border border-border-strong px-3.5 py-2 font-body text-sm font-semibold text-foreground transition-colors hover:bg-surface-2 focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none"
                        >
                            <Icon name="plus" size={16} />
                            Pôr as{' '}
                            {visible.length === notInCart.length
                                ? ''
                                : `outras `}
                            {photoCount(notInCart.length)} no carrinho
                        </button>
                    )}
                </div>

                {visible.length === 0 ? (
                    <div className="rounded-card border border-dashed border-border bg-surface px-6 py-14 text-center">
                        <Icon
                            name="image"
                            size={28}
                            className="mx-auto text-faint"
                        />
                        <p className="mt-3 font-body text-sm font-semibold text-foreground">
                            Nenhuma foto com esse filtro
                        </p>
                        <p className="mt-1 font-body text-sm text-muted-foreground">
                            Nem todo rosto do palco é reconhecido. Tente só pela
                            turma.
                        </p>
                        <button
                            type="button"
                            onClick={clearFilters}
                            className="mt-4 font-body text-sm font-semibold text-accent-text underline-offset-4 hover:underline"
                        >
                            Ver todas as fotos
                        </button>
                    </div>
                ) : (
                    <PhotoGrid
                        photos={visible}
                        isInCart={cart.has}
                        onOpen={setViewing}
                        onToggleCart={cart.toggle}
                    />
                )}

                {selectedPeople.length > 0 && visible.length > 0 && (
                    <p className="mt-8 text-center font-body text-xs text-faint">
                        Falta alguma? Rostos de costas ou de longe podem não ter
                        sido reconhecidos. Veja também pela turma.
                    </p>
                )}
            </div>

            <PhotoLightbox
                photos={visible}
                index={viewing}
                onIndexChange={setViewing}
                peopleById={peopleById}
                isInCart={cart.has}
                onToggleCart={cart.toggle}
                onFilterPerson={filterOnly}
                // Preço de cada foto depois de pôr esta: a 10ª já mostra o valor do pacote.
                unitPriceLabel={formatBRL(
                    quotePhotos(cartPhotos.length + 1, tiers).unitPrice,
                )}
            />

            {cartPhotos.length > 0 && !cartOpen && viewing === null && (
                <PhotoCartBar
                    photos={cartPhotos}
                    quote={quote}
                    onOpen={() => setCartOpen(true)}
                />
            )}

            <PhotoCartPanel
                open={cartOpen}
                onOpenChange={setCartOpen}
                photos={cartPhotos}
                quote={quote}
                tiers={tiers}
                onRemove={cart.remove}
                onClear={cart.clear}
                onCheckout={checkout}
                submitting={submitting}
            />

            <PhotoGuestModal
                open={guestOpen}
                onOpenChange={setGuestOpen}
                guest={guest}
                onGuestChange={setGuest}
                errors={guestErrors}
                emailCodeUrl={emailCodeUrl}
                loginRedirect={`/fotos/${event.slug}`}
                submitting={submitting}
                onSubmit={() => startPurchase({ guest })}
            />
        </>
    );
}

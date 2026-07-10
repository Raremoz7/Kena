import { Head, Link, useForm } from '@inertiajs/react';
import type {FormEvent} from 'react';
import { Button } from '@/components/atoms/Button';
import { Icon } from '@/components/atoms/Icon';
import { Input } from '@/components/atoms/Input';
import { Select } from '@/components/atoms/Select';
import { Textarea } from '@/components/atoms/Textarea';
import { FormField } from '@/components/molecules/FormField';

interface Venue {
    id: number;
    name: string;
    city: string;
    seats: number;
}
interface SessionField {
    id?: number;
    starts_at: string;
    doors_at: string;
}
interface EventData {
    id: number;
    venue_id: number;
    title: string;
    kicker: string;
    description: string;
    status: string;
    duration_label: string | null;
    banner_from: string;
    banner_to: string;
    banner_image: string | null;
    sector_name: string;
    price: number;
    sessions: { id: number; starts_at: string; doors_at: string | null }[];
}

const presets = [
    { label: 'Roxo', from: 'oklch(0.32 0.08 285)', to: 'oklch(0.14 0.012 48)' },
    { label: 'Vinho', from: 'oklch(0.3 0.08 22)', to: 'oklch(0.14 0.012 48)' },
    { label: 'Azul', from: 'oklch(0.26 0.05 250)', to: 'oklch(0.14 0.012 48)' },
    { label: 'Verde', from: 'oklch(0.27 0.06 160)', to: 'oklch(0.14 0.012 48)' },
];

export default function EventForm({ venues, event }: { venues: Venue[]; event: EventData | null }) {
    const editing = !!event;
    const form = useForm({
        venue_id: event?.venue_id ?? venues[0]?.id ?? 0,
        title: event?.title ?? '',
        kicker: event?.kicker ?? '',
        description: event?.description ?? '',
        status: event?.status === 'on_sale' ? 'on_sale' : 'draft',
        duration_label: event?.duration_label ?? '',
        banner_from: event?.banner_from ?? presets[0].from,
        banner_to: event?.banner_to ?? presets[0].to,
        banner_image: null as File | null,
        sector_name: event?.sector_name ?? 'Plateia',
        price: event ? String(event.price) : '',
        sessions: (event?.sessions?.map((s) => ({
            id: s.id,
            starts_at: s.starts_at,
            doors_at: s.doors_at ?? '',
        })) ?? [{ starts_at: '', doors_at: '' }]) as SessionField[],
    });

    function submit(e: FormEvent) {
        e.preventDefault();

        if (editing && event) {
            form.put(`/dashboard/eventos/${event.id}`);
        } else {
            form.post('/dashboard/eventos');
        }
    }

    function setSession(i: number, patch: Partial<SessionField>) {
        form.setData(
            'sessions',
            form.data.sessions.map((s, idx) => (idx === i ? { ...s, ...patch } : s)),
        );
    }
    function addSession() {
        form.setData('sessions', [...form.data.sessions, { starts_at: '', doors_at: '' }]);
    }
    function removeSession(i: number) {
        form.setData(
            'sessions',
            form.data.sessions.filter((_, idx) => idx !== i),
        );
    }

    const venue = venues.find((v) => v.id === Number(form.data.venue_id));

    return (
        <>
            <Head title={editing ? 'Editar evento' : 'Novo evento'} />
            <div className="mx-auto max-w-2xl px-6 py-8 sm:px-8">
                <Link
                    href="/dashboard/eventos"
                    className="inline-flex items-center gap-1.5 font-body text-sm text-muted-foreground hover:text-foreground"
                >
                    <Icon name="chevron-left" size={16} /> Eventos
                </Link>
                <h1 className="mt-3 font-display text-display-lg text-foreground uppercase">
                    {editing ? 'Editar evento' : 'Novo evento'}
                </h1>

                <form onSubmit={submit} className="mt-6 flex flex-col gap-5">
                    <div className="flex flex-col gap-4 rounded-card border border-border bg-surface p-6">
                        <FormField label="Local" htmlFor="venue">
                            <Select
                                id="venue"
                                value={form.data.venue_id}
                                onChange={(e) => form.setData('venue_id', Number(e.target.value))}
                            >
                                {venues.map((v) => (
                                    <option key={v.id} value={v.id}>
                                        {v.name} · {v.city} ({v.seats} lugares)
                                    </option>
                                ))}
                            </Select>
                        </FormField>
                        {venue && venue.seats === 0 && (
                            <p className="font-body text-xs text-warning-text">
                                Esse local não tem assentos cadastrados — o mapa ficará vazio.
                            </p>
                        )}

                        <FormField label="Título" htmlFor="title" error={form.errors.title}>
                            <Input
                                id="title"
                                value={form.data.title}
                                onChange={(e) => form.setData('title', e.target.value)}
                                placeholder="O Quebra-Nozes"
                            />
                        </FormField>
                        <FormField label="Categoria (kicker)" htmlFor="kicker" error={form.errors.kicker}>
                            <Input
                                id="kicker"
                                value={form.data.kicker}
                                onChange={(e) => form.setData('kicker', e.target.value)}
                                placeholder="Ballet · Clássico"
                            />
                        </FormField>
                        <FormField label="Descrição" htmlFor="description" error={form.errors.description}>
                            <Textarea
                                id="description"
                                rows={4}
                                value={form.data.description}
                                onChange={(e) => form.setData('description', e.target.value)}
                                placeholder="Sobre o espetáculo…"
                            />
                        </FormField>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField label="Duração" htmlFor="duration">
                                <Input
                                    id="duration"
                                    value={form.data.duration_label}
                                    onChange={(e) => form.setData('duration_label', e.target.value)}
                                    placeholder="1h50 · com intervalo"
                                />
                            </FormField>
                            <FormField label="Status" htmlFor="status">
                                <Select
                                    id="status"
                                    value={form.data.status}
                                    onChange={(e) => form.setData('status', e.target.value)}
                                >
                                    <option value="draft">Rascunho (oculto)</option>
                                    <option value="on_sale">À venda (público)</option>
                                </Select>
                            </FormField>
                        </div>

                        <FormField label="Banner" error={form.errors.banner_image}>
                            <div className="flex flex-col gap-3">
                                <div className="flex items-center gap-3">
                                    <span
                                        className="relative h-10 w-28 shrink-0 overflow-hidden rounded-btn border border-border"
                                        style={{
                                            background: `linear-gradient(160deg, ${form.data.banner_from}, ${form.data.banner_to})`,
                                        }}
                                    >
                                        {event?.banner_image && !form.data.banner_image && (
                                            <img
                                                src={event.banner_image}
                                                alt=""
                                                className="absolute inset-0 size-full object-cover"
                                            />
                                        )}
                                    </span>
                                    <div className="flex gap-2">
                                        {presets.map((p) => (
                                            <button
                                                key={p.label}
                                                type="button"
                                                aria-label={p.label}
                                                onClick={() => {
                                                    form.setData('banner_from', p.from);
                                                    form.setData('banner_to', p.to);
                                                }}
                                                className="size-8 rounded-btn border border-border"
                                                style={{ background: `linear-gradient(160deg, ${p.from}, ${p.to})` }}
                                            />
                                        ))}
                                    </div>
                                </div>

                                <label className="flex cursor-pointer items-center gap-2 rounded-btn border border-dashed border-border px-3 py-2 font-body text-sm text-muted-foreground transition-colors hover:border-accent hover:text-foreground">
                                    <Icon name="image" size={16} />
                                    {form.data.banner_image
                                        ? form.data.banner_image.name
                                        : event?.banner_image
                                          ? 'Trocar imagem do banner'
                                          : 'Enviar imagem do banner (opcional)'}
                                    <input
                                        type="file"
                                        accept="image/png,image/jpeg,image/webp"
                                        className="hidden"
                                        onChange={(e) => form.setData('banner_image', e.target.files?.[0] ?? null)}
                                    />
                                </label>
                                <p className="font-body text-[11px] text-faint">
                                    A imagem (JPG/PNG/WebP, até 4MB) cobre o gradiente nas telas do evento.
                                </p>
                            </div>
                        </FormField>
                    </div>

                    <div className="flex flex-col gap-4 rounded-card border border-border bg-surface p-6">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField label="Setor" htmlFor="sector_name">
                                <Input
                                    id="sector_name"
                                    value={form.data.sector_name}
                                    onChange={(e) => form.setData('sector_name', e.target.value)}
                                    placeholder="Plateia"
                                />
                            </FormField>
                            <FormField label="Preço (R$)" htmlFor="price" error={form.errors.price}>
                                <Input
                                    id="price"
                                    type="number"
                                    step="0.01"
                                    inputMode="decimal"
                                    value={form.data.price}
                                    onChange={(e) => form.setData('price', e.target.value)}
                                    placeholder="45"
                                />
                            </FormField>
                        </div>
                        <div className="flex flex-col gap-3">
                            <div className="flex items-center justify-between">
                                <span className="kicker text-faint">Sessões</span>
                                <Button type="button" variant="ghost" size="sm" onClick={addSession}>
                                    <Icon name="plus" size={15} /> Adicionar sessão
                                </Button>
                            </div>

                            {form.data.sessions.map((s, i) => (
                                <div
                                    key={s.id ?? `new-${i}`}
                                    className="grid gap-4 rounded-btn border border-border bg-bg p-4 sm:grid-cols-[1fr_1fr_auto]"
                                >
                                    <FormField
                                        label="Início"
                                        htmlFor={`starts_at_${i}`}
                                        error={form.errors[`sessions.${i}.starts_at` as keyof typeof form.errors]}
                                    >
                                        <Input
                                            id={`starts_at_${i}`}
                                            type="datetime-local"
                                            value={s.starts_at}
                                            onChange={(e) => setSession(i, { starts_at: e.target.value })}
                                        />
                                    </FormField>
                                    <FormField label="Abertura dos portões" htmlFor={`doors_at_${i}`}>
                                        <Input
                                            id={`doors_at_${i}`}
                                            type="datetime-local"
                                            value={s.doors_at}
                                            onChange={(e) => setSession(i, { doors_at: e.target.value })}
                                        />
                                    </FormField>
                                    <div className="flex items-end gap-1">
                                        {s.id && (
                                            <>
                                                <Button
                                                    asChild
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                >
                                                    <Link
                                                        href={`/dashboard/sessoes/${s.id}/assentos`}
                                                        title="Gerenciar assentos (bloquear/liberar)"
                                                    >
                                                        <Icon name="map-pin" size={16} />
                                                    </Link>
                                                </Button>
                                                <Button
                                                    asChild
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                >
                                                    <Link
                                                        href={`/dashboard/pedidos?session=${s.id}`}
                                                        title="Ver pedidos desta sessão"
                                                    >
                                                        <Icon name="agenda" size={16} />
                                                    </Link>
                                                </Button>
                                            </>
                                        )}
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            disabled={form.data.sessions.length === 1}
                                            onClick={() => removeSession(i)}
                                            aria-label="Remover sessão"
                                        >
                                            <Icon name="trash" size={16} />
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>
                        <p className="flex items-start gap-1.5 font-body text-[11px] text-faint">
                            <Icon name="info" size={14} className="mt-px" />
                            {editing
                                ? 'Cada sessão tem seu próprio mapa. Novas sessões geram o mapa do local; remover uma sessão só é possível se ela não tiver lugares vendidos.'
                                : 'Cada sessão gera automaticamente um mapa de assentos a partir do local selecionado.'}
                        </p>
                    </div>

                    <div className="flex justify-end gap-3">
                        <Button asChild variant="secondary">
                            <Link href="/dashboard/eventos">Cancelar</Link>
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? 'Salvando…' : editing ? 'Salvar alterações' : 'Criar evento'}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

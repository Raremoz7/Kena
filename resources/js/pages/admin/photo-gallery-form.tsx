import { Head, useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/atoms/Button';
import { Icon } from '@/components/atoms/Icon';
import { Input } from '@/components/atoms/Input';
import { Textarea } from '@/components/atoms/Textarea';
import { FormActions } from '@/components/molecules/FormActions';
import { FormField } from '@/components/molecules/FormField';
import { DriveImportSection } from '@/components/organisms/photos/DriveImportSection';
import type { DriveState } from '@/components/organisms/photos/DriveImportSection';
import { PhotoGalleryShell } from '@/components/organisms/photos/PhotoGalleryShell';
import type { GalleryShellData } from '@/components/organisms/photos/PhotoGalleryShell';
import {
    formatBRL,
    parseCents,
    parseTiers,
    tierErrors,
    tierWarnings,
} from '@/lib/photo-tiers';
import type { TierDraft } from '@/lib/photo-tiers';

interface FormProps {
    shell: GalleryShellData;
    drive: DriveState;
    form: {
        driveFolder: string;
        description: string;
        tiers: { min_qty: number; price: number }[];
    };
}

const reais = (value: number) => value.toFixed(2).replace('.', ',');

export default function PhotoGalleryForm({
    shell,
    drive,
    form: initial,
}: FormProps) {
    const form = useForm<{
        drive_folder: string;
        description: string;
        tiers: TierDraft[];
    }>({
        drive_folder: initial.driveFolder,
        description: initial.description,
        tiers: initial.tiers.map((t) => ({
            min_qty: String(t.min_qty),
            price: reais(t.price),
        })),
    });

    const parsed = useMemo(() => parseTiers(form.data.tiers), [form.data.tiers]);
    const rulesErrors = parsed ? tierErrors(parsed) : [];
    const warnings = parsed ? tierWarnings(parsed) : [];
    const incomplete = parsed === null;
    const serverTierErrors = Object.entries(form.errors)
        .filter(([key]) => key === 'tiers')
        .map(([, message]) => message);

    function setTier(index: number, patch: Partial<TierDraft>) {
        form.setData(
            'tiers',
            form.data.tiers.map((t, i) => (i === index ? { ...t, ...patch } : t)),
        );
    }

    function addTier() {
        const last = parsed?.[parsed.length - 1];
        const next: TierDraft = last
            ? {
                  min_qty: String(last.minQty + 5),
                  price: reais(Math.max(1, Math.floor(last.cents * 0.85)) / 100),
              }
            : { min_qty: '1', price: '' };

        form.setData('tiers', [...form.data.tiers, next]);
    }

    function removeTier(index: number) {
        form.setData(
            'tiers',
            form.data.tiers.filter((_, i) => i !== index),
        );
    }

    function applySuggestion(
        suggestion: { minQty: number; cents: number },
    ) {
        const tiers = [
            ...form.data.tiers,
            {
                min_qty: String(suggestion.minQty),
                price: reais(suggestion.cents / 100),
            },
        ].sort((a, b) => Number(a.min_qty) - Number(b.min_qty));

        form.setData('tiers', tiers);
    }

    function submit(e: FormEvent) {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            tiers: data.tiers.map((t) => ({
                min_qty: t.min_qty,
                price: t.price.replace(',', '.'),
            })),
        }));
        form.put(`/painel/fotos/${shell.event.id}`, { preserveScroll: true });
    }

    const rowError = (index: number) =>
        form.errors[`tiers.${index}.min_qty` as keyof typeof form.errors] ??
        form.errors[`tiers.${index}.price` as keyof typeof form.errors];

    return (
        <>
            <Head title={`Painel — Fotos de ${shell.event.title}`} />
            <PhotoGalleryShell shell={shell} tab="ajustes" width="medium">
                {!shell.exists && (
                    <p className="mb-5 rounded-card border border-border bg-surface px-4 py-3 font-body text-sm text-muted-foreground">
                        Defina o preço e salve para criar a galeria. Depois
                        cole o link da pasta do Drive e importe as fotos
                        (ou use o comando{' '}
                        <code className="font-mono text-foreground">
                            fotos:importar
                        </code>
                        ).
                    </p>
                )}

                <form onSubmit={submit} className="flex flex-col gap-5">
                    <section className="flex flex-col gap-4 rounded-card border border-border bg-surface p-4 sm:p-6">
                        <FormField
                            label="Descrição"
                            htmlFor="description"
                            helper="Aparece no topo da galeria pública. Opcional."
                            error={form.errors.description}
                        >
                            <Textarea
                                id="description"
                                rows={3}
                                maxLength={1000}
                                value={form.data.description}
                                onChange={(e) =>
                                    form.setData('description', e.target.value)
                                }
                                placeholder="Registros do espetáculo, feitos pelo nosso time."
                            />
                        </FormField>
                    </section>

                    <DriveImportSection
                        drive={drive}
                        eventId={shell.event.id}
                        value={form.data.drive_folder}
                        savedValue={initial.driveFolder}
                        onChange={(value) => form.setData('drive_folder', value)}
                        error={form.errors.drive_folder}
                    />

                    <section className="flex flex-col gap-4 rounded-card border border-border bg-surface p-4 sm:p-6">
                        <div>
                            <h2 className="font-display text-display-sm text-foreground uppercase">
                                Faixas de preço
                            </h2>
                            <p className="mt-1 font-body text-sm text-muted-foreground">
                                Quanto mais fotos a pessoa leva, menor o preço
                                de cada uma. O preço da faixa vale para todas
                                as fotos do pedido.
                            </p>
                        </div>

                        <ul className="flex flex-col gap-3">
                            {form.data.tiers.map((tier, index) => {
                                const cents = parseCents(tier.price);
                                const qty = /^\d+$/.test(tier.min_qty)
                                    ? Number(tier.min_qty)
                                    : null;
                                const error = rowError(index);

                                return (
                                    <li
                                        key={index}
                                        className="rounded-card border border-border bg-bg p-3"
                                    >
                                        <div className="grid grid-cols-[1fr_1fr_auto] items-end gap-3">
                                            <FormField
                                                label={
                                                    index === 0
                                                        ? 'A partir de (fotos)'
                                                        : 'A partir de'
                                                }
                                                htmlFor={`qty-${index}`}
                                            >
                                                <Input
                                                    id={`qty-${index}`}
                                                    type="number"
                                                    inputMode="numeric"
                                                    min={1}
                                                    value={tier.min_qty}
                                                    onChange={(e) =>
                                                        setTier(index, {
                                                            min_qty:
                                                                e.target.value,
                                                        })
                                                    }
                                                />
                                            </FormField>
                                            <FormField
                                                label="Preço por foto (R$)"
                                                htmlFor={`price-${index}`}
                                            >
                                                <Input
                                                    id={`price-${index}`}
                                                    inputMode="decimal"
                                                    value={tier.price}
                                                    onChange={(e) =>
                                                        setTier(index, {
                                                            price: e.target
                                                                .value,
                                                        })
                                                    }
                                                    placeholder="35,00"
                                                />
                                            </FormField>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => removeTier(index)}
                                                disabled={
                                                    form.data.tiers.length === 1
                                                }
                                                aria-label={`Remover faixa ${index + 1}`}
                                            >
                                                <Icon name="trash" size={18} />
                                            </Button>
                                        </div>
                                        {cents !== null && qty !== null && (
                                            <p className="mt-2 font-body text-xs text-faint">
                                                {qty}{' '}
                                                {qty === 1 ? 'foto' : 'fotos'}{' '}
                                                = {formatBRL(qty * cents)}
                                            </p>
                                        )}
                                        {error && (
                                            <p className="mt-2 font-body text-xs text-danger-text">
                                                {error}
                                            </p>
                                        )}
                                    </li>
                                );
                            })}
                        </ul>

                        <div>
                            <Button
                                type="button"
                                variant="secondary"
                                size="sm"
                                onClick={addTier}
                                disabled={form.data.tiers.length >= 8}
                            >
                                <Icon name="plus" size={15} /> Adicionar faixa
                            </Button>
                        </div>

                        {[...rulesErrors, ...serverTierErrors].length > 0 && (
                            <ul
                                role="alert"
                                className="flex flex-col gap-1 rounded-card border border-danger bg-danger/10 px-4 py-3 font-body text-sm text-danger-text"
                            >
                                {Array.from(
                                    new Set([
                                        ...rulesErrors,
                                        ...serverTierErrors,
                                    ]),
                                ).map((message) => (
                                    <li key={message}>{message}</li>
                                ))}
                            </ul>
                        )}

                        {warnings.map((w) => (
                            <div
                                key={w.index}
                                className="flex flex-col gap-3 rounded-card border border-warning bg-warning/10 px-4 py-3"
                            >
                                <p className="flex items-start gap-2 font-body text-sm text-foreground">
                                    <Icon
                                        name="alert"
                                        size={18}
                                        className="mt-0.5 shrink-0"
                                    />
                                    <span>
                                        {w.nextQty - 1} fotos custam{' '}
                                        {formatBRL(w.cost)}, mais que{' '}
                                        {w.nextQty} fotos por{' '}
                                        {formatBRL(w.nextCost)}. Quem levar de{' '}
                                        {w.fromQty} a {w.nextQty - 1} fotos
                                        paga mais do que pagaria levando{' '}
                                        {w.nextQty}.
                                    </span>
                                </p>
                                {w.suggestion && (
                                    <div className="flex flex-wrap items-center gap-3 pl-7">
                                        <span className="font-body text-sm text-muted-foreground">
                                            Sugestão: uma faixa a partir de{' '}
                                            {w.suggestion.minQty} fotos por{' '}
                                            {formatBRL(w.suggestion.cents)}{' '}
                                            cada, para quem leva{' '}
                                            {w.suggestion.minQty} não pagar
                                            mais que quem leva{' '}
                                            {w.suggestion.minQty - 1}. Se o
                                            aviso voltar, repita.
                                        </span>
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="secondary"
                                            onClick={() =>
                                                applySuggestion(w.suggestion!)
                                            }
                                        >
                                            Adicionar essa faixa
                                        </Button>
                                    </div>
                                )}
                            </div>
                        ))}
                    </section>

                    <FormActions>
                        <Button
                            type="submit"
                            disabled={
                                form.processing ||
                                incomplete ||
                                rulesErrors.length > 0
                            }
                        >
                            {form.processing
                                ? 'Salvando…'
                                : shell.exists
                                  ? 'Salvar alterações'
                                  : 'Criar galeria'}
                        </Button>
                    </FormActions>
                </form>
            </PhotoGalleryShell>
        </>
    );
}

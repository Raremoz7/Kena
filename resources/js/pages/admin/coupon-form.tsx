import { Head, Link, useForm } from '@inertiajs/react';
import type {FormEvent} from 'react';
import { Button } from '@/components/atoms/Button';
import { Icon } from '@/components/atoms/Icon';
import { Input } from '@/components/atoms/Input';
import { Select } from '@/components/atoms/Select';
import { FormField } from '@/components/molecules/FormField';

interface EventOption {
    id: number;
    title: string;
}
interface CouponData {
    id: number;
    code: string;
    type: string;
    value: number;
    max_uses: number | null;
    expires_at: string | null;
    active: boolean;
    event_id: number | null;
}

export default function CouponForm({ coupon, events }: { coupon: CouponData | null; events: EventOption[] }) {
    const editing = !!coupon;
    const form = useForm({
        code: coupon?.code ?? '',
        type: coupon?.type === 'fixed' ? 'fixed' : 'percent',
        value: coupon ? String(coupon.value) : '',
        max_uses: coupon?.max_uses != null ? String(coupon.max_uses) : '',
        expires_at: coupon?.expires_at ?? '',
        active: coupon?.active ?? true,
        event_id: coupon?.event_id ?? ('' as number | ''),
    });

    function submit(e: FormEvent) {
        e.preventDefault();

        if (editing && coupon) {
            form.put(`/dashboard/cupons/${coupon.id}`);
        } else {
            form.post('/dashboard/cupons');
        }
    }

    const isPercent = form.data.type === 'percent';

    return (
        <>
            <Head title={editing ? 'Editar cupom' : 'Novo cupom'} />
            <div className="mx-auto max-w-2xl px-6 py-8 sm:px-8">
                <Link
                    href="/dashboard/cupons"
                    className="inline-flex items-center gap-1.5 font-body text-sm text-muted-foreground hover:text-foreground"
                >
                    <Icon name="chevron-left" size={16} /> Cupons
                </Link>
                <h1 className="mt-3 font-display text-display-lg text-foreground uppercase">
                    {editing ? 'Editar cupom' : 'Novo cupom'}
                </h1>

                <form onSubmit={submit} className="mt-6 flex flex-col gap-5">
                    <div className="flex flex-col gap-4 rounded-card border border-border bg-surface p-6">
                        <FormField label="Código" htmlFor="code" error={form.errors.code}>
                            <Input
                                id="code"
                                value={form.data.code}
                                onChange={(e) => form.setData('code', e.target.value.toUpperCase())}
                                placeholder="NOITE10"
                                className="uppercase"
                            />
                        </FormField>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField label="Tipo" htmlFor="type">
                                <Select
                                    id="type"
                                    value={form.data.type}
                                    onChange={(e) => form.setData('type', e.target.value)}
                                >
                                    <option value="percent">Percentual (%)</option>
                                    <option value="fixed">Valor fixo (R$)</option>
                                </Select>
                            </FormField>
                            <FormField
                                label={isPercent ? 'Desconto (%)' : 'Desconto (R$)'}
                                htmlFor="value"
                                error={form.errors.value}
                            >
                                <Input
                                    id="value"
                                    type="number"
                                    step={isPercent ? '1' : '0.01'}
                                    inputMode="decimal"
                                    value={form.data.value}
                                    onChange={(e) => form.setData('value', e.target.value)}
                                    placeholder={isPercent ? '10' : '15,00'}
                                />
                            </FormField>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField
                                label="Limite de usos"
                                htmlFor="max_uses"
                                helper="Em branco = ilimitado."
                                error={form.errors.max_uses}
                            >
                                <Input
                                    id="max_uses"
                                    type="number"
                                    inputMode="numeric"
                                    value={form.data.max_uses}
                                    onChange={(e) => form.setData('max_uses', e.target.value)}
                                    placeholder="Ilimitado"
                                />
                            </FormField>
                            <FormField label="Expira em" htmlFor="expires_at" helper="Em branco = sem validade.">
                                <Input
                                    id="expires_at"
                                    type="datetime-local"
                                    value={form.data.expires_at}
                                    onChange={(e) => form.setData('expires_at', e.target.value)}
                                />
                            </FormField>
                        </div>

                        <FormField label="Evento" htmlFor="event_id" helper="Restringe o cupom a um evento.">
                            <Select
                                id="event_id"
                                value={form.data.event_id === '' ? '' : String(form.data.event_id)}
                                onChange={(e) => form.setData('event_id', e.target.value ? Number(e.target.value) : '')}
                            >
                                <option value="">Todos os eventos</option>
                                {events.map((ev) => (
                                    <option key={ev.id} value={ev.id}>
                                        {ev.title}
                                    </option>
                                ))}
                            </Select>
                        </FormField>

                        <label className="flex items-center gap-2 font-body text-sm text-foreground">
                            <input
                                type="checkbox"
                                checked={form.data.active}
                                onChange={(e) => form.setData('active', e.target.checked)}
                                className="size-4 rounded border-border-strong"
                            />
                            Ativo
                        </label>
                    </div>

                    <div className="flex justify-end gap-3">
                        <Button asChild variant="secondary">
                            <Link href="/dashboard/cupons">Cancelar</Link>
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? 'Salvando…' : editing ? 'Salvar alterações' : 'Criar cupom'}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

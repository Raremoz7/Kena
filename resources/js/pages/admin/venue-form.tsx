import { Head, Link, router, useForm } from '@inertiajs/react';
import { useRef, useState   } from 'react';
import type {ChangeEvent, FormEvent} from 'react';
import { Button } from '@/components/atoms/Button';
import { Icon } from '@/components/atoms/Icon';
import { Input } from '@/components/atoms/Input';
import { FormField } from '@/components/molecules/FormField';
import { veludoToast } from '@/lib/veludo/toast';

interface VenueData {
    id: number;
    name: string;
    city: string;
    state: string;
    address: string | null;
    maps_query: string | null;
    seatsCount: number;
    canEditMap: boolean;
    importUrl: string;
    generateUrl: string;
}

export default function VenueForm({ venue }: { venue: VenueData | null }) {
    const editing = !!venue;
    const form = useForm({
        name: venue?.name ?? '',
        city: venue?.city ?? '',
        state: venue?.state ?? '',
        address: venue?.address ?? '',
        maps_query: venue?.maps_query ?? '',
    });

    function submit(e: FormEvent) {
        e.preventDefault();

        if (editing && venue) {
            form.put(`/painel/locais/${venue.id}`);
        } else {
            form.post('/painel/locais');
        }
    }

    const [rows, setRows] = useState('10');
    const [seatsPerRow, setSeatsPerRow] = useState('20');
    const [seatBusy, setSeatBusy] = useState(false);
    const fileRef = useRef<HTMLInputElement | null>(null);

    function generateGrid() {
        if (!venue || seatBusy) {
            return;
        }

        setSeatBusy(true);
        router.post(
            venue.generateUrl,
            { rows: Number(rows), seats_per_row: Number(seatsPerRow) },
            {
                preserveScroll: true,
                onError: (e) => veludoToast.error('Não foi possível gerar', e.seats ?? e.rows ?? 'Tente novamente.'),
                onSuccess: () => veludoToast.success('Mapa gerado', 'Assentos criados.'),
                onFinish: () => setSeatBusy(false),
            },
        );
    }

    function importFile(e: ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0];

        if (!venue || !file || seatBusy) {
            return;
        }

        setSeatBusy(true);
        router.post(
            venue.importUrl,
            { file },
            {
                forceFormData: true,
                preserveScroll: true,
                onError: (err) => veludoToast.error('Falha na importação', err.seats ?? err.file ?? 'Arquivo inválido.'),
                onSuccess: () => veludoToast.success('Mapa importado', 'Assentos criados a partir do arquivo.'),
                onFinish: () => {
                    setSeatBusy(false);

                    if (fileRef.current) {
                        fileRef.current.value = '';
                    }
                },
            },
        );
    }

    return (
        <>
            <Head title={editing ? 'Editar local' : 'Novo local'} />
            <div className="mx-auto max-w-2xl px-6 py-8 sm:px-8">
                <Link
                    href="/painel/locais"
                    className="inline-flex items-center gap-1.5 font-body text-sm text-muted-foreground hover:text-foreground"
                >
                    <Icon name="chevron-left" size={16} /> Locais
                </Link>
                <h1 className="mt-3 font-display text-display-lg text-foreground uppercase">
                    {editing ? 'Editar local' : 'Novo local'}
                </h1>

                <form onSubmit={submit} className="mt-6 flex flex-col gap-5">
                    <div className="flex flex-col gap-4 rounded-card border border-border bg-surface p-6">
                        <FormField label="Nome" htmlFor="name" error={form.errors.name}>
                            <Input
                                id="name"
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                                placeholder="Teatro UNIP"
                            />
                        </FormField>
                        <div className="grid gap-4 sm:grid-cols-[1fr_120px]">
                            <FormField label="Cidade" htmlFor="city" error={form.errors.city}>
                                <Input
                                    id="city"
                                    value={form.data.city}
                                    onChange={(e) => form.setData('city', e.target.value)}
                                    placeholder="Brasília"
                                />
                            </FormField>
                            <FormField label="UF" htmlFor="state" error={form.errors.state}>
                                <Input
                                    id="state"
                                    value={form.data.state}
                                    onChange={(e) => form.setData('state', e.target.value.toUpperCase())}
                                    maxLength={2}
                                    placeholder="DF"
                                />
                            </FormField>
                        </div>
                        <FormField label="Endereço" htmlFor="address" error={form.errors.address}>
                            <Input
                                id="address"
                                value={form.data.address}
                                onChange={(e) => form.setData('address', e.target.value)}
                                placeholder="SGAS I, Quadra 913, Asa Sul"
                            />
                        </FormField>
                        <FormField
                            label="Busca no mapa (Google Maps)"
                            htmlFor="maps_query"
                            error={form.errors.maps_query}
                        >
                            <Input
                                id="maps_query"
                                value={form.data.maps_query}
                                onChange={(e) => form.setData('maps_query', e.target.value)}
                                placeholder="Teatro UNIP Asa Sul Brasília"
                            />
                        </FormField>
                        <p className="flex items-start gap-1.5 font-body text-[11px] text-faint">
                            <Icon name="info" size={14} className="mt-px" />
                            O mapa de assentos do local é cadastrado à parte (importação dos lugares).
                        </p>
                    </div>

                    <div className="flex justify-end gap-3">
                        <Button asChild variant="secondary">
                            <Link href="/painel/locais">Cancelar</Link>
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? 'Salvando…' : editing ? 'Salvar alterações' : 'Criar local'}
                        </Button>
                    </div>
                </form>

                {editing && venue && (
                    <div className="mt-6 rounded-card border border-border bg-surface p-6">
                        <h2 className="font-display text-display-sm text-foreground uppercase">Mapa de assentos</h2>
                        <p className="mt-1 font-body text-sm text-muted-foreground">
                            {venue.seatsCount > 0
                                ? `${venue.seatsCount} assentos cadastrados.`
                                : 'Nenhum assento cadastrado ainda — gere uma grade ou importe um arquivo.'}
                        </p>

                        {venue.canEditMap ? (
                            <div className="mt-5 flex flex-col gap-5">
                                <div>
                                    <p className="kicker text-faint">Gerar grade</p>
                                    <div className="mt-2 grid gap-3 sm:grid-cols-2">
                                        <FormField label="Fileiras" htmlFor="grid-rows">
                                            <Input
                                                id="grid-rows"
                                                type="number"
                                                inputMode="numeric"
                                                value={rows}
                                                onChange={(e) => setRows(e.target.value)}
                                            />
                                        </FormField>
                                        <FormField label="Lugares por fileira" htmlFor="grid-seats">
                                            <Input
                                                id="grid-seats"
                                                type="number"
                                                inputMode="numeric"
                                                value={seatsPerRow}
                                                onChange={(e) => setSeatsPerRow(e.target.value)}
                                            />
                                        </FormField>
                                    </div>
                                    <div className="mt-3">
                                        <Button type="button" variant="secondary" onClick={generateGrid} disabled={seatBusy}>
                                            <Icon name="maximize" size={15} /> Gerar grade
                                        </Button>
                                    </div>
                                    <p className="mt-1 font-body text-[11px] text-faint">
                                        Cria uma grade retangular (A1, A2, …). Substitui o mapa atual.
                                    </p>
                                </div>

                                <div className="border-t border-border pt-5">
                                    <p className="kicker text-faint">Importar de arquivo</p>
                                    <label className="mt-2 flex cursor-pointer items-center gap-2 rounded-btn border border-dashed border-border px-3 py-2 font-body text-sm text-muted-foreground transition-colors hover:border-accent hover:text-foreground">
                                        <Icon name="image" size={16} />
                                        {seatBusy ? 'Enviando…' : 'Escolher arquivo JSON'}
                                        <input
                                            ref={fileRef}
                                            type="file"
                                            accept=".json,application/json"
                                            className="hidden"
                                            onChange={importFile}
                                            disabled={seatBusy}
                                        />
                                    </label>
                                    <p className="mt-1 font-body text-[11px] text-faint">
                                        JSON: lista de {'{ code, line, number, x, y, kind }'}. Substitui o mapa atual.
                                    </p>
                                </div>
                            </div>
                        ) : (
                            <p className="mt-4 flex items-start gap-1.5 font-body text-xs text-warning-text">
                                <Icon name="alert" size={14} className="mt-px" />
                                O mapa não pode ser alterado porque já há eventos usando este local.
                            </p>
                        )}
                    </div>
                )}
            </div>
        </>
    );
}

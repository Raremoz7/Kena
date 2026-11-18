import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/atoms/Button';
import { Icon } from '@/components/atoms/Icon';
import { Input } from '@/components/atoms/Input';
import { ConfirmDialog } from '@/components/molecules/ConfirmDialog';
import { MobileList, MobileListItem } from '@/components/molecules/MobileList';
import { AdminPage, PageHeader } from '@/components/molecules/PageHeader';
import { Pagination } from '@/components/molecules/Pagination';
import type { Paginator } from '@/components/molecules/Pagination';

interface Buyer {
    id: number;
    name: string;
    email: string;
    /** Mascarado no servidor: só os 3 últimos dígitos. */
    cpf: string | null;
    createdAt: string | null;
    paidOrders: number;
}

interface BuyersPageProps {
    buyers: Paginator<Buyer>;
    search: string;
}

function ordersLabel(n: number): string {
    if (n === 0) {
        return 'Nenhum pedido pago';
    }

    return n === 1 ? '1 pedido pago' : `${n} pedidos pagos`;
}

export default function AdminBuyers({ buyers, search }: BuyersPageProps) {
    const [query, setQuery] = useState(search);
    const [unlinking, setUnlinking] = useState<Buyer | null>(null);

    function submitSearch(e: React.FormEvent) {
        e.preventDefault();
        const busca = query.trim();
        router.get('/painel/compradores', busca ? { busca } : {}, {
            preserveState: true,
            preserveScroll: true,
        });
    }

    function unlinkButton(b: Buyer, compact = false) {
        if (!b.cpf) {
            return null;
        }

        return (
            <Button
                size="sm"
                variant="ghost"
                onClick={() => setUnlinking(b)}
                aria-label={`Desvincular CPF de ${b.name}`}
                className={compact ? 'text-faint hover:text-danger-text' : ''}
            >
                Desvincular CPF
            </Button>
        );
    }

    const unlinkDescription = unlinking
        ? [
              `O CPF ${unlinking.cpf} sairá da conta de ${unlinking.name} (${unlinking.email}) e ficará livre para outro cadastro. A conta e os pedidos continuam.`,
              unlinking.paidOrders > 0
                  ? `Atenção: essa conta tem ${ordersLabel(unlinking.paidOrders).toLowerCase()}.`
                  : '',
          ]
              .filter(Boolean)
              .join(' ')
        : '';

    return (
        <>
            <Head title="Painel — Compradores" />
            <AdminPage>
                <PageHeader
                    title="Compradores"
                    description="Contas de quem compra na loja. Para liberar um CPF preso em outra conta, busque pelo número completo."
                />

                <form
                    onSubmit={submitSearch}
                    className="flex flex-col gap-2 sm:flex-row"
                    role="search"
                >
                    <Input
                        aria-label="Buscar comprador"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Nome, e-mail ou CPF completo"
                        className="sm:max-w-md"
                    />
                    <Button type="submit" variant="secondary">
                        <Icon name="search" size={16} /> Buscar
                    </Button>
                    {search !== '' && (
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => {
                                setQuery('');
                                router.get('/painel/compradores');
                            }}
                        >
                            Limpar
                        </Button>
                    )}
                </form>

                {buyers.data.length === 0 ? (
                    <p className="mt-6 rounded-card border border-border bg-surface p-6 text-center font-body text-sm text-muted-foreground">
                        {search
                            ? 'Nenhuma conta encontrada para essa busca.'
                            : 'Nenhum comprador cadastrado ainda.'}
                    </p>
                ) : (
                    <>
                        <MobileList className="mt-6">
                            {buyers.data.map((b) => (
                                <MobileListItem
                                    key={b.id}
                                    title={b.name}
                                    subtitle={
                                        <>
                                            {b.email}
                                            <br />
                                            {b.cpf ?? 'Sem CPF'} ·{' '}
                                            {ordersLabel(b.paidOrders)}
                                        </>
                                    }
                                    actions={unlinkButton(b, true) ?? undefined}
                                />
                            ))}
                        </MobileList>

                        <div className="mt-6 hidden overflow-hidden rounded-card border border-border md:block">
                            <table className="w-full border-collapse font-body text-sm">
                                <thead>
                                    <tr className="border-b border-border bg-surface-2 text-left">
                                        <th className="kicker px-4 py-3 text-faint">
                                            Comprador
                                        </th>
                                        <th className="kicker px-4 py-3 text-faint">
                                            CPF
                                        </th>
                                        <th className="kicker px-4 py-3 text-faint">
                                            Cadastro
                                        </th>
                                        <th className="kicker px-4 py-3 text-faint">
                                            Pedidos pagos
                                        </th>
                                        <th className="px-4 py-3" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {buyers.data.map((b) => (
                                        <tr
                                            key={b.id}
                                            className="border-b border-border last:border-b-0 hover:bg-surface-2"
                                        >
                                            <td className="px-4 py-3">
                                                <div className="font-medium text-foreground">
                                                    {b.name}
                                                </div>
                                                <div className="text-muted-foreground">
                                                    {b.email}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground tabular-nums">
                                                {b.cpf ?? (
                                                    <span className="text-faint">
                                                        Sem CPF
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground tabular-nums">
                                                {b.createdAt}
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground tabular-nums">
                                                {b.paidOrders}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex justify-end">
                                                    {unlinkButton(b)}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </>
                )}
                <Pagination links={buyers.links} />
            </AdminPage>

            <ConfirmDialog
                open={unlinking !== null}
                onOpenChange={(open) => !open && setUnlinking(null)}
                title="Desvincular CPF"
                description={unlinkDescription}
                confirmLabel="Desvincular"
                onConfirm={() => {
                    if (unlinking) {
                        router.delete(
                            `/painel/compradores/${unlinking.id}/cpf`,
                            {
                                preserveScroll: true,
                            },
                        );
                    }

                    setUnlinking(null);
                }}
            />
        </>
    );
}

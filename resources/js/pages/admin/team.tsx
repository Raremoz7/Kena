import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Badge } from '@/components/atoms/Badge';
import { Button } from '@/components/atoms/Button';
import { Icon } from '@/components/atoms/Icon';
import { Input } from '@/components/atoms/Input';
import { Select } from '@/components/atoms/Select';
import { ConfirmDialog } from '@/components/molecules/ConfirmDialog';
import { FormField } from '@/components/molecules/FormField';
import { Pagination } from '@/components/molecules/Pagination';
import type { Paginator } from '@/components/molecules/Pagination';

interface Member {
    id: number;
    name: string;
    email: string;
    role: string;
    isSelf: boolean;
}

interface RoleOption {
    value: string;
    label: string;
}

interface TeamPageProps {
    members: Paginator<Member>;
    roles: RoleOption[];
}

const roleLabel: Record<string, string> = {
    organizer: 'Organizador',
    staff: 'Staff',
};

export default function AdminTeam({ members, roles }: TeamPageProps) {
    const form = useForm({ name: '', email: '', role: 'staff', password: '' });
    const [removing, setRemoving] = useState<Member | null>(null);

    function invite(e: React.FormEvent) {
        e.preventDefault();
        form.post('/painel/equipe', {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    }

    function changeRole(member: Member, role: string) {
        router.put(
            `/painel/equipe/${member.id}`,
            { role },
            { preserveScroll: true },
        );
    }

    return (
        <>
            <Head title="Painel — Equipe" />
            <div className="px-6 py-8 sm:px-8">
                <div>
                    <h1 className="font-display text-display-lg text-foreground uppercase">
                        Equipe
                    </h1>
                    <p className="mt-1 font-body text-sm text-muted-foreground">
                        Organizadores acessam o painel completo. Staff faz
                        apenas o check-in na portaria.
                    </p>
                </div>

                <form
                    onSubmit={invite}
                    className="mt-6 grid gap-4 rounded-card border border-border bg-surface p-6 sm:grid-cols-[1fr_1fr_1fr_180px_auto] sm:items-end"
                >
                    <FormField
                        label="Nome"
                        htmlFor="tm-name"
                        error={form.errors.name}
                    >
                        <Input
                            id="tm-name"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            placeholder="Nome do membro"
                        />
                    </FormField>
                    <FormField
                        label="E-mail"
                        htmlFor="tm-email"
                        error={form.errors.email}
                    >
                        <Input
                            id="tm-email"
                            type="email"
                            value={form.data.email}
                            onChange={(e) =>
                                form.setData('email', e.target.value)
                            }
                            placeholder="email@exemplo.com"
                        />
                    </FormField>
                    <FormField
                        label="Senha inicial"
                        htmlFor="tm-password"
                        error={form.errors.password}
                    >
                        <Input
                            id="tm-password"
                            type="password"
                            value={form.data.password}
                            onChange={(e) =>
                                form.setData('password', e.target.value)
                            }
                            autoComplete="new-password"
                            placeholder="Mínimo 8 caracteres"
                        />
                    </FormField>
                    <FormField
                        label="Papel"
                        htmlFor="tm-role"
                        error={form.errors.role}
                    >
                        <Select
                            id="tm-role"
                            value={form.data.role}
                            onChange={(e) =>
                                form.setData('role', e.target.value)
                            }
                        >
                            {roles.map((r) => (
                                <option key={r.value} value={r.value}>
                                    {r.label}
                                </option>
                            ))}
                        </Select>
                    </FormField>
                    <Button type="submit" disabled={form.processing}>
                        <Icon name="plus" size={18} /> Adicionar
                    </Button>
                </form>

                <p className="mt-2 font-body text-xs text-faint">
                    O painel entra por e-mail e senha em /painel/login. Defina a
                    senha inicial e repasse ao membro — a conta do painel é
                    separada da conta de comprador.
                </p>

                <div className="mt-6 overflow-hidden rounded-card border border-border">
                    <table className="w-full border-collapse font-body text-sm">
                        <thead>
                            <tr className="border-b border-border bg-surface-2 text-left">
                                <th className="kicker px-4 py-3 text-faint">
                                    Membro
                                </th>
                                <th className="kicker px-4 py-3 text-faint">
                                    Papel
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {members.data.map((m) => (
                                <tr
                                    key={m.id}
                                    className="border-b border-border last:border-b-0 hover:bg-surface-2"
                                >
                                    <td className="px-4 py-3">
                                        <div className="font-medium text-foreground">
                                            {m.name}
                                            {m.isSelf && (
                                                <span className="ml-2 text-xs text-faint">
                                                    (você)
                                                </span>
                                            )}
                                        </div>
                                        <div className="text-muted-foreground">
                                            {m.email}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        {m.isSelf ? (
                                            <Badge tone="neutral">
                                                {roleLabel[m.role] ?? m.role}
                                            </Badge>
                                        ) : (
                                            <Select
                                                value={m.role}
                                                onChange={(e) =>
                                                    changeRole(
                                                        m,
                                                        e.target.value,
                                                    )
                                                }
                                                className="max-w-[220px]"
                                            >
                                                {roles.map((r) => (
                                                    <option
                                                        key={r.value}
                                                        value={r.value}
                                                    >
                                                        {r.label}
                                                    </option>
                                                ))}
                                            </Select>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex justify-end">
                                            {!m.isSelf && (
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() =>
                                                        setRemoving(m)
                                                    }
                                                    aria-label={`Remover ${m.name}`}
                                                >
                                                    <Icon
                                                        name="trash"
                                                        size={15}
                                                    />{' '}
                                                    Remover
                                                </Button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <Pagination links={members.links} />
            </div>

            <ConfirmDialog
                open={removing !== null}
                onOpenChange={(open) => !open && setRemoving(null)}
                title="Remover da equipe"
                description={
                    removing
                        ? `${removing.name} deixará de ter acesso ao painel e voltará a ser um comprador comum.`
                        : ''
                }
                confirmLabel="Remover"
                onConfirm={() => {
                    if (removing) {
                        router.delete(`/painel/equipe/${removing.id}`, {
                            preserveScroll: true,
                        });
                    }

                    setRemoving(null);
                }}
            />
        </>
    );
}

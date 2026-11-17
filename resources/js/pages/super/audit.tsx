import { Head } from '@inertiajs/react';
import { AdminPage, PageHeader } from '@/components/molecules/PageHeader';
import { Pager } from '@/components/organisms/SuperAdminList';
import type { SimplePage } from '@/components/organisms/SuperAdminList';

interface LogRow {
    id: number;
    when: string;
    admin: string;
    organization: string;
    action: string;
    details: string | null;
    ip: string | null;
}

/** Registro do que o super admin fez em nome dos organizadores. */
export default function SuperAudit({ logs }: { logs: SimplePage<LogRow> }) {
    return (
        <>
            <Head title="Admin — Registro" />
            <AdminPage>
                <PageHeader
                    title="Registro"
                    description="Entradas no painel de organizadores, suspensões, taxas e organizações criadas. Mais recentes primeiro."
                />
                <ul className="divide-y divide-border rounded-card border border-border bg-surface">
                    {logs.data.map((log) => (
                        <li
                            key={log.id}
                            className="flex flex-wrap items-start justify-between gap-2 p-4 font-body text-sm"
                        >
                            <div className="min-w-0">
                                <p className="text-foreground">
                                    <strong>{log.admin}</strong> · {log.action}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {log.organization}
                                    {log.details && ` · ${log.details}`}
                                </p>
                            </div>
                            <p className="text-right text-xs text-faint tabular-nums">
                                {log.when}
                                {log.ip && (
                                    <>
                                        <br />
                                        {log.ip}
                                    </>
                                )}
                            </p>
                        </li>
                    ))}
                    {logs.data.length === 0 && (
                        <li className="p-4 font-body text-sm text-muted-foreground">
                            Nada registrado ainda.
                        </li>
                    )}
                </ul>
                <Pager page={logs} />
            </AdminPage>
        </>
    );
}

<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\PlatformAuditLog;
use Inertia\Inertia;
use Inertia\Response;

/** Registro do que o super admin fez (entrar como, suspensões, taxas, criação). */
class AuditLogController extends Controller
{
    private const LABELS = [
        'impersonate.start' => 'Entrou como organizador',
        'impersonate.stop' => 'Saiu do painel do organizador',
        'organization.suspend' => 'Suspendeu as vendas',
        'organization.reactivate' => 'Reativou as vendas',
        'organization.fee' => 'Mudou a taxa',
        'organization.create' => 'Criou a organização',
    ];

    public function index(): Response
    {
        $logs = PlatformAuditLog::query()
            ->with(['admin', 'organization'])
            ->latest('id')
            ->paginate(50);

        return Inertia::render('super/audit', [
            'logs' => [
                'data' => $logs->getCollection()->map(fn (PlatformAuditLog $log): array => [
                    'id' => $log->id,
                    'when' => $log->created_at->format('d/m/Y H:i'),
                    'admin' => $log->admin->name ?? '—',
                    'organization' => $log->organization->name ?? '—',
                    'action' => self::LABELS[$log->action] ?? $log->action,
                    'details' => $log->meta !== null ? json_encode($log->meta, JSON_UNESCAPED_UNICODE) : null,
                    'ip' => $log->ip,
                ])->values(),
                'meta' => ['currentPage' => $logs->currentPage(), 'lastPage' => $logs->lastPage(), 'total' => $logs->total()],
                'links' => ['prev' => $logs->previousPageUrl(), 'next' => $logs->nextPageUrl()],
            ],
        ]);
    }
}

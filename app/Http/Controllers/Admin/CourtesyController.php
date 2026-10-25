<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\CourtesyService;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Cortesias nominais por evento: geração em lote e relatório de retiradas. */
class CourtesyController extends Controller
{
    private const STATUS_LABELS = [
        CourtesyService::STATUS_REDEEMED => 'Retirada',
        CourtesyService::STATUS_PENDING => 'Não retirada',
        CourtesyService::STATUS_EXPIRED => 'Expirada',
    ];

    public function __construct(private readonly CourtesyService $courtesies) {}

    public function index(Request $request): Response
    {
        $events = Event::query()->orderBy('title')->get(['id', 'title']);
        $event = $request->integer('event')
            ? $events->firstWhere('id', $request->integer('event'))
            : $events->first();

        return Inertia::render('admin/courtesies', [
            'events' => $events->map(fn (Event $e): array => ['id' => $e->id, 'title' => $e->title])->values(),
            'eventId' => $event?->id,
            'courtesies' => $event !== null ? $this->courtesies->report($event) : [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'people' => ['required', 'string', 'max:20000'],
            'tickets' => ['required', 'integer', 'min:1', 'max:10'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $people = CourtesyService::parseList($data['people']);
        if ($people === []) {
            throw ValidationException::withMessages(['people' => 'Cole pelo menos um nome (um por linha).']);
        }
        if (count($people) > 500) {
            throw ValidationException::withMessages(['people' => 'Gere no máximo 500 cortesias por vez.']);
        }

        /** @var Event $event */
        $event = Event::findOrFail($data['event_id']);
        // Prazo vale até o fim do dia escolhido.
        $expiresAt = filled($data['expires_at'] ?? null) ? Carbon::parse($data['expires_at'])->endOfDay() : null;

        $created = $this->courtesies->generate(
            $event,
            $people,
            (int) $data['tickets'],
            $expiresAt,
        );

        return redirect()
            ->route('admin.courtesies', ['event' => $event->id])
            ->with('status', $created->count().' cortesia(s) gerada(s).');
    }

    public function export(Request $request): StreamedResponse
    {
        /** @var Event $event */
        $event = Event::findOrFail($request->integer('event'));
        $rows = $this->courtesies->report($event);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            // BOM: o Excel abre os acentos certos.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Nome', 'Grupo', 'Código', 'Ingressos', 'Situação', 'Retirado por', 'E-mail', 'Telefone', 'Pedido', 'Assentos', 'Retirado em', 'Prazo'], ';');
            foreach ($rows as $r) {
                Csv::put($out, [
                    $r['beneficiary'] ?? '',
                    $r['group'] ?? '',
                    $r['code'],
                    $r['tickets'],
                    self::STATUS_LABELS[$r['status']] ?? $r['status'],
                    $r['redeemedBy']['name'] ?? '',
                    $r['redeemedBy']['email'] ?? '',
                    $r['redeemedBy']['phone'] ?? '',
                    $r['orderReference'] ?? '',
                    implode(' ', $r['seats']),
                    $r['redeemedAt'] !== null ? Carbon::parse($r['redeemedAt'])->format('d/m/Y H:i') : '',
                    $r['expiresAt'] !== null ? Carbon::parse($r['expiresAt'])->format('d/m/Y') : '',
                ], ';');
            }
            fclose($out);
        }, 'cortesias-'.$event->slug.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}

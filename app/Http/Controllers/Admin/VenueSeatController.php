<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EventSession;
use App\Models\Seat;
use App\Models\Sector;
use App\Models\SessionSeat;
use App\Models\Venue;
use App\Services\SessionSeatGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Cadastro da planta de assentos de um local: importa de JSON ou gera uma grade.
 * Permitido enquanto o local não tem VENDAS — ao trocar o mapa, os session_seats
 * das sessões são regenerados.
 */
class VenueSeatController extends Controller
{
    public function __construct(private readonly SessionSeatGenerator $seatGenerator) {}

    /** Importa a planta de um arquivo JSON: [{code,line,number,x,y,kind?}, ...]. */
    public function import(Request $request, Venue $venue): RedirectResponse
    {
        if ($this->locked($venue)) {
            return back()->withErrors(['seats' => 'Este local já tem vendas — não é possível trocar o mapa.']);
        }

        $request->validate(['file' => ['required', 'file', 'mimes:json,txt', 'max:4096']]);

        $decoded = json_decode((string) $request->file('file')->get(), true);
        if (! is_array($decoded) || $decoded === []) {
            return back()->withErrors(['seats' => 'JSON inválido ou vazio. Esperado uma lista de assentos.']);
        }

        $rows = [];
        foreach ($decoded as $s) {
            if (! is_array($s) || ! isset($s['code'], $s['x'], $s['y'])) {
                continue;
            }
            $line = (string) ($s['line'] ?? '');
            $rows[] = [
                'code' => (string) $s['code'],
                'line' => $line,
                'number' => (string) ($s['number'] ?? ''),
                'pos_x' => (int) $s['x'],
                'pos_y' => (int) $s['y'],
                'kind' => (string) ($s['kind'] ?? 'standard'),
            ];
        }

        if ($rows === []) {
            return back()->withErrors(['seats' => 'Nenhum assento válido no arquivo (precisa de code, x e y).']);
        }

        $this->replaceSeats($venue, $rows);

        return back();
    }

    /** Gera uma grade retangular de assentos (fileiras × lugares). */
    public function generateGrid(Request $request, Venue $venue): RedirectResponse
    {
        if ($this->locked($venue)) {
            return back()->withErrors(['seats' => 'Este local já tem vendas — não é possível trocar o mapa.']);
        }

        $data = $request->validate([
            'rows' => ['required', 'integer', 'min:1', 'max:60'],
            'seats_per_row' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $spacing = 24;
        $margin = 20;
        $rows = [];
        for ($r = 0; $r < $data['rows']; $r++) {
            $line = $this->rowLabel($r);
            for ($n = 1; $n <= $data['seats_per_row']; $n++) {
                $rows[] = [
                    'code' => $line.$n,
                    'line' => $line,
                    'number' => (string) $n,
                    'pos_x' => $margin + ($n - 1) * $spacing,
                    'pos_y' => $margin + $r * $spacing,
                    'kind' => 'standard',
                ];
            }
        }

        $this->replaceSeats($venue, $rows);

        return back();
    }

    /** Travado apenas quando há venda viva — sem vendas, o mapa é reeditável. */
    private function locked(Venue $venue): bool
    {
        return $venue->hasSales();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     *
     * Substitui a planta e re-sincroniza os session_seats das sessões do local
     * (só chamado quando não há vendas — nenhum estado de compra é perdido).
     */
    private function replaceSeats(Venue $venue, array $rows): void
    {
        $now = now();
        DB::transaction(function () use ($venue, $rows, $now): void {
            $sessions = EventSession::whereIn('event_id', $venue->events()->select('id'))->get();

            // Limpa o mapa materializado das sessões antes de trocar os assentos físicos.
            SessionSeat::whereIn('session_id', $sessions->pluck('id'))->delete();
            Seat::where('venue_id', $venue->id)->delete();

            foreach (array_chunk($rows, 200) as $chunk) {
                $chunk = array_map(fn (array $s): array => [
                    ...$s,
                    'venue_id' => $venue->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $chunk);
                DB::table('seats')->insert($chunk);
            }

            // Regenera o mapa de cada sessão a partir da nova planta (setor v1 = 1º do evento).
            foreach ($sessions as $session) {
                $sector = Sector::where('event_id', $session->event_id)->first();
                if ($sector !== null) {
                    $this->seatGenerator->generate($session, $sector, $venue->id);
                }
            }
        });
    }

    private function rowLabel(int $index): string
    {
        $label = '';
        $i = $index + 1;
        while ($i > 0) {
            $i--;
            $label = chr(65 + ($i % 26)).$label;
            $i = intdiv($i, 26);
        }

        return $label;
    }
}

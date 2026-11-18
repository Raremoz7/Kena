<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Contas de comprador no painel: ver os cadastros e liberar um CPF preso numa
 * conta errada (ex.: CPF usado em teste). O CPF completo nunca sai daqui: a
 * lista só mostra os 3 últimos dígitos, mas a busca aceita o número inteiro.
 */
class BuyerController extends Controller
{
    private const PER_PAGE = 25;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('busca', ''));
        $digits = (string) preg_replace('/\D/', '', $search);

        $buyers = User::query()
            ->when($search !== '', function (Builder $q) use ($search, $digits): void {
                if (strlen($digits) === 11) {
                    $q->where('cpf', $digits);

                    return;
                }

                $q->where(fn (Builder $w) => $w
                    ->whereLike('name', "%{$search}%")
                    ->orWhereLike('email', "%{$search}%"));
            })
            ->withCount(['orders as paid_orders_count' => fn (Builder $q) => $q->where('status', Order::STATUS_PAID)])
            ->latest()
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (User $u): array => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'cpf' => self::maskCpf($u->cpf),
                'createdAt' => $u->created_at?->format('d/m/Y'),
                'paidOrders' => (int) $u->paid_orders_count,
            ]);

        return Inertia::render('admin/buyers', [
            'buyers' => $buyers,
            'search' => $search,
        ]);
    }

    /** Tira o CPF da conta, liberando o número para outro cadastro. Não apaga nada. */
    public function unlinkCpf(User $user): RedirectResponse
    {
        if (blank($user->cpf)) {
            return back()->with('status', 'Essa conta já está sem CPF.');
        }

        $user->forceFill(['cpf' => null])->save();

        $panelUser = Auth::guard('painel')->user();
        Log::info('CPF desvinculado pelo painel', [
            'buyer_id' => $user->id,
            'buyer_email' => $user->email,
            'panel_user_id' => $panelUser?->getAuthIdentifier(),
            'panel_user_email' => $panelUser?->email,
        ]);

        return back()->with('status', "CPF desvinculado da conta de {$user->name}.");
    }

    /** Só os 3 últimos dígitos: 52998224725 -> •••.•••.••7-25. */
    public static function maskCpf(?string $cpf): ?string
    {
        if (blank($cpf) || strlen($cpf) !== 11) {
            return null;
        }

        return '•••.•••.••'.$cpf[8].'-'.substr($cpf, 9, 2);
    }
}

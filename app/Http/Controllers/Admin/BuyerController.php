<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Organization;
use App\Models\User;
use App\Support\CurrentOrganization;
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

        $buyers = self::ofCurrentOrganization()
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
                'paidOrders' => (int) $u->getAttribute('paid_orders_count'),
            ]);

        return Inertia::render('admin/buyers', [
            'buyers' => $buyers,
            'search' => $search,
        ]);
    }

    /** Tira o CPF da conta, liberando o número para outro cadastro. Não apaga nada. */
    public function unlinkCpf(User $user): RedirectResponse
    {
        abort_unless(self::ofCurrentOrganization()->whereKey($user->id)->exists(), 404);

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

    /**
     * Conta de comprador é da plataforma, não de um organizador: um organizador
     * novo só vê quem já fez pedido com ele (Order filtra pela organização).
     * A organização principal continua vendo todas as contas, como antes do
     * multi-tenant (inclusive as sem pedido, que é onde costuma ficar o CPF
     * preso em conta errada). Quando o OAuth for ligado e outros organizadores
     * venderem, essa visão completa deve ir para o /admin.
     *
     * @return Builder<User>
     */
    private static function ofCurrentOrganization(): Builder
    {
        $query = User::query();
        $id = CurrentOrganization::id();

        return $id !== null && $id !== Organization::DEFAULT_ID ? $query->whereHas('orders') : $query;
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

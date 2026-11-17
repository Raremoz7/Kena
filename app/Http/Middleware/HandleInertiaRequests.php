<?php

namespace App\Http\Middleware;

use App\Http\Controllers\SuperAdmin\ImpersonationController;
use App\Models\MercadoPagoAccount;
use App\Models\Organization;
use App\Models\PanelUser;
use App\Models\PhotoGallery;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Payments\MercadoPagoOAuth;
use App\Support\Presenters\CatalogPresenter;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            // Identificação do fornecedor no rodapé (Decreto 7.962/2013).
            'company' => [
                'name' => (string) config('kena.legal.name'),
                'document' => config('kena.legal.document') ?: null,
                'address' => config('kena.legal.address') ?: null,
                'email' => config('kena.support.email') ?: null,
            ],
            'auth' => [
                // Comprador (guard web) e conta de painel (guard painel) sao
                // independentes — os dois podem estar nulos ao mesmo tempo.
                // So os campos que o front usa: isto vai em toda pagina.
                'user' => self::buyer($request->user('web')),
                'panelUser' => self::panelUser($request->user('painel')),
                // Super admin (/admin): conta separada da do painel.
                'superAdmin' => $request->user('admin')?->only(['id', 'name', 'email']),
            ],
            // Reserva em andamento do comprador: alimenta o aviso flutuante
            // "retomar ou cancelar" em todas as telas do comprador.
            'activeReservation' => fn (): ?array => self::activeReservation($request->user('web')),
            // As fotos só entram no menu depois do espetáculo, quando existe
            // galeria publicada. Vale para todos, inclusive quem está logado no
            // painel: a equipe confere as fotos pelo próprio painel.
            'photosAvailable' => fn (): bool => PhotoGallery::where('status', 'published')->exists(),
            // Super admin "dentro" de um organizador: aviso fixo no painel.
            'impersonation' => self::impersonation($request),
            // Por que a organização não está vendendo (aviso fixo no painel).
            'organizationAlert' => fn (): ?array => self::organizationAlert($request->user('painel')),
            // Mensagens flash viram toast no front (useFlashToasts).
            'flash' => [
                'success' => $request->session()->get('status'),
                'warning' => $request->session()->get('warning'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }

    /**
     * Conta do painel: papel, se administra a plataforma e o nome da organização.
     *
     * @return array<string, mixed>|null
     */
    private static function panelUser(mixed $user): ?array
    {
        if (! $user instanceof PanelUser) {
            return null;
        }

        return $user->only(['id', 'name', 'email', 'role']) + [
            'organizationName' => $user->organization?->name,
        ];
    }

    /**
     * Aviso para o organizador quando algo impede as vendas. Staff não vê
     * (não pode resolver). Um aviso por vez, do mais grave ao mais leve.
     *
     * @return array{kind: string, message: string, actionUrl: string|null, actionLabel: string|null}|null
     */
    private static function organizationAlert(mixed $user): ?array
    {
        if (! $user instanceof PanelUser || ! $user->canManageOrganization()) {
            return null;
        }
        $organization = Organization::find($user->organization_id);
        if ($organization === null) {
            return null;
        }

        if ($organization->isSuspended()) {
            return [
                'kind' => 'danger',
                'message' => 'As vendas estão suspensas pela plataforma'.($organization->suspended_reason ? " (motivo: {$organization->suspended_reason})" : '').'. Seus eventos saíram da vitrine; pedidos e reembolsos continuam aqui.',
                'actionUrl' => null,
                'actionLabel' => null,
            ];
        }
        if (! MercadoPagoOAuth::enabled()) {
            return null;
        }

        $account = MercadoPagoAccount::forOrganization($organization->id);
        $settings = route('admin.settings');
        if ($account === null) {
            return ['kind' => 'warning', 'message' => 'Seus eventos ainda não aparecem no site: conecte a conta do Mercado Pago que vai receber as vendas.', 'actionUrl' => $settings, 'actionLabel' => 'Conectar'];
        }
        if (! $account->isUsable()) {
            return ['kind' => 'danger', 'message' => 'A conexão com o Mercado Pago venceu e as vendas pararam. Reconecte a conta para voltar a vender.', 'actionUrl' => $settings, 'actionLabel' => 'Reconectar'];
        }
        if ($account->needsReconnect()) {
            return ['kind' => 'warning', 'message' => 'Não conseguimos renovar a conexão com o Mercado Pago. Reconecte antes de '.$account->expires_at?->format('d/m/Y').' para não parar as vendas.', 'actionUrl' => $settings, 'actionLabel' => 'Reconectar'];
        }

        return null;
    }

    /** @return array<string, string>|null */
    private static function impersonation(Request $request): ?array
    {
        $data = $request->session()->get(ImpersonationController::SESSION_KEY);
        if (! is_array($data) || $request->user('painel') === null) {
            return null;
        }

        return [
            'adminName' => (string) ($data['admin_name'] ?? ''),
            'organizationName' => (string) ($data['organization_name'] ?? ''),
            'stopUrl' => route('painel.impersonation.stop'),
        ];
    }

    /** @return array<string, mixed>|null */
    private static function activeReservation(User|PanelUser|null $user): ?array
    {
        if (! $user instanceof User) {
            return null;
        }

        $reservation = Reservation::inProgressFor($user->id);
        if ($reservation === null) {
            return null;
        }

        $reservation->loadMissing(['session.event', 'seats.sessionSeat.seat']);

        return [
            'id' => $reservation->id,
            'eventTitle' => $reservation->session->event->title,
            'sessionLabel' => CatalogPresenter::sessionLabel($reservation->session),
            'seats' => $reservation->seats->map(fn ($rs): string => $rs->sessionSeat->seat->code)->all(),
            'expiresAt' => $reservation->expires_at->toIso8601String(),
            'checkoutUrl' => route('checkout', $reservation, false),
            'releaseUrl' => route('reservations.release', ['reservation' => $reservation, 'voltar' => 1], false),
        ];
    }

    /**
     * Comprador compartilhado com o front. hasCpf: o mapa pede o CPF antes de
     * reservar (limite por CPF), sem expor o número.
     *
     * @return array<string, mixed>|null
     */
    private static function buyer(User|PanelUser|null $user): ?array
    {
        if ($user === null) {
            return null;
        }

        $data = $user->only(['id', 'name', 'email', 'email_verified_at']);
        if ($user instanceof User) {
            $data['hasCpf'] = filled($user->cpf);
        }

        return $data;
    }
}

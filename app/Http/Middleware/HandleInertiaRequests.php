<?php

namespace App\Http\Middleware;

use App\Http\Controllers\SuperAdmin\ImpersonationController;
use App\Models\PanelUser;
use App\Models\PhotoGallery;
use App\Models\Reservation;
use App\Models\User;
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

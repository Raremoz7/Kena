<?php

namespace App\Http\Middleware;

use App\Models\PanelUser;
use App\Models\User;
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
                'user' => self::buyer($request->user()),
                'panelUser' => $request->user('painel')?->only(['id', 'name', 'email', 'role']),
            ],
            // Mensagens flash viram toast no front (useFlashToasts).
            'flash' => [
                'success' => $request->session()->get('status'),
                'warning' => $request->session()->get('warning'),
                'error' => $request->session()->get('error'),
            ],
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

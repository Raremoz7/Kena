<?php

namespace App\Support;

use App\Models\EventSession;
use App\Support\Presenters\CatalogPresenter;
use Illuminate\Support\Facades\Cache;

/**
 * Opções do seletor de sessões do painel de pedidos.
 *
 * O conteúdo é idêntico para toda visita à tela, então fica em cache. O label
 * depende do título do evento e do horário da sessão — por isso os observers
 * (AppServiceProvider) invalidam em qualquer escrita de Event ou EventSession.
 */
final class SessionOptionsCache
{
    public const KEY = 'admin.orders.session_options';

    private const TTL_SECONDS = 3600;

    /** @return array<int, array<string, mixed>> */
    public static function get(): array
    {
        return Cache::remember(self::KEY, self::TTL_SECONDS, fn (): array => EventSession::with('event')
            ->get()
            ->map(fn (EventSession $s): array => [
                'id' => $s->id,
                'label' => $s->event->title.' · '.CatalogPresenter::sessionLabel($s),
            ])->all());
    }

    public static function forget(): void
    {
        Cache::forget(self::KEY);
    }
}

<?php

namespace App\Services;

use App\Models\Ticket;
use App\Support\GoogleWalletSettings;
use App\Support\Presenters\CatalogPresenter;
use RuntimeException;

/**
 * Gera o link "Adicionar ao Google Wallet" para um ingresso: monta um GenericObject
 * e assina um JWT (RS256) com a chave da service account. A classe do passe precisa
 * existir na conta Google (criada uma vez no console/API).
 */
class GoogleWalletPass
{
    private const SAVE_URL = 'https://pay.google.com/gp/v/save/';

    /** Vinho da marca (mesmo tom do "K"). */
    private const BACKGROUND = '#6e0021';

    /** 660x660 com fundo branco: o Wallet recorta o logo em círculo. */
    private const LOGO_PATH = 'img/wallet/kena-logo-660.png';

    public function saveUrl(Ticket $ticket): string
    {
        if (! GoogleWalletSettings::isConfigured()) {
            throw new RuntimeException('Google Wallet não configurado.');
        }

        $issuer = (string) GoogleWalletSettings::issuerId();
        $claims = [
            'iss' => (string) GoogleWalletSettings::serviceAccountEmail(),
            'aud' => 'google',
            'typ' => 'savetowallet',
            'iat' => time(),
            'payload' => ['genericObjects' => [$this->object($ticket, $issuer)]],
        ];

        return self::SAVE_URL.$this->sign($claims, (string) GoogleWalletSettings::privateKey());
    }

    /**
     * @return array<string, mixed>
     */
    private function object(Ticket $ticket, string $issuer): array
    {
        $ticket->loadMissing('session.event.venue');
        $session = $ticket->session;
        $event = $session->event;
        $venue = $event->venue;

        // A frente do cartão (layout da classe) mostra os módulos `seat` e `doors`.
        $modules = [
            ['header' => 'Assento', 'body' => $ticket->sector_name.' · '.$ticket->seat_code, 'id' => 'seat'],
        ];
        if ($session->doors_at !== null) {
            $modules[] = ['header' => 'Portas', 'body' => CatalogPresenter::timeLabel($session->doors_at), 'id' => 'doors'];
        }
        $modules[] = ['header' => 'Titular', 'body' => $ticket->holder_name, 'id' => 'holder'];
        $modules[] = ['header' => 'Local', 'body' => filled($venue->address) ? $venue->name.' — '.$venue->address : $venue->name, 'id' => 'venue'];

        $links = [
            ['id' => 'tickets', 'uri' => route('tickets.index'), 'description' => 'Meus ingressos no Kena'],
        ];
        if (filled($venue->maps_query)) {
            $links[] = [
                'id' => 'maps',
                'uri' => 'https://www.google.com/maps/search/?api=1&query='.rawurlencode((string) $venue->maps_query),
                'description' => 'Como chegar',
            ];
        }

        $object = [
            'id' => $issuer.'.kena-'.$ticket->id,
            'classId' => $issuer.'.'.GoogleWalletSettings::classId(),
            'genericType' => 'GENERIC_TYPE_UNSPECIFIED',
            'state' => 'ACTIVE',
            'hexBackgroundColor' => self::BACKGROUND,
            'logo' => $this->image(self::LOGO_PATH, 'Kena'),
            'cardTitle' => $this->text('Kena'),
            'header' => $this->text($event->title),
            'subheader' => $this->text(CatalogPresenter::sessionLabel($session)),
            'textModulesData' => $modules,
            'linksModuleData' => ['uris' => $links],
            'barcode' => [
                'type' => 'QR_CODE',
                'value' => $ticket->qr_token,
                'alternateText' => $ticket->code,
            ],
        ];

        // Capa 3:1 opcional por evento: public/img/wallet/{slug}-hero.jpg.
        $hero = 'img/wallet/'.$event->slug.'-hero.jpg';
        if (is_file(public_path($hero))) {
            $object['heroImage'] = $this->image($hero, $event->title);
        }

        return $object;
    }

    /** @return array<string, mixed> */
    private function text(string $value): array
    {
        return ['defaultValue' => ['language' => 'pt-BR', 'value' => $value]];
    }

    /** @return array<string, mixed> */
    private function image(string $publicPath, string $description): array
    {
        return [
            'sourceUri' => ['uri' => asset($publicPath)],
            'contentDescription' => $this->text($description),
        ];
    }

    /** @param array<string, mixed> $claims */
    private function sign(array $claims, string $privateKey): string
    {
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $input = $this->segment($header).'.'.$this->segment($claims);

        $signature = '';
        if (! openssl_sign($input, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Falha ao assinar o passe (chave privada inválida?).');
        }

        return $input.'.'.$this->base64Url($signature);
    }

    /** @param array<string, mixed> $data */
    private function segment(array $data): string
    {
        return $this->base64Url((string) json_encode($data));
    }

    private function base64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

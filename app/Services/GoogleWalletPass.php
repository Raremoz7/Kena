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

    /** @return array<string, mixed> */
    private function object(Ticket $ticket, string $issuer): array
    {
        $ticket->loadMissing('session.event');
        $session = $ticket->session;
        $event = $session->event;

        return [
            'id' => $issuer.'.kena-'.$ticket->id,
            'classId' => $issuer.'.'.GoogleWalletSettings::classId(),
            'genericType' => 'GENERIC_TYPE_UNSPECIFIED',
            'state' => 'ACTIVE',
            'hexBackgroundColor' => '#3a1220',
            'cardTitle' => ['defaultValue' => ['language' => 'pt-BR', 'value' => 'Kena']],
            'header' => ['defaultValue' => ['language' => 'pt-BR', 'value' => $event->title]],
            'subheader' => ['defaultValue' => ['language' => 'pt-BR', 'value' => CatalogPresenter::sessionLabel($session)]],
            'textModulesData' => [
                ['header' => 'Assento', 'body' => $ticket->sector_name.' · '.$ticket->seat_code, 'id' => 'seat'],
                ['header' => 'Titular', 'body' => $ticket->holder_name, 'id' => 'holder'],
            ],
            'barcode' => [
                'type' => 'QR_CODE',
                'value' => $ticket->qr_token,
                'alternateText' => $ticket->code,
            ],
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

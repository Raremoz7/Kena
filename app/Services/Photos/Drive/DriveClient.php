<?php

namespace App\Services\Photos\Drive;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Cliente mínimo da API REST do Google Drive v3 com conta de serviço.
 *
 * Sem o pacote google/apiclient (dezenas de classes e dependências para usar
 * três chamadas): o token vem de um JWT assinado com a chave da conta de
 * serviço (openssl, que o PHP já tem) trocado em oauth2.googleapis.com, e a
 * listagem e o download são chamadas HTTP diretas pelo cliente do Laravel.
 * O escopo é somente leitura.
 */
class DriveClient
{
    public const FOLDER_MIME = 'application/vnd.google-apps.folder';

    private const API = 'https://www.googleapis.com/drive/v3';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const SCOPE = 'https://www.googleapis.com/auth/drive.readonly';

    /** @var array{client_email: string, private_key: string}|null */
    private ?array $credentials = null;

    public function __construct(private readonly ?string $credentialsPath) {}

    public static function fromConfig(): self
    {
        $path = config('kena.photos.drive.credentials');

        return new self(is_string($path) && $path !== '' ? $path : null);
    }

    public function isConfigured(): bool
    {
        try {
            $this->credentials();

            return true;
        } catch (DriveException) {
            return false;
        }
    }

    /** E-mail da conta de serviço: a pasta do evento precisa ser compartilhada com ele. */
    public function serviceAccountEmail(): ?string
    {
        try {
            return $this->credentials()['client_email'];
        } catch (DriveException) {
            return null;
        }
    }

    /**
     * Dados da pasta, confirmando que a conta de serviço enxerga e que é pasta.
     *
     * @return array{id: string, name: string}
     */
    public function folder(string $id): array
    {
        $response = $this->get("/files/{$id}", ['fields' => 'id,name,mimeType', 'supportsAllDrives' => 'true']);

        if ($response->status() === 404) {
            throw new DriveException($this->notSharedMessage());
        }
        $this->ensureOk($response);

        $file = $response->json();
        if (($file['mimeType'] ?? null) !== self::FOLDER_MIME) {
            throw new DriveException('O link aponta para um arquivo, não para uma pasta. Copie o link da pasta do evento.');
        }

        return ['id' => $file['id'], 'name' => $file['name']];
    }

    /**
     * Itens diretos de uma pasta (arquivos e subpastas), página a página.
     *
     * @return iterable<array{id: string, name: string, mimeType: string}>
     */
    public function children(string $folderId): iterable
    {
        $pageToken = null;

        do {
            $response = $this->get('/files', array_filter([
                'q' => "'{$folderId}' in parents and trashed = false",
                'fields' => 'nextPageToken, files(id, name, mimeType)',
                'pageSize' => 1000,
                'orderBy' => 'name',
                'supportsAllDrives' => 'true',
                'includeItemsFromAllDrives' => 'true',
                'pageToken' => $pageToken,
            ]));

            if ($response->status() === 404) {
                throw new DriveException($this->notSharedMessage());
            }
            $this->ensureOk($response);

            foreach ($response->json('files', []) as $file) {
                yield $file;
            }

            $pageToken = $response->json('nextPageToken');
        } while ($pageToken);
    }

    /** Baixa o arquivo byte a byte para `$destination` (o XMP chega intacto). */
    public function download(string $fileId, string $destination): void
    {
        try {
            $response = $this->request()
                ->timeout(300)
                ->withOptions(['sink' => $destination])
                ->get(self::API."/files/{$fileId}", ['alt' => 'media', 'supportsAllDrives' => 'true']);
        } catch (ConnectionException $e) {
            throw new DriveException('Não consegui falar com o Google Drive: '.$e->getMessage(), previous: $e);
        }

        if ($response->failed()) {
            @unlink($destination);
            if ($response->status() === 404) {
                throw new DriveException('Arquivo não encontrado no Drive (apagado ou sem acesso para a conta de serviço).');
            }
            $this->ensureOk($response);
        }
    }

    /** @param  array<string, mixed>  $query */
    private function get(string $path, array $query = []): Response
    {
        try {
            return $this->request()->get(self::API.$path, $query);
        } catch (ConnectionException $e) {
            throw new DriveException('Não consegui falar com o Google Drive: '.$e->getMessage(), previous: $e);
        }
    }

    private function request(): PendingRequest
    {
        return Http::withToken($this->accessToken())->acceptJson()->timeout(60);
    }

    private function ensureOk(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $reason = (string) $response->json('error.errors.0.reason', '');
        $status = (string) $response->json('error.status', '');

        if ($reason === 'accessNotConfigured' || $status === 'PERMISSION_DENIED' && str_contains((string) $response->json('error.message'), 'has not been used')) {
            throw new DriveException('A Google Drive API não está ativada no projeto da conta de serviço. Ative-a no Google Cloud (veja docs/fotos/configurar-google-drive.md).');
        }
        if ($response->status() === 403 && in_array($reason, ['rateLimitExceeded', 'userRateLimitExceeded', 'quotaExceeded'], true)) {
            throw new DriveException('O Google Drive pediu para ir mais devagar (limite de requisições). Rode a importação de novo em alguns minutos.');
        }
        if ($response->status() === 403) {
            throw new DriveException($this->notSharedMessage());
        }
        if ($response->status() === 401) {
            Cache::forget($this->tokenCacheKey());
            throw new DriveException('O Google recusou as credenciais da conta de serviço. Confira o arquivo JSON configurado.');
        }

        throw new DriveException(sprintf(
            'Erro %d do Google Drive: %s',
            $response->status(),
            $response->json('error.message') ?? 'resposta inesperada',
        ));
    }

    private function notSharedMessage(): string
    {
        $email = $this->serviceAccountEmail();

        return 'Pasta não encontrada no Drive. Compartilhe a pasta do evento com '
            .($email ?? 'o e-mail da conta de serviço').' (como leitor) e confira o link.';
    }

    private function accessToken(): string
    {
        return Cache::remember($this->tokenCacheKey(), now()->addMinutes(50), function (): string {
            $credentials = $this->credentials();
            $now = time();

            $assertion = self::jwt([
                'iss' => $credentials['client_email'],
                'scope' => self::SCOPE,
                'aud' => self::TOKEN_URL,
                'iat' => $now,
                'exp' => $now + 3600,
            ], $credentials['private_key']);

            try {
                $response = Http::asForm()->timeout(30)->post(self::TOKEN_URL, [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $assertion,
                ]);
            } catch (ConnectionException $e) {
                throw new DriveException('Não consegui falar com o Google: '.$e->getMessage(), previous: $e);
            }

            if ($response->failed() || ! is_string($response->json('access_token'))) {
                throw new DriveException('O Google recusou as credenciais da conta de serviço ('.$response->json('error_description', $response->json('error', 'erro desconhecido')).'). Confira o arquivo JSON configurado.');
            }

            return $response->json('access_token');
        });
    }

    private function tokenCacheKey(): string
    {
        return 'photos.drive.token.'.sha1((string) $this->credentialsPath);
    }

    /** @return array{client_email: string, private_key: string} */
    private function credentials(): array
    {
        if ($this->credentials !== null) {
            return $this->credentials;
        }

        $path = $this->credentialsPath;
        if ($path === null) {
            throw new DriveException('Google Drive não configurado: defina GOOGLE_DRIVE_CREDENTIALS no .env (veja docs/fotos/configurar-google-drive.md).');
        }
        if (! preg_match('#^([A-Za-z]:)?[\\\\/]#', $path)) {
            $path = base_path($path);
        }
        if (! is_file($path) || ! is_readable($path)) {
            throw new DriveException("Arquivo de credenciais do Google Drive não encontrado: {$this->credentialsPath}");
        }

        $json = json_decode((string) file_get_contents($path), true);
        if (! is_array($json) || ! isset($json['client_email'], $json['private_key'])) {
            throw new DriveException('O arquivo de credenciais do Google Drive não é o JSON de uma conta de serviço (faltam client_email e private_key).');
        }

        return $this->credentials = ['client_email' => $json['client_email'], 'private_key' => $json['private_key']];
    }

    /** @param  array<string, mixed>  $claims */
    private static function jwt(array $claims, string $privateKey): string
    {
        $b64 = fn (string $data): string => rtrim(strtr(base64_encode($data), '+/', '-_'), '=');

        $unsigned = $b64((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT']))
            .'.'.$b64((string) json_encode($claims));

        if (! openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new DriveException('A chave privada do arquivo de credenciais do Google Drive é inválida.');
        }

        return $unsigned.'.'.$b64($signature);
    }
}

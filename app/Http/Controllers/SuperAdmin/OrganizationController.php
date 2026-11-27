<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Mail\OrganizationStatusMail;
use App\Mail\PanelPasswordResetMail;
use App\Models\Event;
use App\Models\MercadoPagoAccount;
use App\Models\Order;
use App\Models\Organization;
use App\Models\PanelUser;
use App\Models\PlatformAdmin;
use App\Models\PlatformAuditLog;
use App\Services\Payments\MercadoPagoOAuth;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Super admin: todas as organizações, com vendas, taxas e Mercado Pago.
 * Suspender fecha as vendas na hora, mas mantém o painel do organizador (ele
 * ainda precisa ver pedidos e estornar). /admin roda sem o filtro de
 * organização: é a visão de todas.
 */
class OrganizationController extends Controller
{
    public function index(): Response
    {
        $paid = Order::query()
            ->where('status', Order::STATUS_PAID)
            ->selectRaw('organization_id, count(*) as orders, sum(total_cents) as gross, sum(fee_cents) as fees')
            ->groupBy('organization_id')
            ->get()
            ->keyBy('organization_id');
        $events = Event::query()->selectRaw('organization_id, count(*) as total')->groupBy('organization_id')->pluck('total', 'organization_id');
        $connected = MercadoPagoAccount::query()->whereNull('disconnected_at')->get()
            ->keyBy('organization_id')
            ->map(fn (MercadoPagoAccount $a): string => $a->isUsable() ? ($a->needsReconnect() ? 'expiring' : 'ok') : 'reconnect');

        $organizations = Organization::query()->orderBy('name')->get()->map(function (Organization $org) use ($paid, $events, $connected): array {
            $stats = $paid->get($org->id);
            $owner = self::owner($org);

            return [
                'id' => $org->id,
                'name' => $org->name,
                'slug' => $org->slug,
                'owner' => $owner !== null ? ['name' => $owner->name, 'email' => $owner->email, 'verified' => $owner->email_verified_at !== null] : null,
                'createdAt' => $org->created_at?->toIso8601String(),
                'status' => $org->status,
                'suspendedReason' => $org->suspended_reason,
                'feePercent' => $org->service_fee_percent !== null ? (float) $org->service_fee_percent : null,
                'photoFeePercent' => $org->photo_fee_percent !== null ? (float) $org->photo_fee_percent : null,
                'events' => (int) ($events[$org->id] ?? 0),
                'paidOrders' => (int) ($stats->orders ?? 0),
                'gross' => Money::toReais((int) ($stats->gross ?? 0)),
                'fees' => Money::toReais((int) ($stats->fees ?? 0)),
                'mpConnected' => $connected->has($org->id),
                // ok | expiring (renovação falhou, ainda vale) | reconnect (parou) | none
                'mpStatus' => $connected->get($org->id, 'none'),
            ];
        });

        return Inertia::render('super/organizations', [
            'organizations' => $organizations,
            'defaultFeePercent' => (float) config('kena.service_fee_percent', 6),
            'defaultPhotoFeePercent' => (float) config('kena.photo_fee_percent', 6),
            'oauthEnabled' => MercadoPagoOAuth::enabled(),
        ]);
    }

    /**
     * Cria uma organização com o primeiro organizador (para quando o cadastro
     * aberto estiver desligado). O organizador recebe por e-mail o link para
     * criar a própria senha; ninguém conhece a senha dele.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'organization_name' => ['required', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('panel_users', 'email')],
        ], ['email.unique' => 'Este e-mail já tem conta no painel.']);

        /** @var PanelUser|null $owner */
        $owner = null;
        $organization = DB::transaction(function () use ($data, &$owner): Organization {
            $organization = Organization::create([
                'name' => $data['organization_name'],
                'slug' => Organization::uniqueSlug($data['organization_name']),
                'contact_email' => mb_strtolower(trim($data['email'])),
            ]);
            $owner = PanelUser::create([
                'organization_id' => $organization->id,
                'name' => $data['name'],
                'email' => mb_strtolower(trim($data['email'])),
                'password' => Str::random(40),
                'role' => PanelUser::ROLE_ORGANIZER,
                'email_verified_at' => now(),
            ]);

            return $organization;
        });

        // Convite: link para criar a senha, válido por 7 dias.
        /** @var PanelUser $owner */
        $token = Password::broker('panel_invites')->createToken($owner);
        $url = route('painel.password.reset', ['token' => $token, 'email' => $owner->email, 'convite' => 1]);
        Mail::to($owner->email)->send(new PanelPasswordResetMail(
            (string) $owner->name, $url, (int) config('auth.passwords.panel_invites.expire', 10080), invite: true, organizationName: $organization->name,
        ));
        PlatformAuditLog::record($this->admin(), 'organization.create', $organization->id, ['email' => $data['email']]);

        return back()->with('status', "{$organization->name} criada. Enviamos para {$data['email']} o link para criar a senha.");
    }

    public function suspend(Request $request, Organization $organization): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        $organization->update([
            'status' => Organization::STATUS_SUSPENDED,
            'suspended_at' => now(),
            'suspended_reason' => $data['reason'],
        ]);
        PlatformAuditLog::record($this->admin(), 'organization.suspend', $organization->id, ['reason' => $data['reason']]);
        self::notifyOrganizers($organization, new OrganizationStatusMail($organization->name, true, $data['reason']));

        return back()->with('status', "Vendas de {$organization->name} suspensas.");
    }

    public function reactivate(Organization $organization): RedirectResponse
    {
        $organization->update(['status' => Organization::STATUS_ACTIVE, 'suspended_at' => null, 'suspended_reason' => null]);
        PlatformAuditLog::record($this->admin(), 'organization.reactivate', $organization->id);
        self::notifyOrganizers($organization, new OrganizationStatusMail($organization->name, false));

        return back()->with('status', "{$organization->name} voltou a vender.");
    }

    /**
     * Taxas negociadas: ingresso e fotos são independentes (mudar uma não mexe
     * na outra). Em branco = volta para a taxa padrão da plataforma.
     */
    public function fee(Request $request, Organization $organization): RedirectResponse
    {
        $data = $request->validate([
            'fee_percent' => ['nullable', 'numeric', 'min:0', 'max:30'],
            'photo_fee_percent' => ['nullable', 'numeric', 'min:0', 'max:30'],
        ]);

        $changes = [];
        if ($request->exists('fee_percent')) {
            $changes['service_fee_percent'] = $data['fee_percent'] ?? null;
        }
        if ($request->exists('photo_fee_percent')) {
            $changes['photo_fee_percent'] = $data['photo_fee_percent'] ?? null;
        }
        $organization->update($changes);
        PlatformAuditLog::record($this->admin(), 'organization.fee', $organization->id, $changes);

        return back()->with('status', "Taxa de {$organization->name} atualizada.");
    }

    /** Avisa os organizadores (não a equipe de check-in) da organização. */
    private static function notifyOrganizers(Organization $organization, OrganizationStatusMail $mail): void
    {
        $emails = PanelUser::query()
            ->where('organization_id', $organization->id)
            ->where('role', PanelUser::ROLE_ORGANIZER)
            ->pluck('email')
            ->all();

        if ($emails !== []) {
            Mail::to($emails)->queue($mail);
        }
    }

    /** Primeiro organizador da organização (quem o "entrar como" assume). */
    public static function owner(Organization $organization): ?PanelUser
    {
        return PanelUser::query()
            ->where('organization_id', $organization->id)
            ->where('role', PanelUser::ROLE_ORGANIZER)
            ->orderBy('id')
            ->first();
    }

    private function admin(): ?PlatformAdmin
    {
        $admin = Auth::guard('admin')->user();

        return $admin instanceof PlatformAdmin ? $admin : null;
    }
}

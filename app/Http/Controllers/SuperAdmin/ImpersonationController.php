<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\PlatformAdmin;
use App\Models\PlatformAuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * "Entrar como" organizador: o super admin abre o painel da organização como
 * o organizador dela. Fica um aviso fixo no painel e entrada/saída ficam no
 * registro (platform_audit_logs). As ações feitas lá aparecem como do
 * organizador: o registro diz quem estava de fato no controle e quando.
 */
class ImpersonationController extends Controller
{
    public const SESSION_KEY = 'impersonator';

    public function start(Request $request, Organization $organization): RedirectResponse
    {
        /** @var PlatformAdmin $admin */
        $admin = Auth::guard('admin')->user();
        $owner = OrganizationController::owner($organization);

        if ($owner === null) {
            return back()->with('error', "{$organization->name} não tem organizador para entrar.");
        }

        Auth::guard('painel')->login($owner);
        $request->session()->put(self::SESSION_KEY, [
            'admin_id' => $admin->id,
            'admin_name' => $admin->name,
            'organization_id' => $organization->id,
            'organization_name' => $organization->name,
        ]);
        PlatformAuditLog::record($admin, 'impersonate.start', $organization->id, ['panel_user_id' => $owner->id]);

        return redirect()->route('painel');
    }

    /** Sai do painel do organizador e volta para o /admin. */
    public function stop(Request $request): RedirectResponse
    {
        $data = $request->session()->pull(self::SESSION_KEY);
        abort_unless(is_array($data), 404);

        Auth::guard('painel')->logout();
        $admin = PlatformAdmin::query()->whereKey((int) ($data['admin_id'] ?? 0))->first();
        $organizationId = isset($data['organization_id']) ? (int) $data['organization_id'] : null;
        PlatformAuditLog::record($admin, 'impersonate.stop', $organizationId);

        return redirect()->route('super.organizations');
    }
}

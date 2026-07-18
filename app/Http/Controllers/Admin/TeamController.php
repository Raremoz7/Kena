<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PanelUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gestão de equipe: contas do painel (organizador e staff de portaria). Só
 * organizador alcança esta tela — staff fica no check-in.
 *
 * Contas de painel são independentes das de comprador: criar alguém aqui não
 * dá acesso à loja, e remover daqui não apaga compras — são tabelas diferentes.
 */
class TeamController extends Controller
{
    private const MANAGEABLE_ROLES = [PanelUser::ROLE_ORGANIZER, PanelUser::ROLE_STAFF];

    private const PER_PAGE = 25;

    public function index(): Response
    {
        $members = PanelUser::orderBy('role')
            ->orderBy('name')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (PanelUser $u): array => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role,
                'isSelf' => $u->id === Auth::guard('painel')->id(),
            ]);

        return Inertia::render('admin/team', [
            'members' => $members,
            'roles' => [
                ['value' => PanelUser::ROLE_ORGANIZER, 'label' => 'Organizador (painel completo)'],
                ['value' => PanelUser::ROLE_STAFF, 'label' => 'Staff (só check-in)'],
            ],
        ]);
    }

    /**
     * Cria a conta de painel. O painel só tem e-mail+senha (sem magic link nem
     * "esqueci a senha"), então quem convida define a senha inicial e a repassa.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('panel_users', 'email')],
            'role' => ['required', Rule::in(self::MANAGEABLE_ROLES)],
            'password' => ['required', 'string', 'min:8'],
        ]);

        PanelUser::create([
            'name' => $data['name'],
            'email' => mb_strtolower(trim($data['email'])),
            'role' => $data['role'],
            'password' => $data['password'],
        ]);

        return back()->with('status', "{$data['name']} agora tem acesso ao painel como ".$this->roleLabel($data['role']).'.');
    }

    /** Troca o papel de um membro. */
    public function update(Request $request, PanelUser $panelUser): RedirectResponse
    {
        $data = $request->validate(['role' => ['required', Rule::in(self::MANAGEABLE_ROLES)]]);

        if ($panelUser->id === Auth::guard('painel')->id()) {
            return back()->withErrors(['role' => 'Você não pode alterar seu próprio papel.']);
        }

        $panelUser->update(['role' => $data['role']]);

        return back()->with('status', 'Papel atualizado.');
    }

    /** Remove do painel — apaga a conta de acesso, não a de comprador. */
    public function destroy(PanelUser $panelUser): RedirectResponse
    {
        if ($panelUser->id === Auth::guard('painel')->id()) {
            return back()->withErrors(['team' => 'Você não pode remover a si mesmo da equipe.']);
        }

        // Não deixa o painel ficar sem ninguém que consiga administrá-lo.
        $isLastOrganizer = $panelUser->role === PanelUser::ROLE_ORGANIZER
            && PanelUser::where('role', PanelUser::ROLE_ORGANIZER)->count() === 1;

        if ($isLastOrganizer) {
            return back()->withErrors(['team' => 'Não é possível remover o último organizador.']);
        }

        $panelUser->delete();

        return back()->with('status', 'Acesso ao painel removido.');
    }

    private function roleLabel(string $role): string
    {
        return $role === PanelUser::ROLE_ORGANIZER ? 'organizador' : 'staff';
    }
}

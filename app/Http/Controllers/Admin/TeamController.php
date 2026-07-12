<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gestão de equipe: organizadores e staff (portaria). Organizador/admin convida,
 * promove e remove membros — staff só faz check-in (não alcança esta tela).
 */
class TeamController extends Controller
{
    private const MANAGEABLE_ROLES = [User::ROLE_ORGANIZER, User::ROLE_STAFF];

    private const PER_PAGE = 25;

    public function index(): Response
    {
        $members = User::whereIn('role', self::MANAGEABLE_ROLES)
            ->orWhere('is_admin', true)
            ->orderByDesc('is_admin')
            ->orderBy('name')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (User $u): array => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role,
                'isAdmin' => $u->is_admin,
                'isSelf' => $u->id === Auth::id(),
            ]);

        return Inertia::render('admin/team', [
            'members' => $members,
            'roles' => [
                ['value' => User::ROLE_ORGANIZER, 'label' => 'Organizador (painel completo)'],
                ['value' => User::ROLE_STAFF, 'label' => 'Staff (só check-in)'],
            ],
        ]);
    }

    /** Convida (cria conta leve) ou promove um usuário existente a membro da equipe. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::in(self::MANAGEABLE_ROLES)],
        ]);

        $email = mb_strtolower(trim($data['email']));
        $existing = User::where('email', $email)->first();

        if ($existing !== null) {
            $existing->update(['role' => $data['role']]);

            return back()->with('status', "{$existing->name} agora é ".$this->roleLabel($data['role']).'.');
        }

        // Conta leve sem senha: o membro define a senha via "esqueci a senha".
        User::create([
            'name' => $data['name'],
            'email' => $email,
            'role' => $data['role'],
            'email_verified_at' => now(),
        ]);

        return back()->with('status', 'Convite criado. Peça ao membro para definir a senha em "Esqueci a senha".');
    }

    /** Troca o papel de um membro. */
    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['role' => ['required', Rule::in(self::MANAGEABLE_ROLES)]]);

        if ($user->id === Auth::id()) {
            return back()->withErrors(['role' => 'Você não pode alterar seu próprio papel.']);
        }
        if ($user->is_admin && ! Auth::user()->is_admin) {
            return back()->withErrors(['role' => 'Apenas um admin pode alterar outro admin.']);
        }

        $user->update(['role' => $data['role']]);

        return back()->with('status', 'Papel atualizado.');
    }

    /** Remove da equipe (volta a ser comprador comum). */
    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === Auth::id()) {
            return back()->withErrors(['team' => 'Você não pode remover a si mesmo da equipe.']);
        }
        if ($user->is_admin && ! Auth::user()->is_admin) {
            return back()->withErrors(['team' => 'Apenas um admin pode remover outro admin.']);
        }

        $user->update(['role' => User::ROLE_BUYER, 'is_admin' => false]);

        return back()->with('status', 'Membro removido da equipe.');
    }

    private function roleLabel(string $role): string
    {
        return $role === User::ROLE_ORGANIZER ? 'organizador' : 'staff';
    }
}

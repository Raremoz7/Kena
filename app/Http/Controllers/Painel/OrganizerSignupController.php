<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use App\Mail\OrganizerVerifyEmailMail;
use App\Models\Organization;
use App\Models\PanelUser;
use App\Rules\EmailDomainExists;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Cadastro aberto de organizador (modelo Sympla): cria a organização e a conta
 * do painel; entra depois de confirmar o e-mail. Para vender ainda precisa
 * conectar o Mercado Pago. Só existe com KENA_ORGANIZER_SIGNUP_ENABLED e o
 * OAuth do Mercado Pago ligados: sem OAuth a venda cairia na conta da plataforma.
 */
class OrganizerSignupController extends Controller
{
    private const LINK_TTL_HOURS = 24;

    public static function enabled(): bool
    {
        return (bool) config('kena.organizer_signup.enabled', false)
            && (bool) config('kena.mercadopago.oauth.enabled', false);
    }

    public function show(): Response
    {
        abort_unless(self::enabled(), 404);

        return Inertia::render('painel/signup');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(self::enabled(), 404);

        $data = $request->validate([
            'organization_name' => ['required', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', new EmailDomainExists, Rule::unique('panel_users', 'email')],
            'password' => ['required', 'confirmed', Password::defaults()],
            'accept_terms' => ['accepted'],
        ], [
            'email.unique' => 'Este e-mail já tem conta no painel. Entre com ele.',
            'accept_terms.accepted' => 'É preciso aceitar os termos para criar a conta.',
        ]);

        $user = DB::transaction(function () use ($data): PanelUser {
            $organization = Organization::create([
                'name' => $data['organization_name'],
                'slug' => Organization::uniqueSlug($data['organization_name']),
                'contact_email' => $data['email'],
                'terms_accepted_at' => now(),
            ]);

            return PanelUser::create([
                'organization_id' => $organization->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => PanelUser::ROLE_ORGANIZER,
            ]);
        });

        self::sendVerification($user);

        return redirect()->route('painel.login')->with('status', 'Conta criada! Enviamos um link para '.$user->email.'. Confirme o e-mail para entrar.');
    }

    /** Link assinado do e-mail: confirma e já entra no painel, na tela de conectar o Mercado Pago. */
    public function verify(Request $request, PanelUser $panelUser): RedirectResponse
    {
        // Uso único: depois de confirmado, o link não entra mais sozinho no painel.
        if ($panelUser->email_verified_at !== null) {
            return redirect()->route('painel.login')->with('status', 'E-mail já confirmado. Entre com sua senha.');
        }

        $panelUser->forceFill(['email_verified_at' => now()])->save();
        Auth::guard('painel')->login($panelUser);
        $request->session()->regenerate();

        return redirect()->route('admin.settings');
    }

    public static function sendVerification(PanelUser $user): void
    {
        $url = URL::temporarySignedRoute('organizador.verificar', now()->addHours(self::LINK_TTL_HOURS), ['panelUser' => $user->id]);

        Mail::to($user->email)->send(new OrganizerVerifyEmailMail((string) $user->name, $url, self::LINK_TTL_HOURS));
    }
}

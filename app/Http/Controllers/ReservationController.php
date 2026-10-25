<?php

namespace App\Http\Controllers;

use App\Exceptions\CpfInUseException;
use App\Exceptions\GuestAccountExistsException;
use App\Exceptions\LotsSoldOutException;
use App\Exceptions\SeatConflictException;
use App\Exceptions\SeatLimitException;
use App\Models\EventSession;
use App\Models\Reservation;
use App\Models\User;
use App\Rules\EmailDomainExists;
use App\Rules\ValidCpf;
use App\Services\EmailVerificationCodeService;
use App\Services\GuestIdentityService;
use App\Services\SeatReservationService;
use App\Support\AccountNotice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReservationController extends Controller
{
    public function __construct(
        private readonly SeatReservationService $reservations,
        private readonly GuestIdentityService $guests,
        private readonly EmailVerificationCodeService $codes,
    ) {}

    /** Cria o hold a partir da seleção e leva ao checkout. Aceita convidado (sem conta). */
    public function reserve(Request $request, string $slug, EventSession $session): RedirectResponse
    {
        $session->load('event');
        abort_if($session->event->slug !== $slug, 404);
        abort_unless($session->isSellable(), 410, 'As vendas desta sessão estão encerradas.');

        $max = $this->reservations->maxTicketsPerBuyer();
        $rules = [
            'seats' => ['required', 'array', 'min:1', 'max:'.$max],
            'seats.*' => ['integer'],
        ];
        if (! Auth::check()) {
            $rules += [
                'guest.email' => ['required', 'email', 'max:255', new EmailDomainExists],
                'guest.name' => ['required', 'string', 'max:120'],
                'guest.cpf' => ['required', 'string', new ValidCpf],
                'guest.code' => ['required', 'digits:6'],
            ];
        } elseif (blank($request->user('web')?->cpf)) {
            // Conta sem CPF (ex.: login com Google): o limite é por CPF, então pede antes de reservar.
            $rules['cpf'] = ['required', 'string', new ValidCpf];
        }
        $validated = $request->validate($rules, [
            'seats.max' => "Cada CPF pode levar até {$max} ingressos por sessão.",
            'cpf.required' => 'Informe seu CPF para reservar.',
            'guest.code.required' => 'Digite o código que enviamos para o seu e-mail.',
            'guest.code.digits' => 'O código tem 6 números.',
        ]);

        // Convidado: confirma o e-mail pelo código, cria/reusa a conta leve e autentica.
        if (! Auth::check()) {
            if (! $this->codes->verify($validated['guest']['email'], $validated['guest']['code'])) {
                return back()->withErrors([
                    'guest.code' => 'Código inválido ou expirado. Confira o e-mail ou peça um novo código.',
                ]);
            }

            try {
                $user = $this->guests->identify($validated['guest']);
            } catch (GuestAccountExistsException) {
                return back()->withErrors([
                    'guest.email' => (AccountNotice::forEmail((string) $validated['guest']['email'])['message'] ?? 'Este e-mail já tem conta.').' Depois é só voltar aos assentos.',
                ]);
            } catch (CpfInUseException) {
                return back()->withErrors([
                    'guest.cpf' => 'Esse CPF já está em outra conta. Entre com o e-mail dela para continuar.',
                ]);
            }
            Auth::login($user);
        } elseif (isset($validated['cpf'])) {
            /** @var User $user */
            $user = $request->user('web');
            $cpf = (string) preg_replace('/\D/', '', $validated['cpf']);
            if (User::where('cpf', $cpf)->whereKeyNot($user->id)->exists()) {
                return back()->withErrors(['cpf' => 'Esse CPF já está em outra conta. Entre com o e-mail dela para continuar.']);
            }
            $user->update(['cpf' => $cpf]);
        }

        try {
            $reservation = $this->reservations->hold($session, $request->user('web'), $validated['seats']);
        } catch (SeatConflictException $e) {
            return back()->withErrors([
                'seats' => $e->seatCodes === []
                    ? 'Selecione ao menos um assento disponível.'
                    : 'Estes assentos acabaram de ser reservados: '.implode(', ', $e->seatCodes).'. Escolha outros.',
            ]);
        } catch (LotsSoldOutException $e) {
            return back()->withErrors([
                'seats' => $e->remaining === 0
                    ? 'Os ingressos desta sessão esgotaram.'
                    : "Só restam {$e->remaining} ingressos à venda. Selecione menos assentos.",
            ]);
        } catch (SeatLimitException $e) {
            return back()->withErrors([
                'seats' => $e->remaining === 0
                    ? "Este CPF já tem {$e->max} ingressos para esta sessão, o máximo permitido."
                    : "Cada CPF pode levar até {$e->max} ingressos por sessão. Você ainda pode escolher {$e->remaining}.",
            ]);
        }

        return redirect()->route('checkout', $reservation);
    }

    /** Libera a reserva ativa do próprio usuário. */
    public function release(Reservation $reservation): RedirectResponse
    {
        abort_unless($reservation->user_id === Auth::id(), 403);

        $this->reservations->release($reservation);

        return redirect()->route('events.show', $reservation->session->event->slug);
    }
}

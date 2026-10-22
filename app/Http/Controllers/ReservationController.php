<?php

namespace App\Http\Controllers;

use App\Exceptions\GuestAccountExistsException;
use App\Exceptions\GuestAccountNeedsVerificationException;
use App\Exceptions\SeatConflictException;
use App\Exceptions\SeatLimitException;
use App\Mail\GuestAccessLinkMail;
use App\Models\EventSession;
use App\Models\Reservation;
use App\Rules\ValidCpf;
use App\Services\GuestIdentityService;
use App\Services\SeatReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class ReservationController extends Controller
{
    private const ACCESS_LINKS_PER_WINDOW = 3;

    private const ACCESS_LINK_WINDOW_SECONDS = 600;

    public function __construct(
        private readonly SeatReservationService $reservations,
        private readonly GuestIdentityService $guests,
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
                'guest.email' => ['required', 'email', 'max:255'],
                'guest.name' => ['required', 'string', 'max:120'],
                'guest.cpf' => ['required', 'string', new ValidCpf],
            ];
        }
        $validated = $request->validate($rules, [
            'seats.max' => "Cada comprador pode levar até {$max} ingressos.",
        ]);

        // Convidado: cria/reusa conta leve e autentica antes de seguir.
        if (! Auth::check()) {
            try {
                $user = $this->guests->identify($validated['guest']);
            } catch (GuestAccountExistsException) {
                return back()->withErrors([
                    'guest.email' => 'Esse e-mail já tem uma conta. Faça login para continuar a compra.',
                ]);
            } catch (GuestAccountNeedsVerificationException $e) {
                // Limite por e-mail: ninguém usa o formulário para lotar a caixa de outra pessoa.
                RateLimiter::attempt(
                    'guest-access-link:'.$e->user->id,
                    self::ACCESS_LINKS_PER_WINDOW,
                    fn () => Mail::to($e->user)->send(new GuestAccessLinkMail($e->user, $session)),
                    self::ACCESS_LINK_WINDOW_SECONDS,
                );

                return back()->withErrors([
                    'guest.email' => 'Esse e-mail já tem uma conta Kena. Enviamos um link de acesso para ele — abra o link para continuar a compra.',
                ]);
            }
            Auth::login($user);
        }

        try {
            $reservation = $this->reservations->hold($session, $request->user('web'), $validated['seats']);
        } catch (SeatConflictException $e) {
            return back()->withErrors([
                'seats' => $e->seatCodes === []
                    ? 'Selecione ao menos um assento disponível.'
                    : 'Estes assentos acabaram de ser reservados: '.implode(', ', $e->seatCodes).'. Escolha outros.',
            ]);
        } catch (SeatLimitException $e) {
            return back()->withErrors([
                'seats' => $e->remaining === 0
                    ? "Você já tem {$e->max} ingressos para esta sessão, o máximo por comprador."
                    : "Cada comprador pode levar até {$e->max} ingressos. Você ainda pode escolher {$e->remaining}.",
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

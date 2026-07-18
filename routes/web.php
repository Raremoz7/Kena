<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\CheckInController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\EventController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\SeatController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Admin\VenueController;
use App\Http\Controllers\Admin\VenueSeatController;
use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\BuyerController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\Dev\MailPreviewController;
use App\Http\Controllers\MagicLoginController;
use App\Http\Controllers\Painel\PanelLoginController;
use App\Http\Controllers\PasswordSetupController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

// Público — descoberta e seleção
Route::get('/', [BuyerController::class, 'events'])->name('home');
Route::get('/eventos', [BuyerController::class, 'events'])->name('events.index');
Route::redirect('/agenda', '/eventos');
Route::get('/e/{slug}', [BuyerController::class, 'event'])->name('events.show');
Route::get('/e/{slug}/sessoes/{session}', [BuyerController::class, 'seats'])->name('sessions.seats');

// Disponibilidade (polling do mapa) — público
Route::get('/api/sessoes/{session}/disponibilidade', [AvailabilityController::class, 'show'])
    ->name('api.sessions.availability');

// Webhook do Mercado Pago (público, idempotente, isento de CSRF)
Route::post('/webhooks/mercadopago', [WebhookController::class, 'mercadopago'])
    ->middleware('throttle:120,1')
    ->name('webhooks.mercadopago');

// Style guide (referência de DS)
Route::get('/style-guide', [BuyerController::class, 'styleGuide'])->name('style-guide');

// Login social
Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');

// Reserva (hold) — pública: aceita convidado (cria conta leve) ou usuário logado
Route::post('/e/{slug}/sessoes/{session}/reservar', [ReservationController::class, 'reserve'])
    ->middleware('throttle:20,1')
    ->name('sessions.reserve');

// Magic-link (login sem senha por e-mail, assinado)
Route::get('/entrar/{user}', [MagicLoginController::class, 'login'])->name('magic-login')->middleware('signed');

// Autenticado como comprador — checkout / ingressos. O guard é explícito:
// com dois guards no sistema, 'auth' sozinho resolveria o padrão e deixaria
// uma conta de painel entrar aqui.
Route::middleware(['auth:web'])->group(function () {
    Route::delete('/reservas/{reservation}', [ReservationController::class, 'release'])
        ->name('reservations.release');

    Route::get('/checkout/{reservation}', [CheckoutController::class, 'show'])->name('checkout');
    Route::post('/checkout/{reservation}/cupom', [CheckoutController::class, 'coupon'])->name('checkout.coupon');
    Route::post('/checkout/{reservation}/pagar', [CheckoutController::class, 'pay'])->name('checkout.pay');
    Route::get('/checkout/{reservation}/status', [CheckoutController::class, 'status'])->name('checkout.status');

    Route::get('/meus-ingressos', [BuyerController::class, 'tickets'])->name('tickets.index');
    Route::get('/definir-senha', [PasswordSetupController::class, 'show'])->name('password.setup');
    Route::post('/definir-senha', [PasswordSetupController::class, 'store'])->name('password.setup.store');
    Route::post('/api/ingressos/{ticket}/transferir', [TicketController::class, 'transfer'])->name('tickets.transfer');
    Route::get('/ingressos/{ticket}/agenda.ics', [TicketController::class, 'calendar'])->name('tickets.calendar');
    Route::get('/ingressos/{ticket}/google-wallet', [TicketController::class, 'googleWallet'])->name('tickets.google-wallet');
    Route::post('/api/pedidos/{order}/reembolso', [TicketController::class, 'refund'])->name('orders.refund');
});

// Preview local dos e-mails transacionais (bloqueado fora de local/testing)
Route::get('/dev/mail-preview/{type}', [MailPreviewController::class, 'show'])
    ->name('dev.mail-preview');

// Login do painel — guard próprio, independente do login do comprador.
Route::middleware('guest:painel')->group(function () {
    Route::get('/painel/login', [PanelLoginController::class, 'show'])->name('painel.login');
    Route::post('/painel/login', [PanelLoginController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('painel.login.store');
});
Route::post('/painel/logout', [PanelLoginController::class, 'destroy'])->name('painel.logout');

// Painel — shell acessível a organizer/staff. Staff só alcança o check-in.
Route::middleware(['auth:painel', 'can-manage'])->group(function () {
    Route::get('/painel', [AdminController::class, 'overview'])->name('painel');

    // Check-in scanner (staff + organizer)
    Route::get('/painel/checkin', [CheckInController::class, 'show'])->name('admin.checkin');
    Route::post('/api/checkin', [CheckInController::class, 'scan'])->name('admin.checkin.scan');
    Route::post('/api/checkin/buscar', [CheckInController::class, 'lookup'])->name('admin.checkin.lookup');
    Route::post('/api/checkin/admitir', [CheckInController::class, 'admit'])->name('admin.checkin.admit');

    // Gestão sensível — só organizer/admin (staff recebe 403).
    Route::middleware('can-organize')->group(function () {
        Route::get('/painel/eventos', [AdminController::class, 'events'])->name('admin.events');
        Route::get('/painel/eventos/novo', [EventController::class, 'create'])->name('admin.events.create');
        Route::post('/painel/eventos', [EventController::class, 'store'])->name('admin.events.store');
        Route::get('/painel/eventos/{event}/editar', [EventController::class, 'edit'])->name('admin.events.edit');
        Route::put('/painel/eventos/{event}', [EventController::class, 'update'])->name('admin.events.update');
        Route::delete('/painel/eventos/{event}', [EventController::class, 'destroy'])->name('admin.events.destroy');

        // Pedidos + export de participantes + reembolso pelo organizador
        Route::get('/painel/pedidos', [OrderController::class, 'index'])->name('admin.orders');
        Route::get('/painel/pedidos/export', [OrderController::class, 'exportAttendees'])->name('admin.orders.export');
        Route::post('/painel/pedidos/{order}/reembolso', [OrderController::class, 'refund'])->name('admin.orders.refund');
        Route::post('/painel/sessoes/{session}/cancelar', [OrderController::class, 'cancelSession'])->name('admin.sessions.cancel');

        // Cupons de desconto
        Route::get('/painel/cupons', [CouponController::class, 'index'])->name('admin.coupons');
        Route::get('/painel/cupons/novo', [CouponController::class, 'create'])->name('admin.coupons.create');
        Route::post('/painel/cupons', [CouponController::class, 'store'])->name('admin.coupons.store');
        Route::get('/painel/cupons/{coupon}/editar', [CouponController::class, 'edit'])->name('admin.coupons.edit');
        Route::put('/painel/cupons/{coupon}', [CouponController::class, 'update'])->name('admin.coupons.update');
        Route::delete('/painel/cupons/{coupon}', [CouponController::class, 'destroy'])->name('admin.coupons.destroy');

        // Locais (venues)
        Route::get('/painel/locais', [VenueController::class, 'index'])->name('admin.venues');
        Route::get('/painel/locais/novo', [VenueController::class, 'create'])->name('admin.venues.create');
        Route::post('/painel/locais', [VenueController::class, 'store'])->name('admin.venues.store');
        Route::get('/painel/locais/{venue}/editar', [VenueController::class, 'edit'])->name('admin.venues.edit');
        Route::put('/painel/locais/{venue}', [VenueController::class, 'update'])->name('admin.venues.update');
        Route::delete('/painel/locais/{venue}', [VenueController::class, 'destroy'])->name('admin.venues.destroy');
        Route::post('/painel/locais/{venue}/assentos/importar', [VenueSeatController::class, 'import'])->name('admin.venues.seats.import');
        Route::post('/painel/locais/{venue}/assentos/gerar', [VenueSeatController::class, 'generateGrid'])->name('admin.venues.seats.generate');

        // Gestão de assentos (bloquear/liberar cortesias e lugares interditados)
        Route::get('/painel/sessoes/{session}/assentos', [SeatController::class, 'show'])->name('admin.seats');
        Route::post('/painel/assentos/toggle', [SeatController::class, 'toggle'])->name('admin.seats.toggle');

        // Equipe (organizadores e staff)
        Route::get('/painel/equipe', [TeamController::class, 'index'])->name('admin.team');
        Route::post('/painel/equipe', [TeamController::class, 'store'])->name('admin.team.store');
        Route::put('/painel/equipe/{panelUser}', [TeamController::class, 'update'])->name('admin.team.update');
        Route::delete('/painel/equipe/{panelUser}', [TeamController::class, 'destroy'])->name('admin.team.destroy');

        Route::get('/painel/config', [SettingsController::class, 'show'])->name('admin.settings');
        Route::post('/painel/config', [SettingsController::class, 'update'])->name('admin.settings.update');
        Route::post('/painel/config/testar-email', [SettingsController::class, 'testMail'])->name('admin.settings.test-mail');
        Route::post('/painel/config/testar-mercadopago', [SettingsController::class, 'testMercadoPago'])->name('admin.settings.test-mp');
    });
});

require __DIR__.'/settings.php';

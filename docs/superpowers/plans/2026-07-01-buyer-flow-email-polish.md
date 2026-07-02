# Polish do fluxo do comprador (e-mails + UI) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Dar identidade visual (fundo escuro + tipografia Kena, canhoto de ingresso perfurado) aos 4 e-mails transacionais que hoje usam o tema azul padrão do Laravel, e fechar 2 ajustes soltos de UI (modal de confirmação de reembolso, toast de sucesso ao definir senha).

**Architecture:** Os 4 `Mailable`s passam de `Content(markdown: ...)` para `Content(view: ...)` (HTML 100% controlado, sem o parser Markdown/CommonMark do meio do caminho corrompendo marcação). As views usam 4 componentes Blade compartilhados em `resources/views/components/mail/` (layout, botão, canhoto de ingresso, painel de informação) com cores fixas em hex (clientes de e-mail não suportam `oklch()`/CSS vars). No frontend, troca-se `window.confirm()` por um `Modal` já existente no design system, e adiciona-se um toast de sucesso que já tinha o padrão pronto em outras telas.

**Tech Stack:** Laravel 12/13 Mailables + Blade components, Pest/PHPUnit, React 19 + Inertia + TypeScript (sem mudança de stack).

---

## Antes de começar

Todos os comandos abaixo rodam dentro do projeto em WSL. Prefixe com:

```bash
wsl bash -lc "cd ~/Projetos/Ingresso && <comando>"
```

(omitido nos passos abaixo por brevidade — use sempre esse prefixo ao executar)

---

### Task 1: Componentes Blade compartilhados + e-mail "Ingressos emitidos"

**Files:**
- Create: `resources/views/components/mail/layout.blade.php`
- Create: `resources/views/components/mail/button.blade.php`
- Create: `resources/views/components/mail/ticket-stub.blade.php`
- Modify: `resources/views/mail/tickets-issued.blade.php`
- Modify: `app/Mail/TicketsIssuedMail.php:29-40` (troca `markdown:` por `view:`)
- Test: `tests/Feature/Kena/MailVisualIdentityTest.php` (novo arquivo)

- [ ] **Step 1: Escrever o teste que falha**

Criar `tests/Feature/Kena/MailVisualIdentityTest.php`:

```php
<?php

namespace Tests\Feature\Kena;

use App\Mail\EventReminderMail;
use App\Mail\RefundConfirmedMail;
use App\Mail\TicketsIssuedMail;
use App\Mail\TicketTransferredMail;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use App\Services\SeatReservationService;
use App\Services\TicketIssuanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\TestCase;

class MailVisualIdentityTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    private function paidOrder(): Order
    {
        Mail::fake();
        $session = $this->makeSession(1);
        $buyer = User::factory()->create();
        $reservation = app(SeatReservationService::class)
            ->hold($session, $buyer, $this->availableSeatIds($session, 1));
        $order = app(OrderService::class)->createFromReservation($reservation);
        app(TicketIssuanceService::class)->issueForOrder($order);

        return $order->refresh();
    }

    public function test_tickets_issued_uses_kena_dark_theme(): void
    {
        $html = (new TicketsIssuedMail($this->paidOrder()))->render();

        $this->assertStringContainsString('#120C08', $html);
        $this->assertStringContainsString('KENA', $html);
        $this->assertStringContainsString('Pagamento aprovado', $html);
    }
}
```

- [ ] **Step 2: Rodar e confirmar que falha**

Run: `php artisan test --filter=test_tickets_issued_uses_kena_dark_theme`
Expected: FAIL (o HTML atual não contém `#120C08` — ainda é o tema azul padrão do Laravel).

- [ ] **Step 3: Criar o componente de layout**

Criar `resources/views/components/mail/layout.blade.php`:

```blade
@props(['title' => 'Kena'])
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Oswald:wght@500;700&family=Hanken+Grotesk:wght@400;600&display=swap" rel="stylesheet">
<title>{{ $title }}</title>
</head>
<body style="margin:0;padding:0;background-color:#120C08;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#120C08;">
<tr>
<td align="center" style="padding:32px 16px;">
<table role="presentation" width="480" cellpadding="0" cellspacing="0" style="max-width:480px;width:100%;font-family:'Hanken Grotesk',Arial,sans-serif;">
<tr>
<td style="padding-bottom:20px;">
<span style="font-family:'Oswald',Georgia,serif;color:#BD4049;text-transform:uppercase;letter-spacing:2px;font-size:13px;font-weight:700;">KENA</span>
</td>
</tr>
<tr>
<td>
{{ $slot }}
</td>
</tr>
<tr>
<td style="padding-top:28px;text-align:center;">
<span style="color:#6E6762;font-size:11px;">Kena &middot; Entre em cena</span>
</td>
</tr>
</table>
</td>
</tr>
</table>
</body>
</html>
```

- [ ] **Step 4: Criar o componente de botão**

Criar `resources/views/components/mail/button.blade.php`:

```blade
@props(['url'])
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:20px 0;">
<tr>
<td style="background-color:#BD4049;border-radius:6px;">
<a href="{{ $url }}" style="display:block;padding:12px 24px;color:#FCF3F0;font-family:'Oswald',Georgia,serif;text-transform:uppercase;font-size:13px;letter-spacing:1px;text-decoration:none;">{{ $slot }}</a>
</td>
</tr>
</table>
```

- [ ] **Step 5: Criar o componente de canhoto de ingresso**

Criar `resources/views/components/mail/ticket-stub.blade.php`:

```blade
@props(['sectorName', 'seatLabel', 'holderName', 'code', 'qrSrc'])
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#1D1511;border-radius:10px;margin:16px 0;">
<tr>
<td style="padding:16px;border-right:1px dashed #382E29;vertical-align:top;">
<div style="color:#6E6762;font-size:10px;text-transform:uppercase;letter-spacing:1px;">Setor</div>
<div style="color:#F3EEE6;font-size:14px;font-weight:600;margin-top:2px;">{{ $sectorName }}</div>
<div style="color:#6E6762;font-size:10px;text-transform:uppercase;letter-spacing:1px;margin-top:10px;">Lugar</div>
<div style="color:#F3EEE6;font-size:14px;font-weight:600;margin-top:2px;">{{ $seatLabel }}</div>
<div style="color:#6E6762;font-size:10px;text-transform:uppercase;letter-spacing:1px;margin-top:10px;">Titular</div>
<div style="color:#F3EEE6;font-size:13px;margin-top:2px;">{{ $holderName }}</div>
</td>
<td width="96" style="padding:12px;text-align:center;vertical-align:middle;">
<img src="{{ $qrSrc }}" width="64" height="64" alt="QR {{ $code }}" style="display:block;border-radius:6px;margin:0 auto;">
<div style="color:#6E6762;font-size:9px;font-family:monospace;margin-top:6px;">{{ $code }}</div>
</td>
</tr>
</table>
```

- [ ] **Step 6: Reescrever a view do e-mail "Ingressos emitidos"**

Substituir o conteúdo de `resources/views/mail/tickets-issued.blade.php` por:

```blade
<x-mail.layout title="Seus ingressos">
<h1 style="font-family:'Oswald',Georgia,serif;color:#F3EEE6;text-transform:uppercase;font-size:22px;margin:0 0 12px;">Pagamento aprovado 🎭</h1>

<p style="color:#A59D95;font-size:14px;line-height:1.6;margin:0 0 4px;">Olá {{ $order->user->name }}, seus ingressos para <strong style="color:#F3EEE6;">{{ $event->title }}</strong> foram emitidos.</p>
<p style="color:#A59D95;font-size:13px;margin:8px 0 0;"><strong style="color:#F3EEE6;">{{ $sessionLabel }}</strong></p>
<p style="color:#A59D95;font-size:13px;margin:0;">{{ $venue->name }} — {{ $venue->city }}/{{ $venue->state }}</p>

@foreach ($order->tickets as $ticket)
<x-mail.ticket-stub
    :sector-name="$ticket->sector_name"
    :seat-label="$ticket->seat_code"
    :holder-name="$ticket->holder_name"
    :code="$ticket->code"
    :qr-src="$message->embedData(\App\Support\QrImage::png($ticket->qr_token), 'qr-'.$ticket->code.'.png', 'image/png')"
/>
@endforeach

<p style="color:#A59D95;font-size:13px;line-height:1.6;margin:16px 0 0;">Apresente o QR na entrada — ele é reemitido se você transferir o ingresso. Bom espetáculo!</p>

<x-mail.button :url="$ticketsUrl">Ver meus ingressos</x-mail.button>
</x-mail.layout>
```

- [ ] **Step 7: Trocar `markdown:` por `view:` no Mailable**

Em `app/Mail/TicketsIssuedMail.php`, dentro de `content()`, trocar:

```php
return new Content(
    markdown: 'mail.tickets-issued',
```

por:

```php
return new Content(
    view: 'mail.tickets-issued',
```

(o restante do array `with` não muda)

- [ ] **Step 8: Rodar o teste e confirmar que passa**

Run: `php artisan test --filter=test_tickets_issued_uses_kena_dark_theme`
Expected: PASS

- [ ] **Step 9: Rodar a suíte de e-mail existente (não pode quebrar)**

Run: `php artisan test tests/Feature/Kena/MailNotificationsTest.php`
Expected: PASS (o teste `test_confirmation_email_renders_with_inline_qr` continua verde — copy e QR embutido são os mesmos, só mudou o wrapper visual)

- [ ] **Step 10: Commit**

Não aplicável — projeto não é um repositório git (confirmado no início do trabalho nesta base de código). Pular para a próxima task.

---

### Task 2: E-mail "Transferência recebida" (reusa os mesmos componentes)

**Files:**
- Modify: `resources/views/mail/ticket-transferred.blade.php`
- Modify: `app/Mail/TicketTransferredMail.php:23-38`
- Test: `tests/Feature/Kena/MailVisualIdentityTest.php` (adicionar teste)

- [ ] **Step 1: Adicionar o teste que falha**

Adicionar em `MailVisualIdentityTest.php`:

```php
    public function test_ticket_transferred_uses_kena_dark_theme(): void
    {
        $order = $this->paidOrder();
        $ticket = $order->tickets()->firstOrFail();

        $html = (new TicketTransferredMail($ticket, 'Ana Souza'))->render();

        $this->assertStringContainsString('#120C08', $html);
        $this->assertStringContainsString('Ana Souza', $html);
    }
```

- [ ] **Step 2: Rodar e confirmar que falha**

Run: `php artisan test --filter=test_ticket_transferred_uses_kena_dark_theme`
Expected: FAIL

- [ ] **Step 3: Reescrever a view**

Substituir o conteúdo de `resources/views/mail/ticket-transferred.blade.php` por:

```blade
<x-mail.layout title="Você recebeu um ingresso">
<h1 style="font-family:'Oswald',Georgia,serif;color:#F3EEE6;text-transform:uppercase;font-size:22px;margin:0 0 12px;">Você recebeu um ingresso 🎟️</h1>

<p style="color:#A59D95;font-size:14px;line-height:1.6;margin:0 0 4px;"><strong style="color:#F3EEE6;">{{ $fromName }}</strong> transferiu um ingresso para você.</p>
<p style="color:#A59D95;font-size:13px;margin:8px 0 0;"><strong style="color:#F3EEE6;">{{ $event->title }}</strong></p>
<p style="color:#A59D95;font-size:13px;margin:0;">{{ $sessionLabel }} · {{ $venue->name }}, {{ $venue->city }}</p>

<x-mail.ticket-stub
    :sector-name="$ticket->sector_name"
    :seat-label="$ticket->seat_code"
    :holder-name="$ticket->holder_name"
    :code="$ticket->code"
    :qr-src="$message->embedData(\App\Support\QrImage::png($ticket->qr_token), 'qr-'.$ticket->code.'.png', 'image/png')"
/>

<p style="color:#A59D95;font-size:13px;line-height:1.6;margin:16px 0 0;">O ingresso já está na sua conta Kena. Apresente o QR na entrada.</p>

<x-mail.button :url="$ticketsUrl">Ver meus ingressos</x-mail.button>
</x-mail.layout>
```

- [ ] **Step 4: Trocar `markdown:` por `view:`**

Em `app/Mail/TicketTransferredMail.php`, dentro de `content()`, trocar `markdown: 'mail.ticket-transferred'` por `view: 'mail.ticket-transferred'`.

- [ ] **Step 5: Rodar o teste e confirmar que passa**

Run: `php artisan test --filter=MailVisualIdentityTest`
Expected: PASS (os 2 testes já escritos)

- [ ] **Step 6: Rodar a suíte de e-mail existente**

Run: `php artisan test tests/Feature/Kena/MailNotificationsTest.php`
Expected: PASS

---

### Task 3: E-mails "Lembrete D-1" e "Reembolso confirmado" (painel de informação)

**Files:**
- Create: `resources/views/components/mail/info-panel.blade.php`
- Modify: `resources/views/mail/event-reminder.blade.php`
- Modify: `resources/views/mail/refund-confirmed.blade.php`
- Modify: `app/Mail/EventReminderMail.php:23-38`
- Modify: `app/Mail/RefundConfirmedMail.php:22-35`
- Test: `tests/Feature/Kena/MailVisualIdentityTest.php` (adicionar 2 testes)

- [ ] **Step 1: Adicionar os testes que falham**

Adicionar em `MailVisualIdentityTest.php`:

```php
    public function test_event_reminder_uses_kena_dark_theme(): void
    {
        $order = $this->paidOrder();

        $html = (new EventReminderMail($order->session, $order->user))->render();

        $this->assertStringContainsString('#120C08', $html);
    }

    public function test_refund_confirmed_uses_kena_dark_theme(): void
    {
        $order = $this->paidOrder();

        $html = (new RefundConfirmedMail($order))->render();

        $this->assertStringContainsString('#120C08', $html);
        $this->assertStringContainsString('R$', $html);
    }
```

- [ ] **Step 2: Rodar e confirmar que falham**

Run: `php artisan test --filter=MailVisualIdentityTest`
Expected: os 2 testes novos FAIL, os 2 anteriores continuam PASS

- [ ] **Step 3: Criar o componente de painel de informação**

Criar `resources/views/components/mail/info-panel.blade.php`:

```blade
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#1D1511;border-radius:10px;margin:16px 0;">
<tr>
<td style="padding:16px;">
{{ $slot }}
</td>
</tr>
</table>
```

- [ ] **Step 4: Reescrever a view do lembrete**

Substituir o conteúdo de `resources/views/mail/event-reminder.blade.php` por:

```blade
<x-mail.layout title="É amanhã">
<h1 style="font-family:'Oswald',Georgia,serif;color:#F3EEE6;text-transform:uppercase;font-size:22px;margin:0 0 12px;">É amanhã 🎭</h1>

<p style="color:#A59D95;font-size:14px;line-height:1.6;margin:0;">Olá {{ $name }}, é amanhã o <strong style="color:#F3EEE6;">{{ $event->title }}</strong>.</p>

<x-mail.info-panel>
<div style="color:#F3EEE6;font-size:14px;font-weight:600;">{{ $sessionLabel }}</div>
<div style="color:#A59D95;font-size:13px;margin-top:4px;">{{ $venue->name }} — {{ $venue->city }}/{{ $venue->state }}</div>
@if ($venue->address)
<div style="color:#A59D95;font-size:13px;margin-top:2px;">{{ $venue->address }}</div>
@endif
</x-mail.info-panel>

<p style="color:#A59D95;font-size:13px;line-height:1.6;margin:0;">Leve o QR do seu ingresso (na tela ou impresso). Chegue com antecedência.</p>

<x-mail.button :url="$ticketsUrl">Ver meus ingressos</x-mail.button>

<p style="color:#A59D95;font-size:13px;margin:16px 0 0;">Bom espetáculo!</p>
</x-mail.layout>
```

- [ ] **Step 5: Reescrever a view do reembolso**

Substituir o conteúdo de `resources/views/mail/refund-confirmed.blade.php` por:

```blade
<x-mail.layout title="Reembolso confirmado">
<h1 style="font-family:'Oswald',Georgia,serif;color:#F3EEE6;text-transform:uppercase;font-size:22px;margin:0 0 12px;">Reembolso confirmado</h1>

<p style="color:#A59D95;font-size:14px;line-height:1.6;margin:0;">Olá {{ $order->user->name }}, seu reembolso do pedido <strong style="color:#F3EEE6;">{{ $order->reference }}</strong> foi processado.</p>
<p style="color:#A59D95;font-size:13px;margin:8px 0 0;"><strong style="color:#F3EEE6;">{{ $event->title }}</strong></p>
<p style="color:#A59D95;font-size:13px;margin:0;">{{ $sessionLabel }}</p>

<x-mail.info-panel>
<div style="color:#F3EEE6;font-size:14px;font-weight:600;">Valor reembolsado: R$ {{ number_format($amount, 2, ',', '.') }}</div>
<div style="color:#A59D95;font-size:12px;margin-top:6px;">O estorno aparece no seu meio de pagamento conforme o prazo do Mercado Pago.</div>
</x-mail.info-panel>

<p style="color:#A59D95;font-size:13px;margin:0;">Seus ingressos desse pedido foram cancelados.</p>
</x-mail.layout>
```

- [ ] **Step 6: Trocar `markdown:` por `view:` nos dois Mailables**

Em `app/Mail/EventReminderMail.php` e `app/Mail/RefundConfirmedMail.php`, dentro de `content()`, trocar `markdown: 'mail.event-reminder'` / `markdown: 'mail.refund-confirmed'` por `view: '...'` (mesmo padrão das tasks anteriores).

- [ ] **Step 7: Rodar os testes e confirmar que passam**

Run: `php artisan test --filter=MailVisualIdentityTest`
Expected: PASS (4 testes)

- [ ] **Step 8: Rodar toda a suíte de e-mail + reembolso + lembrete**

Run: `php artisan test tests/Feature/Kena/MailNotificationsTest.php tests/Feature/Kena/RefundFlowTest.php tests/Feature/Kena/ConversionPolishTest.php`
Expected: PASS

---

### Task 4: Rota de preview local dos e-mails

**Files:**
- Create: `app/Http/Controllers/Dev/MailPreviewController.php`
- Modify: `routes/web.php` (adicionar 1 rota)
- Test: `tests/Feature/Kena/MailPreviewTest.php` (novo arquivo)

- [ ] **Step 1: Escrever o teste que falha**

Criar `tests/Feature/Kena/MailPreviewTest.php`:

```php
<?php

namespace Tests\Feature\Kena;

use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use App\Services\SeatReservationService;
use App\Services\TicketIssuanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\TestCase;

class MailPreviewTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    private function paidOrder(): Order
    {
        Mail::fake();
        $session = $this->makeSession(1);
        $buyer = User::factory()->create();
        $reservation = app(SeatReservationService::class)
            ->hold($session, $buyer, $this->availableSeatIds($session, 1));
        $order = app(OrderService::class)->createFromReservation($reservation);
        app(TicketIssuanceService::class)->issueForOrder($order);

        return $order->refresh();
    }

    public function test_previews_each_known_mail_type(): void
    {
        $this->paidOrder();

        foreach (['tickets-issued', 'ticket-transferred', 'event-reminder', 'refund-confirmed'] as $type) {
            $this->get("/dev/mail-preview/{$type}")->assertOk();
        }
    }

    public function test_unknown_type_is_404(): void
    {
        $this->paidOrder();

        $this->get('/dev/mail-preview/nao-existe')->assertNotFound();
    }

    public function test_404_without_seeded_data(): void
    {
        $this->get('/dev/mail-preview/tickets-issued')->assertNotFound();
    }
}
```

- [ ] **Step 2: Rodar e confirmar que falha**

Run: `php artisan test tests/Feature/Kena/MailPreviewTest.php`
Expected: FAIL (rota não existe — 404 em todos, incluindo o teste que espera 200)

- [ ] **Step 3: Criar o controller**

Criar `app/Http/Controllers/Dev/MailPreviewController.php`:

```php
<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use App\Mail\EventReminderMail;
use App\Mail\RefundConfirmedMail;
use App\Mail\TicketsIssuedMail;
use App\Mail\TicketTransferredMail;
use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Mail\Mailable;

/**
 * Preview local dos e-mails transacionais — renderiza o HTML de verdade no
 * navegador sem enviar nada. Bloqueado fora de local/testing.
 */
class MailPreviewController extends Controller
{
    public function show(string $type): Mailable
    {
        abort_unless(app()->environment(['local', 'testing']), 404);

        return match ($type) {
            'tickets-issued' => new TicketsIssuedMail($this->paidOrder()),
            'ticket-transferred' => new TicketTransferredMail($this->anyTicket(), 'Ana Souza'),
            'event-reminder' => new EventReminderMail($this->anyTicket()->session, $this->anyTicket()->user),
            'refund-confirmed' => new RefundConfirmedMail($this->paidOrder()),
            default => abort(404),
        };
    }

    private function paidOrder(): Order
    {
        return Order::query()->where('status', Order::STATUS_PAID)->latest()->first()
            ?? abort(404, 'Nenhum pedido pago no banco — rode "php artisan migrate:fresh --seed" ou faça uma compra de teste.');
    }

    private function anyTicket(): Ticket
    {
        return Ticket::query()->latest()->first()
            ?? abort(404, 'Nenhum ingresso no banco — rode "php artisan migrate:fresh --seed" ou faça uma compra de teste.');
    }
}
```

- [ ] **Step 4: Registrar a rota**

Em `routes/web.php`, adicionar (perto das outras rotas de `tickets.*`, sem middleware de auth — é ferramenta de dev):

```php
Route::get('/dev/mail-preview/{type}', [\App\Http\Controllers\Dev\MailPreviewController::class, 'show'])
    ->name('dev.mail-preview');
```

- [ ] **Step 5: Rodar os testes e confirmar que passam**

Run: `php artisan test tests/Feature/Kena/MailPreviewTest.php`
Expected: PASS (3 testes)

---

### Task 5: Modal de confirmação de reembolso (troca o `window.confirm`)

**Files:**
- Modify: `resources/js/components/organisms/BuyerTicketList.tsx`

- [ ] **Step 1: Rodar o build antes da mudança (baseline)**

Run: `npx tsc --noEmit`
Expected: PASS (sem erros, confirma ponto de partida limpo)

- [ ] **Step 2: Adicionar estado do modal e remover o `window.confirm`**

Em `resources/js/components/organisms/BuyerTicketList.tsx`, adicionar um novo estado logo após `refundingId`:

```typescript
    const [refundTicket, setRefundTicket] = useState<TicketInfo | null>(null);
```

Trocar a função `requestRefund` (que hoje faz `window.confirm` e a chamada de API junto) para só fazer a chamada de API — a confirmação vira o modal:

```typescript
    async function requestRefund(t: TicketInfo) {
        if (refundingId !== null) {
            return;
        }
        setRefundingId(t.id);
        try {
            const res = await api.post<{ message: string }>(t.refundUrl);
            veludoToast.success('Reembolso solicitado', res.message);
            setRefundTicket(null);
            router.reload({ only: ['tickets'] });
        } catch (err) {
            const message = err instanceof ApiError ? err.message : 'Não foi possível reembolsar.';
            veludoToast.error('Reembolso não concluído', message);
        } finally {
            setRefundingId(null);
        }
    }
```

- [ ] **Step 3: Trocar o botão "Reembolsar" para abrir o modal em vez de chamar a API direto**

Trocar (dentro do `.map` dos tickets):

```typescript
                                {t.canRefund && (
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        disabled={refundingId === t.id}
                                        onClick={() => requestRefund(t)}
                                    >
                                        <Icon name="refund" size={15} /> Reembolsar
                                    </Button>
                                )}
```

por:

```typescript
                                {t.canRefund && (
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        disabled={refundingId === t.id}
                                        onClick={() => setRefundTicket(t)}
                                    >
                                        <Icon name="refund" size={15} /> Reembolsar
                                    </Button>
                                )}
```

- [ ] **Step 4: Adicionar o modal de confirmação**

Adicionar um novo `<Modal>` logo depois do `</Modal>` de transferência (antes do `</>` final do componente):

```tsx
            <Modal
                open={!!refundTicket}
                onOpenChange={(o) => !o && setRefundTicket(null)}
                title="Reembolsar ingresso?"
                description={
                    refundTicket
                        ? `${refundTicket.sectorName} · ${refundTicket.seatLabel}`
                        : undefined
                }
            >
                {refundTicket && (
                    <div className="flex flex-col gap-4">
                        <p className="flex items-start gap-1.5 rounded-btn border border-border bg-bg px-3 py-2 font-body text-xs text-muted-foreground">
                            <Icon
                                name="clock"
                                size={14}
                                className="mt-px text-warning"
                            />
                            Os ingressos deste pedido serão cancelados.
                        </p>
                        <div className="flex justify-end gap-3">
                            <Button
                                type="button"
                                variant="secondary"
                                onClick={() => setRefundTicket(null)}
                                disabled={refundingId === refundTicket.id}
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="button"
                                variant="danger"
                                disabled={refundingId === refundTicket.id}
                                onClick={() => requestRefund(refundTicket)}
                            >
                                {refundingId === refundTicket.id ? <Spinner /> : 'Reembolsar'}
                            </Button>
                        </div>
                    </div>
                )}
            </Modal>
```

Adicionar o import do `Modal` (já importado no topo do arquivo — confirmar; se não estiver, adicionar `import { Modal } from '@/components/molecules/Modal';`).

- [ ] **Step 5: Rodar o type-check**

Run: `npx tsc --noEmit`
Expected: PASS

- [ ] **Step 6: Rodar o build**

Run: `npm run build`
Expected: sucesso (`✓ built in ...`)

---

### Task 6: Toast de sucesso ao definir senha

**Files:**
- Modify: `resources/js/pages/buyer/set-password.tsx`

- [ ] **Step 1: Adicionar os imports necessários**

No topo de `resources/js/pages/buyer/set-password.tsx`, trocar:

```typescript
import { Head, Link, useForm } from '@inertiajs/react';
```

por:

```typescript
import { Head, Link, useForm } from '@inertiajs/react';
import { veludoToast } from '@/lib/veludo/toast';
```

- [ ] **Step 2: Adicionar o toast no submit**

Trocar:

```typescript
    function submit(e: FormEvent) {
        e.preventDefault();
        form.post('/definir-senha');
    }
```

por:

```typescript
    function submit(e: FormEvent) {
        e.preventDefault();
        form.post('/definir-senha', {
            onSuccess: () => {
                veludoToast.success('Senha criada', 'Agora você pode entrar com e-mail e senha.');
            },
        });
    }
```

(o controller (`PasswordSetupController::store`) já redireciona pra `tickets.index` no sucesso — o toast só some com esse redirect por causa do `router.reload`/nova navegação do Inertia; se sumir rápido demais na prática, é aceitável — o toast do Sonner já usa 5s de duração e a navegação do Inertia não desmonta o `Toaster` global.)

- [ ] **Step 3: Rodar o type-check**

Run: `npx tsc --noEmit`
Expected: PASS

- [ ] **Step 4: Rodar o build**

Run: `npm run build`
Expected: sucesso

---

### Task 7: Verificação final

**Files:** nenhum (só verificação)

- [ ] **Step 1: PHPStan**

Run: `vendor/bin/phpstan analyse --no-progress --error-format=raw`
Expected: limpo (sem novos erros introduzidos pelas tasks 1-4; os erros pré-existentes do Alexandre em `AdminController.php`/`GoogleAuthController.php`/`Setting.php` não contam)

- [ ] **Step 2: TypeScript**

Run: `npx tsc --noEmit`
Expected: limpo

- [ ] **Step 3: Pint**

Run: `vendor/bin/pint --quiet`
Expected: sem output (nada pra formatar)

- [ ] **Step 4: Suíte de testes completa**

Run: `php artisan test`
Expected: todos verdes (contando a partir de 107 + os novos: 4 de `MailVisualIdentityTest` + 3 de `MailPreviewTest` = 114)

- [ ] **Step 5: Build do frontend**

Run: `npm run build`
Expected: `✓ built in ...`

- [ ] **Step 6: Conferir visualmente pelo menos 1 e-mail via preview**

Rodar o servidor (`php artisan serve` ou equivalente já usado no projeto) e abrir
`http://localhost:8000/dev/mail-preview/tickets-issued` no navegador — confirmar
visualmente que bate com o mockup aprovado no brainstorm (fundo escuro, canhoto
perfurado, botão vermelho-terracota).

# Product: Kena

**Last updated:** 2026-07-10
**Method:** codebase scan + conversation

## Product Identity
- **One-liner:** A comprador escolhe um evento, seleciona a sessão e os assentos num mapa interativo do local, paga com Mercado Pago e recebe na hora um ingresso com QR code — que pode transferir para outra pessoa ou apresentar na portaria para check-in.
- **Category:** ticketing-marketplace (venda de ingressos com assento marcado)
- **Product type:** B2C — compradores são o público principal; organizador/staff são operadores internos da mesma plataforma, não contas de clientes isoladas.
- **Collaboration:** hybrid — compra é single-player (um comprador por pedido), mas a operação de um evento é multiplayer (organizador + staff dividem a gestão de eventos, cupons, pedidos e check-in).

## Business Model
- **Monetização:** taxa de serviço por pedido (`fee_cents` em `Order`) — modelo estilo Eventbrite. A plataforma cobra uma taxa em cima de cada venda de ingresso, não uma assinatura.
- **Pricing tiers:** não há tiers de plano — preço varia por evento/setor (`Sector`), não por nível de acesso à plataforma.
- **Billing integration:** Mercado Pago (`app/Services/Payments/MercadoPagoGateway.php`) processa o pagamento do comprador. Não há Stripe/Cashier — não existe cobrança de organizador pela plataforma.

## Tech Stack
- **Primary language:** PHP (backend) + TypeScript (frontend)
- **Framework:** Laravel + Inertia.js + React 19
- **Database:** relacional via Eloquent (migrations em `database/migrations`) — motor não determinável pelo código (depende do `.env` em produção)
- **Background jobs:** Laravel queue (`jobs` table) + comandos agendados (`ExpireReservations`, `ReconcilePayments`, `SendEventReminders`)
- **HTTP client patterns:** integrações via SDKs/serviços dedicados em `app/Services` (ex.: `MercadoPagoGateway`), sem cliente HTTP genérico exposto
- **Module organization:** Services de domínio em `app/Services` (um por responsabilidade: reserva, pagamento, emissão de ticket, check-in, reembolso, transferência, cupom, webhook), Controllers HTTP finos em `app/Http/Controllers`

## Value Mapping

### Primary Value Action
**Pedido pago (Order status = paid)** — o comprador reservou assentos, pagou via Mercado Pago e recebeu ingresso(s) com QR emitido. Se esse fluxo cair a zero, a plataforma não gera receita e não entrega o produto (o ingresso).

### Core Features (directly deliver value)
1. **Descoberta e seleção de evento/sessão** (`BuyerController`) — vitrine de eventos e mapa de assentos por sessão; é o ponto de entrada para a compra.
2. **Reserva de assento (hold)** (`ReservationController`, `SeatReservationService`) — trava temporariamente o assento durante o checkout, evitando venda duplicada.
3. **Checkout e pagamento** (`CheckoutController`, `PaymentService`, `MercadoPagoGateway`) — aplica cupom, processa pagamento, confirma pedido.
4. **Emissão de ingresso com QR** (`TicketIssuanceService`, `QrTokenService`) — converte pedido pago em ingressos válidos e verificáveis.
5. **Check-in na portaria** (`CheckInController`, `CheckInService`) — valida o QR e admite o portador no evento; é onde o valor do ingresso é finalmente consumido.

### Supporting Features (enable core actions)
1. **Transferência de ingresso** (`TicketTransferService`) — permite repassar o ingresso a outra pessoa sem nova compra.
2. **Reembolso** (`RefundService`) — devolve valor ao comprador quando pedido ou sessão é cancelado.
3. **Cupons de desconto** (`CouponService`) — incentiva conversão no checkout.
4. **Gestão de eventos/locais/assentos** (`EventController`, `VenueController`, `SeatController`, `SessionSeatGenerator`) — organizador monta o catálogo que os compradores vão comprar.
5. **Gestão de pedidos e cancelamento de sessão** (`OrderController`, `SessionCancellationService`) — operação pós-venda do organizador.
6. **Webhooks e reconciliação de pagamento** (`WebhookController`, `WebhookService`, comando `ReconcilePayments`) — garante que o status do pedido reflita o que o Mercado Pago processou, mesmo se a notificação falhar.
7. **Lembretes de evento** (comando `SendEventReminders`) — reengajamento pré-evento por e-mail.
8. **Wallet passes** (`GoogleWalletPass`) — conveniência de apresentar o ingresso no Google Wallet.

## Entity Model

### Users
- **ID format:** inteiro autoincremento (chave primária padrão do Eloquent)
- **Roles:** `buyer` (comprador, inclui contas leves criadas via `GuestIdentityService` no fluxo de convidado), `organizer` (gestão sensível: eventos, cupons, locais, pedidos, config, equipe), `staff` (restrito ao check-in), mais uma flag independente `is_admin` (acesso administrativo total, sobrepõe `role`)
- **Multi-account:** não — um usuário tem um único papel (`role`) na plataforma; não há troca entre múltiplas contas/organizações

### Accounts
- Não existe entidade de conta/organização no código. A plataforma é single-tenant: um único operador (a própria Kena) roda todos os eventos, com `organizer`/`staff` como papéis internos de usuários, não como contas de clientes isoladas.

## Group Hierarchy

Não aplicável — produto B2C single-tenant, sem hierarquia de contas/grupos. A estrutura relevante para tracking é por **entidade de domínio**, não por conta:

```
Venue (local)
└── Event (evento)
    └── EventSession (sessão/data)
        └── Sector (setor) → Seat (assento)
```

| Nível | Pai | Onde as ações acontecem |
|-------|-----|--------------------------|
| Venue | — | cadastro de local e importação/geração de mapa de assentos |
| Event | Venue | criação/edição de evento, cupons por evento |
| EventSession | Event | reserva, checkout, cancelamento de sessão, lembretes |
| Seat/Sector | EventSession | seleção de assento, disponibilidade, check-in |

**Nível de evento padrão:** `EventSession` (é onde reserva, pagamento e check-in efetivamente ocorrem)
**Ações administrativas em:** `Event` ou `Venue` (gestão de catálogo pelo organizador)

## Current State
- **Existing tracking:** nenhum (`current-state.yaml` já auditado em 2026-07-10 confirma: greenfield, sem SDK de analytics instalado)
- **Documentation:** parcial — há memória de projeto (hardening, backend) mas nenhuma documentação de telemetria antes deste modelo
- **Known issues:** nenhum evento, identify ou group call implementado; produto é greenfield para telemetria

## Integration Targets
| Destination | Purpose | Priority |
|-------------|---------|----------|
| A decidir | Nenhum destino escolhido ainda — decisão adiada para a fase de design do tracking plan | — |

## Codebase Observations
- **Feature areas inferred:** vitrine pública de eventos, reserva/checkout, painel administrativo (eventos, pedidos, cupons, locais, equipe, config), check-in de portaria, autenticação (Fortify + Google OAuth + magic link + passkeys), API pública de disponibilidade de assentos, webhook de pagamento
- **Entity model inferred:** `User` (role + is_admin) → `Order` (pedido, com `fee_cents`/`discount_cents`/`total_cents`) → `Payment` → `Ticket` (com QR e possível `TicketTransfer`) e `Refund`; catálogo em `Venue` → `Event` → `EventSession` → `Sector`/`Seat` → `SessionSeat`; `Reservation`/`ReservationSeat` como hold temporário antes do pedido; `Coupon`/`CouponRedemption` para desconto; `CheckIn` para admissão; `WebhookEvent` para idempotência de notificações do Mercado Pago

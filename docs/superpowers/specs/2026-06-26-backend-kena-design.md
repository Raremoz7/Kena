# Backend Kena — Design

Sistema de ingressos com assento marcado. Esta fatia transforma o frontend mockado
em backend de domínio real: catálogo multi-evento, reserva com hold, pedido/pagamento
via Mercado Pago, emissão de ingressos, transferência/reembolso e painel do organizador
com check-in.

Stack: Laravel 13 + Inertia 2 + React 19 + SQLite (dev). Testes PHPUnit 12. PHPStan/larastan.

## Decisões travadas

- **Escopo:** tudo (comprador + organizador + check-in + webhooks + transferência/reembolso),
  implementado em fases sequenciais A–F que sempre rodam.
- **Pagamento:** Mercado Pago real (cartão tokenizado via Bricks + Pix). `PaymentGateway`
  abstrato com `MercadoPagoGateway`; credenciais por `.env`.
- **Dados:** multi-evento. Seeder do "O Quebra-Nozes" no Teatro UNIP com os 500 assentos reais.
- **Hold:** 10 minutos.
- **Auth organizador:** mesma tabela `users` + coluna `role` (buyer/organizer/staff) + middleware.

## Modelo de dados (~18 tabelas)

- **Catálogo:** `venues`, `events`, `event_sessions`, `sectors`, `seats` (mapa físico do venue).
- **Disponibilidade:** `session_seats` (estado por sessão: available/held/sold/blocked,
  `hold_expires_at`, preço, FK reservation/order). Tabela quente do polling, índice (session_id,status).
- **Reserva:** `reservations` (status active/expired/converted/cancelled, expires_at) + `reservation_seats`.
- **Venda:** `orders` (subtotal/discount/fee/total, status pending/paid/failed/refunded/cancelled),
  `order_items`, `payments` (gateway, method card/pix, gateway_payment_id, status, pix_qr/copia_cola, payload).
- **Cupom:** `coupons` (percent/fixed, value, max_uses, used, expires_at, event_id?), `coupon_redemptions`.
- **Ingressos:** `tickets` (code KNA-…, qr_token, holder, status valid/used/transferred/refunded/cancelled,
  checked_in_at), `ticket_transfers` (from/to, status, expires_at), `refunds`.
- **Operação:** `check_ins` (ticket, operator, result ok/denied, reason, scanned_at), `webhook_events`
  (gateway, gateway_event_id único, payload, processed_at) p/ idempotência.
- `users.role`.

## Services

`SeatReservationService` (hold/release, lockForUpdate em transação — serializa no SQLite),
`AvailabilityService` (snapshot p/ polling, cache curto), `OrderService`, `PricingService`,
`CouponService`, `PaymentGateway`+`MercadoPagoGateway`, `PaymentService` (orquestra criação
+ confirma via webhook), `WebhookService` (idempotente), `TicketIssuanceService`, `QrTokenService`
(HMAC assinado), `TicketTransferService` (trava 24h antes da sessão), `RefundService`,
`CheckInService`, `SalesDashboardService`.

## Rotas

- **Comprador (Inertia):** `/`, `/eventos`, `/e/{slug}`, `/e/{slug}/sessoes/{session}`,
  `/checkout/{order}`, `/meus-ingressos`.
- **API JSON (auth):** `POST /api/sessoes/{session}/reservas`, `DELETE /api/reservas/{reservation}`,
  `GET /api/sessoes/{session}/disponibilidade`, `POST /api/reservas/{reservation}/pedido`,
  `POST /api/pedidos/{order}/pagamento`, `GET /api/pedidos/{order}/status`,
  `POST /api/ingressos/{ticket}/transferir`, `POST /api/ingressos/{ticket}/reembolso`.
- **Webhook (público):** `POST /webhooks/mercadopago`.
- **Organizador (role):** `/painel`, `/painel/sessoes/{session}/checkin`, `POST /api/checkin`.
- **Scheduler:** `ExpireReservations` a cada minuto (libera holds vencidos + cancela pedidos pix expirados).

## Fluxos críticos

- **Reserva:** transação + `lockForUpdate` nos `session_seats` available → vira held +
  `hold_expires_at = now+10min`. Conflito → 409 com lista de assentos. Expiração via job e via leitura preguiçosa.
- **Pedido/Pagamento:** order criado a partir da reserva (snapshot de preço). Cartão: token do Bricks →
  `POST /v1/payments` → approved emite ingressos. Pix: gera QR/copia-cola, order pending até webhook.
- **Webhook:** idempotente por `gateway_payment_id`/`webhook_events.gateway_event_id`; consulta o pagamento
  na API do MP (não confia no payload) e confirma/recusa.
- **Emissão:** no `paid`, cada `order_item` vira `ticket` (code + qr_token), `session_seat` → sold.
- **Transferência:** bloqueada se `session.starts_at - 24h <= now`; QR antigo invalidado, novo emitido.
- **Check-in:** valida qr_token (assinatura + sessão correta + status valid), marca `used` + registra check_in;
  já usado/ inválido → denied com motivo.

## Verificação

`php artisan migrate:fresh --seed`, `npm run build`, `phpstan`, `pint`, e testes PHPUnit:
concorrência da reserva (dois holds no mesmo assento), totais com cupom, idempotência do webhook, check-in duplo.

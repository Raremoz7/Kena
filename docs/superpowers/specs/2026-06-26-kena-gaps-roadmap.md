# Kena — Roadmap de Lacunas (pós-brainstorm)

Sequência por dependência + risco. Cada bloco é verificado (testes + build) antes do próximo.

## Decisões
- **Reembolso:** ambos — comprador self-service até um prazo + organizador a qualquer hora.
- **Guest checkout:** sim — compra com email+CPF, conta leve, acesso por magic-link.
- **Plantas de novos locais:** fora do escopo agora (só Teatro UNIP). Importador fica adiado.
- **E-mail:** provedor real via `.env` (driver agnóstico do Laravel).

## Blocos
1. **Reconciliação de Pix** — job `kena:reconcile-payments` consulta o MP para pagamentos pendentes
   e sincroniza (pega aprovações que o webhook perdeu); expira Pix vencido (cancela pedido + libera
   assentos). **Alinhar o hold do assento à expiração do Pix** (hoje hold=10min < pix=30min → assento
   some antes do pagamento). Sem dependências.
2. **E-mails transacionais** — infra agnóstica + compra paga (com ingresso/QR), transferência recebida,
   reembolso confirmado. Fundação de vários blocos.
3. **Guest checkout** — email+CPF, conta leve, acesso por magic-link (usa #2). Reshape do fluxo do comprador.
4. **Painel: pedidos/compradores + export CSV** — operacional, independente.
5. **Reembolso** (comprador com prazo + organizador) — usa #2 e o modelo final de comprador (#3).
6. **Cancelar sessão/evento + reembolso em massa** — estende #5.
7. **Bloquear/liberar assentos manualmente** — isolado.
8. **Conversão/polish** — busca/filtro na home, `.ics` no ingresso, lembrete D-1 (usa #2 + scheduler).

**Adiado:** importador de planta (multi-local), Wallet pass.

## Bloco 1 — desenho
- `PaymentService.pay` (Pix): após criar o pagamento, estende `reservation.expires_at` e
  `session_seats.hold_expires_at` dos lugares segurados para `pix_expires_at + buffer`, para o hold
  não vencer antes do Pix.
- `PaymentService.expirePending(Payment)`: cancela pedido + pagamento e libera os assentos (via
  `SeatReservationService.release`).
- Command `kena:reconcile-payments` (agendado a cada minuto): (a) para cada pagamento `pending` com
  `gateway_payment_id`, consulta o gateway e `sync`; (b) expira Pix `pending` com `pix_expires_at` no
  passado.
- Testes: reconciliação aprova Pix que o webhook perdeu; expiração libera assentos + cancela pedido;
  hold estendido até a expiração do Pix.

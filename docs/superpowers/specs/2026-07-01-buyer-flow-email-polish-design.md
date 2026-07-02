# Polish do fluxo do comprador: identidade visual dos e-mails + 3 ajustes de UI

## Contexto

O fluxo do comprador (catálogo → assento → checkout → confirmação → Meus Ingressos)
já está funcionalmente completo. Um levantamento identificou que os 4 e-mails
transacionais (`TicketsIssuedMail`, `TicketTransferredMail`, `EventReminderMail`,
`RefundConfirmedMail`) têm copy pronta, mas usam o componente markdown padrão do
Laravel (`<x-mail::message>`) — ou seja, visual genérico azul, sem nenhuma
identidade da Kena. Este spec cobre o desenho visual desses e-mails e mais 3
ajustes soltos de UI encontrados na mesma varredura.

## 1. Identidade visual dos e-mails

### Paleta (hex — clientes de e-mail não suportam `oklch()` nem `var()`)

Convertida diretamente dos tokens de `resources/css/app.css` para manter
consistência com o app:

| Token         | Hex       | Uso no e-mail                          |
|---------------|-----------|-----------------------------------------|
| `bg`          | `#120C08` | Fundo do corpo do e-mail                |
| `surface`     | `#1D1511` | Fundo do canhoto / painel de informação |
| `surface-2`   | `#271E1A` | Placeholder do QR dentro do canhoto      |
| `accent`      | `#BD4049` | Logo "KENA", títulos de destaque, botão CTA |
| `accent-fg`   | `#FCF3F0` | Texto sobre o botão CTA                 |
| `foreground`  | `#F3EEE6` | Texto principal                         |
| `muted-fg`    | `#A59D95` | Texto secundário (datas, descrições)     |
| `faint`       | `#6E6762` | Texto terciário (código do ingresso)     |
| `border`      | `#382E29` | Divisórias, borda tracejada do canhoto   |
| `success`     | `#007F43` | (reservado — não usado nos 4 e-mails atuais) |
| `warning`     | `#AC6900` | (reservado)                              |

### Tipografia

- Títulos/kicker: `Oswald` (700), uppercase, `letter-spacing: 1-2px` — mesma
  fonte de destaque do app. Fallback: `Georgia, serif` (a maioria dos clientes
  de e-mail bloqueia `@font-face`/Google Fonts; usar `<link>` do Google Fonts
  mesmo assim para os clientes que suportam — Apple Mail, e a maioria dos
  webmails renderiza a fonte; Outlook desktop cai no fallback).
- Corpo: `Hanken Grotesk`, fallback `Arial, sans-serif`.

### Componentes visuais (partials Blade, reutilizados pelos 4 e-mails)

Criar `resources/views/mail/partials/`:

- **`layout.blade.php`** — envelope: fundo `#120C08`, largura máx. 480px
  centralizada (padrão de e-mail), padding 32px/22px, wordmark "KENA" no topo
  (accent, uppercase, letter-spacing), slot de conteúdo, rodapé
  "Kena · Entre em cena" (faint, centralizado). Recebe o conteúdo de cada
  e-mail via `@slot` ou `$slot` do Blade component.
- **`ticket-stub.blade.php`** — o "canhoto": card com `surface` de fundo,
  border-radius 10px, dividido em duas colunas por borda tracejada
  (`border: 1px dashed #382E29`): esquerda = setor/lugar/titular (label
  uppercase pequeno em `faint` + valor em `foreground`), direita = QR
  (`<img>` embutido via `$message->embedData`, como já é feito hoje) + código
  monoespaçado em `faint`. Dois círculos `#120C08` posicionados na borda
  tracejada (topo e base) simulam o furo de recorte.
  Usado por: **Ingressos emitidos**, **Transferência recebida**.
- **`info-panel.blade.php`** — painel simples (mesmo `surface`, mesmo
  border-radius, sem a borda tracejada nem os furos) para conteúdo que não é
  "aqui está seu ingresso": usado por **Lembrete D-1** (sessão + endereço do
  local) e **Reembolso confirmado** (valor reembolsado).
- **`button.blade.php`** — substitui `<x-mail::button>`: retângulo `accent`
  de fundo, texto `accent-fg`, uppercase, `Oswald`, border-radius 6px.

### Por que canhoto só em 2 dos 4 e-mails

O canhoto (ticket-stub) é a metáfora "aqui está seu ingresso físico" — faz
sentido para emissão e transferência (ambos entregam um ingresso válido pela
primeira vez para aquele destinatário). Lembrete não emite nada de novo (só
avisa); reembolso é um cancelamento. Usar o canhoto nesses dois passaria a
mensagem errada ("aqui está um ingresso novo"), por isso usam o `info-panel`
mais neutro, mantendo a mesma paleta/tipografia para consistência de marca.

### Conteúdo por e-mail (copy já existente, só muda o wrapper visual)

- **Ingressos emitidos** — mantém texto atual ("Pagamento aprovado 🎭" etc.),
  um `ticket-stub` por ingresso do pedido (`@foreach $order->tickets`).
- **Transferência recebida** — mantém texto atual, um `ticket-stub` para o
  ingresso transferido.
- **Lembrete D-1** — mantém texto atual, `info-panel` com sessão + local
  (nome, cidade/UF, endereço se houver).
- **Reembolso confirmado** — mantém texto atual, `info-panel` com "Valor
  reembolsado: R$ X" + aviso do prazo do Mercado Pago.

### Preview local (dev only)

Adicionar rota **apenas em ambiente `local`** (`routes/web.php`, guardada por
`if (app()->environment('local'))`) que renderiza cada Mailable em HTML puro
no navegador sem enviar e-mail de verdade — útil pra revisar visual sem
precisar dar match com o botão "enviar e-mail de teste" do painel:
`GET /dev/mail-preview/{type}` onde `{type}` é um dos 4 nomes, usando dados
fake (mesmo padrão do `MakesKenaData` dos testes). 404 fora do `local`.

## 2. Modal de confirmação de reembolso

Hoje `BuyerTicketList.tsx` usa `window.confirm(...)` nativo antes de chamar
`requestRefund`. Trocar por um `Modal` (mesmo componente já usado no modal de
transferência): título "Reembolsar ingresso?", corpo com evento/sessão do
ingresso + aviso "Os ingressos deste pedido serão cancelados." (mesmo tom do
banner de aviso do modal de transferência — ícone `clock`/`shield`, texto
`warning`), botões "Cancelar" (secondary) / "Reembolsar" (primary). Estado
`refundTicket: TicketInfo | null` substitui a chamada direta a
`requestRefund` — abre o modal; confirmar dispara a mesma lógica de API que
já existe hoje.

## 3. Toast de sucesso ao definir senha

`resources/js/pages/buyer/set-password.tsx`: no callback `onSuccess` do
`useForm().post(...)`, chamar `veludoToast.success('Senha criada', 'Agora
você pode entrar com e-mail e senha.')` e redirecionar para `/ingressos`
(`router.visit` ou `Link` — usar o padrão já usado em outras telas do buyer).

## Fora de escopo (decidido no brainstorm)

- **Legenda do mapa de assentos** — já existe (`resources/js/components/organisms/SeatMap.tsx:99-119,324`),
  renderizada no rodapé do mapa (Disponível/Selecionado/Ocupado/Cadeirante/Acompanhante).
  O levantamento inicial que apontou essa lacuna estava desatualizado — não há o que fazer aqui.

- **Loading skeleton em Meus Ingressos / polling** — descartado: a barra de
  progresso do Inertia já cobre navegação, e os botões de transferir/reembolsar
  já têm spinner próprio. Adicionar skeleton seria complexidade sem ganho
  real percebido.

## Testes

- **Feature test** dos partials de e-mail: renderizar cada Mailable (como já
  faz `GoogleWalletTest`/mails existentes com `Mail::fake()` + `assertSeeIn`
  ou capturando o HTML renderizado) e checar que contém os hex/marcadores
  esperados (ex.: cor de fundo `#120C08`, texto "KENA"), sem travar em texto
  exato (evitar teste frágil).
- **Refund modal**: teste de componente não é prática no projeto atual (sem
  Jest/RTL configurado) — cobertura fica no teste de feature Pest existente
  do endpoint de reembolso (inalterado, só muda o front).
- Rodar a suíte de verificação padrão do projeto (phpstan nível 7, `tsc
  --noEmit`, `pint`, `php artisan test`, `npm run build`) ao final.

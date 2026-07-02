# Handoff: Sistema de Ingressos com Assento Marcado — Veludo Design System

## Overview

Design system completo para um sistema de venda de ingressos com assento marcado. Inclui a jornada do comprador (evento → seleção de assentos → checkout → meus ingressos), o painel do organizador (dashboard de vendas + check-in scanner), versão mobile (iOS) e todos os tokens de design prontos para uso.

Stack alvo: **Laravel + Inertia.js + React + TypeScript + TailwindCSS**.

---

## Sobre os arquivos de design

Os arquivos `.dc.html` neste pacote são **protótipos de referência criados em HTML** — mockups de alta fidelidade mostrando aparência, hierarquia visual e comportamento pretendidos. **Não são código de produção para copiar diretamente.**

A tarefa do desenvolvedor é **recriar esses designs no ambiente existente do projeto** (React + Inertia + Tailwind), usando os padrões e bibliotecas já estabelecidos no codebase. Os tokens CSS/Tailwind incluídos em `tokens.css` e `tailwind.config.js` (dentro do arquivo `Tokens.dc.html`) são a ponte direta entre design e código.

---

## Fidelidade

**Alta fidelidade (hifi).** Os protótipos têm cores, tipografia, espaçamento e estados de interação finais. O desenvolvedor deve recriar pixel-a-pixel usando os tokens fornecidos.

---

## Design System — Veludo

### Identidade visual
- **Nome**: Veludo
- **Personalidade**: Premium/teatral — elegante, quente, sensação de noite especial
- **Tema principal**: Escuro (dark-first)
- **Público**: Teatro e espetáculos, faixa adulta

### Tipografia
| Uso | Família | Peso | Observação |
|-----|---------|------|------------|
| Títulos de evento, seções, números grandes | Oswald | 500–700 | `text-transform: uppercase` sempre |
| Corpo, labels, botões, inputs | Hanken Grotesk | 400–700 | — |
| Kicker (categoria, setor) | Hanken Grotesk | 600 | `letter-spacing: 0.22em`, uppercase |

**Google Fonts URL:**
```
https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&family=Hanken+Grotesk:wght@400;500;600;700&display=swap
```

### Paleta de tokens

```css
/* Fundo e superfícies — neutros quentes */
--color-bg:          oklch(0.16 0.013 48);   /* canvas principal */
--color-surface:     oklch(0.205 0.015 48);  /* cards */
--color-surface-2:   oklch(0.245 0.016 48);  /* elevated, painéis laterais */
--color-border:      oklch(0.31 0.018 48);   /* bordas normais */

/* Texto */
--color-text:        oklch(0.95 0.012 80);   /* primário */
--color-muted:       oklch(0.70 0.015 70);   /* secundário */
--color-faint:       oklch(0.52 0.012 60);   /* terciário, placeholders */

/* Acento — vinho (ações primárias de marca) */
--color-accent:      oklch(0.55 0.16 20);
--color-accent-hover:oklch(0.61 0.16 20);
--color-accent-fg:   oklch(0.97 0.01 40);    /* texto sobre o acento */

/* Status semânticos */
--color-success:     oklch(0.52 0.14 155);   /* verde */
--color-warning:     oklch(0.58 0.14 72);    /* âmbar */
--color-danger:      oklch(0.52 0.19 27);    /* vermelho */
--color-info:        oklch(0.52 0.14 230);   /* azul */

/* Assentos — tokens de domínio */
--seat-available:    oklch(0.52 0.14 155);   /* verde outline */
--seat-selected:     oklch(0.52 0.14 155);   /* verde sólido preenchido */
--seat-held:         oklch(0.58 0.14 72);    /* âmbar tracejado */
--seat-sold:         oklch(0.245 0.016 48);  /* cinza neutro */
--seat-blocked:      oklch(0.245 0.016 48);  /* cinza + hachura */
--seat-pcd:          oklch(0.52 0.14 230);   /* indicador azul */
```

### Raios por função
| Elemento | Valor |
|----------|-------|
| Badge/tag | 6px |
| Botão, input, assento | 8px |
| Card, modal, tooltip | 12px |
| Avatar, pill | 9999px |

---

## Telas e componentes

### 1. Página do Evento (`/e/{slug}`)
**Arquivo de referência**: `Telas Aplicadas.dc.html` — frame "1 · Página do Evento"

**Layout**: Grid 2 colunas no desktop (`1fr 320px`). Mobile: coluna única com CTA fixo no rodapé.

**Estrutura:**
- `<Header>` com nav: logo + links + botão Entrar
- Hero banner 280px com gradiente `160deg, oklch(0.28 0.055 22) → oklch(0.14 0.012 48)`. Título em Oswald 600 58px uppercase. Kicker + badge de status acima.
- Col principal: texto descritivo + 3 cards de info (data, local, duração) — grid 3 cols, border 1px, radius 10px
- Col lateral (320px): lista de setores com preço em Oswald 20px tabular. Camarote esgotado com opacity 0.5. CTA primário no fundo.

**Badge de status**: fundo `--color-success`, texto branco, font 11px 600, padding 4px 9px, radius 6px.

---

### 2. Seleção de Assentos (`/e/{slug}/sessoes/{session}`)
**Arquivo de referência**: `Telas Aplicadas.dc.html` — frame "2 · Seleção de Assentos" + `SeatMap.dc.html`

**Layout**: Grid 2 colunas (`1fr 300px`). Mobile: mapa full-width + bottom sheet deslizante.

**Nav**: Breadcrumb + contador de reserva (timer âmbar com ícone de aviso).

**SeatMap (componente crítico)**:
- SVG ou grid de divs posicionados por `position_x / position_y` vindos da API
- Estados dos assentos (NUNCA usar só cor — sempre cor + forma/ícone para acessibilidade):

| Estado | Visual | Interativo |
|--------|--------|-----------|
| `available` | Border 1.5px `--seat-available`, texto fileira | Sim — hover com glow + scale 1.08 |
| `available` hover/focus | Glow `oklch(0.52 0.14 155 / .28)`, scale 1.08 | Ring de focus visível para teclado |
| `selected` | Background `--seat-selected`, ícone check branco | Sim — clique deseleciona |
| `held` (outro) | Border 1.5px dashed `--seat-held`, ícone de aviso | Não — aria-disabled |
| `sold` | Background `--seat-sold`, ícone X | Não — aria-disabled |
| `blocked` | Hachura diagonal CSS, ícone cadeado SVG | Não — aria-disabled |
| `accessible` | Border verde + ícone SVG cadeira de rodas + ponto azul `--seat-pcd` | Sim |

**Atenção**: o ícone de acessibilidade é SVG de linha fina — **nunca emoji ♿**.

**Polling**: `GET /api/sessoes/{session}/disponibilidade` a cada 3–5s. Em conflito de reserva (409), mostrar toast warning com lista de assentos afetados.

**Painel lateral de seleção**: lista assentos escolhidos + subtotal + botões "Ir para o checkout" (success) e "Limpar seleção" (outline).

---

### 3. Checkout (`/checkout/{reservation}`)
**Arquivo de referência**: `Telas Aplicadas.dc.html` — frame "3 · Checkout"

**Layout**: Grid 2 colunas (`1fr 320px`). Mobile: coluna única, resumo acima do formulário.

**Seções do formulário (col principal)**:
1. Cupom: input + botão "Aplicar". Feedback de sucesso em `--color-success`, erro em `--color-danger`.
2. Tabs Cartão / Pix
3. Form de cartão (MP Bricks wrapper): número, validade, CVV (com foco ativo vinho), nome, parcelas
4. Nota de segurança (font 11px faint)

**Resumo (col lateral)**:
- Preview do ingresso com faixa vinho no topo (height 4px)
- Linhas: itens + desconto (`--color-success`) + taxa
- Total em Oswald 700 28px tabular
- CTA "Confirmar pagamento" primário vinho

**Contador de reserva**: sempre visível no nav, âmbar quando < 2 min.

---

### 4. Meus Ingressos (`/meus-ingressos`)
**Arquivo de referência**: `Mobile.dc.html` — frame "4 · Meus Ingressos"

**Lista de ingressos** (cards):
- Faixa colorida no topo (4px): vinho para válido
- Conteúdo: kicker do setor, assento em Oswald, data/evento em body
- QR code (72×72px): renderizado com biblioteca no cliente
- Badge de status: fundo cheio, texto branco
- Ações inline: "Transferir" e "Ver QR" (botões outline sm)

**Tab bar mobile**: 4 itens — Eventos, Agenda, Ingressos (ativo = vinho), Perfil

---

### 5. Dashboard do Organizador (`/painel`)
**Arquivo de referência**: `Painel Organizador.dc.html` — frame "1 · Dashboard de Vendas"

**Layout**: Sidebar fixa 220px + área de conteúdo scrollável.

**Sidebar**:
- Fundo `oklch(0.135 0.012 48)`
- Item ativo: `background: --color-surface-2`, borda esquerda 2px vinho, texto primário
- Item inativo: texto muted, sem fundo
- Seções separadas por kicker (Principal / Operação)
- Footer: avatar + nome + role

**KPIs** (grid 5 colunas, `1.4fr 1fr 1fr 1fr 1fr`):
- Métrica primária (Receita total) no canto superior esquerdo — Oswald 700 36px, com borda `oklch(0.34 0.04 22)` destacando
- Demais métricas: Oswald 700 32px
- Delta: seta + percentual em `--color-success` ou `--color-danger`, período em faint
- Barra de progresso inline para ocupação (height 4px, radius 4px)
- **Máximo 5–7 KPIs** — não adicionar sem critério

**Gráfico de barras** (mini, últimos 7 dias):
- Barras com `oklch(0.52 0.14 155 / .35)` para dias passados, `--color-success` para dia atual
- Altura proporcional ao valor, labels de dia em kicker

**DataTable de pedidos recentes**:
- Header em kicker faint, fundo `--color-surface-2`
- Colunas: Comprador (nome + tempo relativo), Assentos, Total (tabular), Status (badge)
- Hover state: fundo `--color-surface-2`

---

### 6. Check-in Scanner (`/painel/sessoes/{session}/checkin`)
**Arquivo de referência**: `Painel Organizador.dc.html` — frame "2 · Check-in Scanner"

**3 estados do scanner**:

**Aguardando**: câmera com frame de scan (4 cantos em L, linha de scan horizontal), fundo quase preto. Rodapé com nome do evento + barra de progresso de check-ins.

**Válido (ok)**:
- Fundo `oklch(0.16 0.04 155)` (tint verde suave)
- Círculo verde 80px com check grande branco
- Título "Entrada liberada" Oswald 700 28px
- Card com detalhes do ingresso (setor, assento, titular, valor, código truncado)
- CTA "Próxima leitura" em `--color-success`

**Recusado (já usado / inválido)**:
- Fundo `oklch(0.17 0.025 27)` (tint vermelho suave)
- Círculo vermelho 80px com X branco
- Título "Acesso negado" + motivo ("Ingresso já utilizado")
- Card com detalhes + horário do check-in anterior
- Botões: "Registrar ocorrência" (outline danger) + "Próxima leitura" (outline neutral)

**Barra de progresso de check-ins** sempre visível no rodapé dos 3 estados — transmite contexto operacional ao operador.

---

## Componentes de UI (Atoms & Molecules)

**Arquivo de referência**: `Componentes.dc.html`

### Button
```
Variantes: primary | secondary | ghost | danger | disabled
Primary:   bg --color-accent, text --color-accent-fg, radius 8px, padding 14px 22px
Hover:     bg --color-accent-hover, sem transition brusca
Secondary: border 1px --color-border, text --color-text
Ghost:     sem borda/fundo, text --color-accent
Danger:    border --color-danger, text --color-danger
Disabled:  bg --color-surface-2, text --color-faint, cursor not-allowed
Size sm:   padding 9px 16px, font 12px, radius 6px
```

### Badge / Status
```
Fundo cheio na cor semântica, texto branco, font 600 11px, padding 4px 9px, radius 6px
Cores: success | warning | danger | accent | info | neutral (oklch 0.40 0.012 50)
NÃO usar fundo tintado/translúcido — fundo sólido na cor completa
```

### Tag de categoria
```
Apenas kicker (texto uppercase tracked), cor --color-accent em tom mais claro
SEM caixa, SEM fundo — só texto
```

### Input
```
bg --color-bg, border 1px --color-border, radius 8px, padding 12px 14px
Foco: border 1.5px --color-accent, box-shadow 0 0 0 3px oklch(0.55 0.16 20 / .18)
Erro: border --color-danger, mensagem de erro em --color-danger abaixo
Disabled: bg levemente mais escuro, opacity 0.5, cursor not-allowed
```

### Toast / Notificação
```
Border 1px + border-left 3px na cor semântica, radius 8px, padding 13px 16px
Ícone SVG de linha fina à esquerda (não emoji, não filled)
Título font 600 13px, descrição font 400 12px muted
NÃO usar fundo tintado/colorido — só a borda esquerda carrega a cor
```

### Card
```
bg --color-surface, border 1px --color-border, radius 12px, padding 28-32px
Usar com intenção — não envolver tudo em card
```

### TicketStub
```
Faixa de cor no topo (height 4-5px): cor de status
Setor em kicker --color-accent, assento em Oswald 600 22-36px
Linha tracejada separando dados / QR: border-left 1px dashed --color-border
QR: 72×72px, fundo --color-surface-2, radius 8px
```

### EmptyState
```
Ícone SVG 56×56px em border card, centralizado
Título Oswald 500 20px, descrição body muted max-width 240px, CTA primary
Voz do domínio: "Nenhum ingresso ainda" — nunca "No data yet"
```

---

## Comportamentos e Interações

### Reserva de assento (fluxo crítico)
1. POST com lista de seat_ids → `SeatReservationService` usa `SELECT ... FOR UPDATE`
2. **Sucesso (200)**: `SessionSeat.status = held`, `hold_expires_at = now + N min`, mostrar contador
3. **Conflito (409)**: toast warning listando assentos bloqueados, mapa atualiza via polling
4. Contador regressivo: mostrar em âmbar, pulsar quando < 2 min, expirar com toast + redirect

### Polling do mapa
- `GET /api/sessoes/{session}/disponibilidade` a cada 3–5s (sem WebSocket)
- Endpoint leve — indexado por `(session_id, status)`, cacheável por 2–3s
- Atualiza apenas os assentos que mudaram de estado (delta payload)

### Checkout — Mercado Pago Bricks
- SDK tokeniza cartão no cliente — token nunca trafega no servidor
- `POST /pedidos` com token + dados → backend chama `/v1/payments`
- Pix: retorna QR + copia-e-cola, pedido fica `pending` até webhook confirmar
- Webhook (`POST /webhooks/mercadopago`): idempotente por `gateway_payment_id`

### Transferência de ingresso
- Bloqueada quando `session.starts_at - 24h <= now`
- Mostrar countdown até o bloqueio na tela "Meus Ingressos"
- Ao aceitar: QR antigo é invalidado, novo emitido

---

## Assets
- **Fontes**: Google Fonts (Oswald + Hanken Grotesk) — nenhum asset local
- **Ícones**: SVG de linha fina gerados inline — não usar Lucide por default, não usar emoji
- **QR code**: gerar no cliente com biblioteca (ex: `qrcode.react`)
- **Banners de evento**: `Event.banner_path` → storage do Laravel

---

## Arquivos de design neste pacote

| Arquivo | Conteúdo |
|---------|----------|
| `Direções Visuais.dc.html` | 4 direções de arte exploradas (referência histórica) |
| `Estilo Base.dc.html` | Paleta, tipografia e raios definidos |
| `Componentes.dc.html` | Atoms e molecules: botões, inputs, badges, toasts, ingresso, empty state |
| `SeatMap.dc.html` | 7 estados de assento com legenda e mapa simulado |
| `Telas Aplicadas.dc.html` | Fluxo desktop: evento → mapa → checkout |
| `Painel Organizador.dc.html` | Dashboard de vendas + check-in scanner (3 estados) |
| `Mobile.dc.html` | 4 telas iOS: evento, mapa, checkout, meus ingressos |
| `Tokens.dc.html` | CSS custom properties + tailwind.config.js prontos para colar |

---

## Design Tokens resumidos

| Token | Valor |
|-------|-------|
| `--color-bg` | `oklch(0.16 0.013 48)` |
| `--color-surface` | `oklch(0.205 0.015 48)` |
| `--color-accent` | `oklch(0.55 0.16 20)` (vinho) |
| `--color-success` | `oklch(0.52 0.14 155)` (verde) |
| `--color-warning` | `oklch(0.58 0.14 72)` (âmbar) |
| `--color-danger` | `oklch(0.52 0.19 27)` (vermelho) |
| `--color-info` | `oklch(0.52 0.14 230)` (azul) |
| `--font-display` | Oswald, uppercase |
| `--font-body` | Hanken Grotesk |
| `--radius-card` | 12px |
| `--radius-btn` | 8px |
| `--radius-badge` | 6px |

---

*Design system criado com Claude · Sistema de Ingressos Veludo · Stack: Laravel + Inertia + React + Tailwind*

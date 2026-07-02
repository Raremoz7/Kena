# Mapa de assentos no mobile — navegação com zoom/pan

**Data:** 2026-06-26
**Status:** Aprovado (design validado com o Davi via companion visual)

## Problema

O `SeatMap` faz auto-fit da largura: espreme o teatro inteiro (Teatro UNIP, 500
lugares, ~26 fileiras) na largura do container. No desktop funciona; no mobile
(~360px) cada assento vira ~5px — ilegível, abaixo do alvo de toque de 44px, sem
como aproximar (os botões +/− foram removidos). O painel "Sua seleção" fica
escondido no mobile (`lg:block`), sobrando só a barra fixa fina com subtotal.

## Objetivo

No mobile, manter a **escolha exata de assento** (lugar marcado/numerado) e
tornar a navegação confortável via **zoom e pan nativos** (pinça + arraste),
mais uma **bottom-sheet** de seleção que substitui a barra fixa fina.

Decisão de modelo de venda: escolha exata obrigatória (não há "por zona / melhor
disponível"). Abordagem escolhida: **canvas livre com pinch-zoom + pan**.

## Escopo

### Em escopo
- Gesto de **pinça (aproximar/afastar) e arraste (pan)** no mapa, no mobile.
- Botões de apoio **menores**: `+`, `−`, e **recentralizar/ajustar** (⤢).
- **Zoom mínimo de interação**: assentos só ficam tocáveis acima de um limiar de
  zoom; abaixo dele, tocar no mapa apenas faz pan/zoom (evita seleção acidental
  num assento de poucos px).
- **Bottom-sheet** de seleção no mobile (recolhida ↔ expandida) substituindo a
  barra fixa atual. Recolhida: contagem + assentos + subtotal + "Continuar".
  Expandida: lista "Sua seleção" (código, setor, qualidade de visão), subtotal,
  CTA "Ir para o checkout", "Limpar seleção".
- Indicador de **% de zoom** e dica de pinça (a dica some após a 1ª interação).
- Legenda compacta mantida abaixo do mapa.

### Fora de escopo
- Desktop permanece como hoje (mapa auto-fit + painel lateral `aside`). O
  comportamento de zoom/pan é primariamente para toque; no desktop o wheel-zoom
  é um bônus opcional, não requisito.
- Countdown de reserva no fluxo **inline** (página do evento). Ele continua
  exclusivo da página dedicada `/sessoes`. (No mockup apareceu só por realismo.)
- Modelo "por zona / melhores lugares" (descartado).
- Mudanças no backend / MockData (o `seatMap` já entrega tudo que precisamos).

## Arquitetura

Três unidades, com responsabilidades isoladas:

### 1. `SeatMap` (organism) — apresentação + navegação do tabuleiro
- Passa a aceitar **zoom/pan controlados externamente** em vez de só auto-fit.
- Substitui o cálculo manual de `scale()` + estado `zoom` por um wrapper de
  pinch/pan: **`react-zoom-pan-pinch`** (`TransformWrapper` + `TransformComponent`).
  - `minScale` = escala de "ajuste à largura" (o fit atual vira o piso);
    `initialScale` ≈ 1.2× o fit para já abrir com assentos legíveis e centrado
    horizontalmente; `maxScale` ~ 2.5.
  - `doubleClick` habilitado (aproxima onde tocou); `pinch` habilitado;
    `panning` habilitado (com `velocityAnimation` para inércia leve).
  - `wheel` habilitado só com `ctrl`/trackpad pinch no desktop (não atrapalha o
    scroll vertical da página).
- **Limiar de toque**: `SeatDot` recebe `interactionEnabled` (boolean derivado da
  escala atual ≥ `MIN_TAP_SCALE`). Abaixo do limiar, os assentos ficam
  `pointer-events-none` (o gesto passa pro pan/zoom). O cálculo da escala atual
  vem do `onTransformed` do wrapper, guardado em estado local.
- **Controles** (`+`, `−`, ⤢) menores (~28px), semitransparentes com blur, no
  canto superior direito do viewport; usam `zoomIn`/`zoomOut`/`centerView` do
  hook `useControls` / refs do wrapper.
- **Indicador de %** no header do card (deriva da escala). **Dica de pinça**
  (pílula central inferior) visível até o primeiro gesto, então oculta
  (estado `hasInteracted`).
- Rótulos de fileira, palco e legenda permanecem; agora vivem **dentro** do
  conteúdo transformável (rótulos/palco escalam junto; a legenda fica fora, no
  rodapé do card).

### 2. `SeatSelection` (organism) — orquestra mapa + painel + estado
- Mantém o estado de seleção (`selected`, `toggle`, `total`) — sem mudança.
- Desktop (`lg+`): inalterado — grid `[1fr_300px]` com `aside` sticky.
- Mobile (`<lg`): a barra fixa fina é **substituída** pela nova `SeatSheet`.

### 3. `SeatSheet` (molecule, novo) — bottom-sheet de seleção (mobile)
- Dois estados: **recolhida** (peek) e **expandida**. Implementada com
  `framer-motion` (já no projeto) para o drag/snap vertical e o scrim.
- Props: `seats`, `total`, `onRemove`, `onClear`, e o destino do checkout.
- Recolhida: handle, "N assentos · códigos", subtotal grande, botão "Continuar"
  (abre a folha se houver seleção / segue pro checkout). Expandida: lista de
  itens (reaproveita a marcação do `SelectionPanel` atual), subtotal, CTA
  "Ir para o checkout", "Limpar seleção", com scrim atrás.
- `lg:hidden` — só existe no mobile. O `SelectionPanel` do desktop continua no
  `aside`.

## Fluxo de dados

`BuyerController@event/@seats` → `seatMap` (inalterado) → `SeatSelection`
(estado de seleção) → `SeatMap` (render + navegação) e `SeatSheet`/`aside`
(render da seleção). Seleção continua client-side; o toggle vem do `SeatDot`.

## Dependência nova

`react-zoom-pan-pinch` (lib pequena, sem dependências, ~controla pinch/pan/wheel,
bounds, double-tap e expõe `zoomIn/zoomOut/centerView`). Adicionada via npm.
Se a instalação não for viável no ambiente, fallback: implementação enxuta de
pinch/pan com Pointer Events dentro do `SeatMap` (dois ponteiros = pinça;
um ponteiro = pan), mantendo a mesma API de limiar/controles.

## Acessibilidade

- Os assentos continuam `<button>` com `aria-label` completo (fila, número,
  status, tipo) — navegável por teclado no desktop.
- Alvo de toque efetivo ≥ ~38–44px quando acima do `MIN_TAP_SCALE`.
- Botões de zoom com `aria-label` ("Aproximar", "Afastar", "Ajustar à tela").
- `prefers-reduced-motion`: desliga inércia/animação de pan e a animação da folha.

## Testes / verificação

- Sem suíte de teste de UI no projeto. Verificação manual em viewport mobile:
  1. Mapa abre legível e centrado; pinça aproxima/afasta; arraste move.
  2. Abaixo do limiar, tocar não seleciona; acima, seleciona o assento certo.
  3. Botões +/−/ajustar funcionam; indicador de % acompanha.
  4. Bottom-sheet: recolhida mostra resumo; expande mostra lista; remover/limpar
     atualizam; CTA leva ao checkout.
  5. Desktop inalterado (mapa + painel lateral).
- `tsc --noEmit` limpo e build do Vite sem erros.

## Riscos

- Instalação da lib no ambiente WSL/UNC — mitigado pelo fallback Pointer Events.
- Conflito do pan vertical do mapa com o scroll da página — resolvido limitando o
  pan ao card e deixando o scroll da página fora do viewport transformável.

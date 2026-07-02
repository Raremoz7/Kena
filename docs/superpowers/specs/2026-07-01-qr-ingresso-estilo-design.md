# Estilo visual do QR code do ingresso

## Contexto

Hoje o QR do ingresso é renderizado com a biblioteca `qrcode.react` (client, `QrCode.tsx`) e com `endroid/qr-code` (server, `QrImage.php`, usado no PNG anexado aos e-mails). Em ambos os casos é o QR "de fábrica": módulos quadrados, sem estilo, sem relação visual com a identidade Kena/Veludo.

Este spec define um novo estilo de QR alinhado à marca, validado visualmente com o Davi via companion no navegador (mockups com QR funcional, escaneável).

## Estilo aprovado

- **Módulos (dots):** quadrados com **cantos arredondados** — não círculos, não quadrados retos. Cor tinta escura `#3a2a24` (o mesmo tom escuro usado no selo).
- **Marcadores de canto (3 "olhos" do QR):** mesmo tratamento arredondado (`extra-rounded`), **na mesma cor monocromática dos módulos** (`#3a2a24`) — sem destaque em vinho, sem contraste de cor entre marcador e miolo.
- **Miolo do marcador de canto:** círculo.
- **Fundo:** creme `#F4EFE7` (tom "selo de ingresso", não o dark-surface padrão do resto da UI).
- **Moldura:** borda tracejada em vinho (baixa opacidade, ex. `#70011f` ~40%) ao redor do QR, com cantos arredondados — ecoa o picote/linha tracejada que já existe no `TicketStub` entre os dados do ingresso e o QR.
- **Logo central:** monograma "K" do Kena (`kena-logo.svg`), **sem o fundo próprio** do arquivo original (fica só o traço vinho, transparente ao redor, integrado ao creme do QR). Tamanho ~38% da largura do QR.
- **Correção de erro:** nível **H** (alto), necessário para manter a leitura mesmo com a área central coberta pelo logo.

Este é o estilo final aprovado (ver mockups gerados durante o brainstorming — dots suaves + selo de ingresso + logo Kena, com iterações até remover o fundo do logo e uniformizar a cor dos marcadores de canto).

## Onde este estilo se aplica

1. **Miniatura no `TicketStub`** (72px, lista "Meus Ingressos") — mesma paleta (dots + cor), pode simplificar a moldura tracejada própria já que o card já tem seu próprio picote.
2. **Modal "Ver QR" grande** (`BuyerTicketList`, 196px) — estilo completo, incluindo a moldura de selo.
3. **QR embutido no PNG do e-mail** (`QrImage.php`, usado pelos Mailables `TicketsIssuedMail`/`TicketTransferredMail`) — mesmo estilo, gerado no servidor.

## Fora de escopo

- **Google Wallet**: o QR dentro do pass é renderizado pelo próprio Google a partir do `barcode.value` — não é customizável por nós. Sem mudança.
- **Scanner de check-in**: ele só decodifica QR (câmera + `BarcodeDetector`/ZXing), não renderiza nada — não afetado.

## Requisito não-funcional

O QR estilizado precisa continuar **100% legível** pelos leitores em uso hoje (câmera do celular do comprador, scanner de check-in). Isso é garantido por:
- nível de correção de erro H;
- logo ocupando só a região central (~38%), dentro da margem de tolerância que o nível H permite cobrir;
- contraste mantido entre tinta escura e fundo creme (não usar módulos em vinho claro sobre creme, que reduziria contraste).

Antes de finalizar a implementação, validar com o scanner de check-in real (câmera) e com pelo menos um app de leitor de QR de celular, não só visualmente.

## Nota técnica (a resolver na fase de plano)

- **Cliente:** troca de `qrcode.react` (sem suporte a estilo) por uma biblioteca que suporte módulos arredondados + logo + correção de erro configurável (validado no protótipo com `qr-code-styling`).
- **Servidor:** `endroid/qr-code` (usado hoje em `QrImage.php`) não tem suporte nativo a módulos arredondados. Será necessário avaliar uma alternativa (ex. `chillerlan/php-qrcode`, que permite desenho customizado por módulo) ou outra forma de gerar o mesmo visual no PNG do e-mail. Essa escolha de biblioteca fica para o plano de implementação.

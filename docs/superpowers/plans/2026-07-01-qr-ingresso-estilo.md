# Estilo Visual do QR do Ingresso — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Trocar o QR "de fábrica" (quadrados retos, sem estilo) por um QR no estilo Kena — módulos com blobs de cantos arredondados, marcadores de canto monocromáticos, fundo creme e o monograma "K" no centro — tanto no site (miniatura + modal) quanto no PNG anexado aos e-mails.

**Architecture:** Cliente troca `qrcode.react` por `qr-code-styling` (renderiza SVG no navegador, já suporta dots arredondados + logo + correção de erro configurável). Servidor troca `endroid/qr-code` por `chillerlan/php-qrcode`, com uma classe de output customizada (`App\Support\KenaQrRenderer`) que estende `QRGdImagePNG` e reaproveita a técnica oficial de "rounded modules" da própria biblioteca (`examples/imageWithRoundedShapes.php`, verificada na tag 6.0.1), sobrepondo o monograma no centro sem reservar espaço no matrix — a correção de erro nível H tolera a área coberta. `App\Support\QrImage::png()` mantém sua assinatura pública (`png(string $data, int $size = 240): string`), só troca a implementação interna — nenhuma view Blade precisa mudar.

**Tech Stack:** `qr-code-styling` (npm), `chillerlan/php-qrcode` ^6.0 (composer, substitui `endroid/qr-code`), `sharp-cli` (via `npx`, uso único pra rasterizar o monograma SVG em PNG), PHPUnit.

**Referência de estilo aprovada:** `docs/superpowers/specs/2026-07-01-qr-ingresso-estilo-design.md`.

**Ambiente:** todos os comandos `php`/`composer`/`npm`/`node` deste projeto rodam **dentro do WSL** (Ubuntu), não no PHP/Composer do Windows (o Composer do Windows falha em caminho UNC). Prefixe comandos de terminal com:
```bash
wsl -e bash -lc "cd ~/Projetos/Ingresso && <comando>"
```

---

### Task 1: Monograma vinho pro centro do QR (SVG + PNG)

**Files:**
- Create: `public/kena-mark-vinho.svg`
- Create: `public/img/kena-qr-mark.png` (binário, gerado a partir do SVG acima)

O projeto já tem `public/kena-mark.svg` (o "K" da marca, recortado, sem fundo, preenchido em creme `#F7ECC5` — usado hoje só como máscara CSS no `KenaMark.tsx`). Para o QR precisamos da MESMA silhueta, mas preenchida em vinho (a cor que realmente aparece, já que aqui não é uma máscara).

- [ ] **Step 1: Criar o SVG vinho**

Crie `public/kena-mark-vinho.svg` com o mesmo `viewBox` e os mesmos 6 `<path>` de `public/kena-mark.svg`, trocando todo `fill="#F7ECC5"` por `fill="#70011F"`:

```xml
<svg version="1.1" xmlns="http://www.w3.org/2000/svg" viewBox="500 455 1055 1045" preserveAspectRatio="xMidYMid meet">
<path fill="#70011F" d="M 1112.72 481.972 L 1346.58 482.01 C 1317.04 511.941 1285.48 541.52 1255.38 571.152 L 937.246 876.923 C 851.399 960.124 764.395 1048.48 677.297 1129.85 C 679.055 1047.01 677.892 960.865 677.891 877.784 L 677.75 482.021 L 900.314 482.023 C 867.71 566.196 839.551 653.706 807.092 738.055 C 796.003 766.869 785.506 796.474 773.243 824.791 C 783.28 813.883 795.548 802.599 806.151 791.718 C 833.883 762.933 861.925 734.448 890.271 706.268 C 963.853 632.996 1041.18 556.901 1112.72 481.972 z"/>
<path fill="#70011F" d="M 1070.38 771.887 L 1384.1 1126.17 C 1339.81 1124.18 1284.65 1125.51 1239.5 1125.53 L 1112.75 1125.68 C 1062.42 1070.41 1014.48 1012.57 964.184 957.212 C 951.171 942.889 937.883 927.89 925.834 912.789 C 937.025 900.829 950.395 888.97 961.99 877.186 C 997.02 841.587 1035.52 807.368 1070.38 771.887 z"/>
<path fill="#70011F" fill-rule="evenodd" d="M 1381.56 1213.36 C 1412.28 1208.12 1438.67 1217.42 1462.69 1235.79 L 1462.86 1218.8 L 1521.15 1218.99 L 1521.07 1468.61 C 1503.15 1467.88 1480.21 1467.99 1462.37 1468.42 L 1461.93 1440.67 C 1460.94 1441.9 1459.93 1443.11 1458.89 1444.3 C 1423.33 1485.16 1358.67 1484.36 1319.07 1449.77 C 1316.3 1446.92 1313.32 1443.88 1310.87 1440.76 C 1297.19 1423.37 1291.03 1401.23 1293.75 1379.27 C 1302.92 1303.41 1398.39 1302.23 1456.13 1309.38 C 1452.12 1273.42 1431.86 1261.58 1396.62 1263.79 C 1376.62 1265.05 1362.79 1275.4 1350.23 1290.54 C 1333.92 1280.58 1316.08 1270.79 1299.41 1261.29 C 1323.43 1232.02 1343.14 1218.38 1381.56 1213.36 z M 1402.45 1348.36 C 1415.2 1346.37 1442.28 1348.22 1455.57 1349.09 C 1455.32 1385.78 1452.69 1410.95 1411.41 1422.32 C 1397.5 1424.24 1382.62 1424.1 1370.57 1415.91 C 1362.54 1410.51 1357.18 1401.95 1355.83 1392.36 C 1351.49 1363.03 1378.42 1351.34 1402.45 1348.36 z"/>
<path fill="#70011F" fill-rule="evenodd" d="M 886.558 1212.43 C 887.39 1212.41 888.222 1212.4 889.054 1212.41 C 963.668 1213.12 1006.19 1268.19 1007.99 1339.97 C 1008.18 1347.67 1008.03 1355.41 1007.98 1363.11 C 950.43 1363.48 888.523 1361.79 831.346 1363.55 C 834.896 1385.5 843.738 1404.79 864.419 1415.23 C 894.197 1430.27 932.464 1416.24 947.118 1386.94 C 957.765 1392.69 968.514 1398.24 979.361 1403.61 C 987.187 1407.64 994.176 1411.66 1001.75 1416.07 C 998.447 1420.63 994.916 1425.55 991.299 1429.84 C 969.25 1455.72 937.87 1471.84 903.992 1474.69 C 869.928 1477.55 836.445 1468.12 810.653 1445.58 C 737.11 1381.3 756.313 1241.56 856.677 1216.35 C 867.304 1213.68 875.68 1213.01 886.558 1212.43 z M 888.271 1262.4 C 924.671 1262.87 936.35 1284.42 943.524 1315.36 C 925.676 1315.63 907.826 1315.79 889.976 1315.85 C 870.718 1315.86 851.46 1315.75 832.204 1315.51 C 839.564 1284.78 854.271 1265.01 888.271 1262.4 z"/>
<path fill="#70011F" d="M 537.581 1111.91 L 602.057 1112.11 L 600.729 1308.22 L 682.278 1218.49 C 708.849 1219.3 738.185 1218.65 764.959 1218.57 C 735.892 1249.15 706.557 1278.02 677.65 1309.27 C 703.449 1348.49 728.294 1389.62 753.392 1429.45 C 762.003 1442.23 770.209 1455.87 778.336 1469.02 C 754.592 1467.89 727.87 1468.35 703.95 1468.3 C 684.014 1432.58 656.778 1391.19 634.663 1355.79 C 623.465 1366.94 612.003 1379.41 601.057 1390.92 L 600.938 1468.86 C 580.035 1467.94 558.151 1468.26 537.163 1468.31 C 537.881 1438.86 537.095 1409.05 537.443 1379.58 C 538.49 1290.79 536.076 1200.55 537.581 1111.91 z"/>
<path fill="#70011F" d="M 1154.02 1213.36 C 1158.45 1212.92 1162.9 1212.7 1167.35 1212.71 C 1192.36 1212.6 1214.2 1221.24 1231.95 1239.01 C 1243.88 1250.92 1252.35 1265.85 1256.45 1282.2 C 1262.82 1306.69 1261.12 1346.48 1261.06 1373.25 L 1260.95 1468.67 C 1240.39 1467.74 1218.16 1468.19 1197.46 1468.25 C 1197.98 1438.68 1197.48 1408.56 1197.67 1378.92 C 1197.15 1358.56 1198.71 1337.86 1196.61 1317.58 C 1192.29 1276.06 1165.21 1258.19 1126.96 1276.03 C 1123.56 1278.42 1121.05 1280.23 1118.11 1283.22 C 1110.3 1291.13 1104.71 1300.96 1101.91 1311.71 C 1097.68 1328.58 1098.95 1362.08 1098.95 1380.98 L 1099.02 1468.56 C 1078.41 1467.87 1056.76 1468.3 1036.06 1468.43 L 1035.34 1218.56 C 1056.05 1218.83 1076.77 1218.86 1097.49 1218.66 L 1097.21 1245.39 C 1113.84 1226.15 1128.71 1216.97 1154.02 1213.36 z"/>
</svg>
```

- [ ] **Step 2: Rasterizar em PNG (uso único, o resultado é commitado)**

Este PNG é usado só no servidor (GD não lê SVG). Rode dentro do WSL:

```bash
wsl -e bash -lc "cd ~/Projetos/Ingresso && mkdir -p public/img && npx --yes sharp-cli -i public/kena-mark-vinho.svg -o public/img/kena-qr-mark.png -f png resize 512 512"
```

Expected: cria `public/img/kena-qr-mark.png`, ~512x512, fundo transparente, "K" vinho.

- [ ] **Step 3: Verificar**

```bash
wsl -e bash -lc "cd ~/Projetos/Ingresso && file public/img/kena-qr-mark.png"
```

Expected: `PNG image data, 512 x 512, ... RGBA`.

- [ ] **Step 4: Commit**

```bash
wsl -e bash -lc "cd ~/Projetos/Ingresso && git add public/kena-mark-vinho.svg public/img/kena-qr-mark.png && git commit -m 'feat: adiciona monograma vinho para o QR estilizado'"
```

---

### Task 2: Trocar a biblioteca de QR no backend

**Files:**
- Modify: `composer.json`

`endroid/qr-code` não tem suporte a módulos com cantos arredondados. `chillerlan/php-qrcode` tem (via classe de output customizável, ver Task 3).

- [ ] **Step 1: Remover endroid, adicionar chillerlan**

```bash
wsl -e bash -lc "cd ~/Projetos/Ingresso && composer remove endroid/qr-code && composer require chillerlan/php-qrcode:^6.0"
```

Expected: `composer.json` passa a ter `"chillerlan/php-qrcode": "^6.0"` no lugar de `"endroid/qr-code": "*"`. Sem erros de instalação (PHP do WSL é 8.4, o pacote exige ^8.2 — compatível).

- [ ] **Step 2: Confirmar**

```bash
wsl -e bash -lc "cd ~/Projetos/Ingresso && composer show chillerlan/php-qrcode | head -3"
```

Expected: mostra `versions : * 6.0.x` (ou superior dentro de `^6.0`).

- [ ] **Step 3: Commit**

```bash
wsl -e bash -lc "cd ~/Projetos/Ingresso && git add composer.json composer.lock && git commit -m 'chore: troca endroid/qr-code por chillerlan/php-qrcode'"
```

---

### Task 3: Classe de renderização `KenaQrRenderer`

**Files:**
- Create: `app/Support/KenaQrRenderer.php`

Estende `chillerlan\QRCode\Output\QRGdImagePNG` e reaproveita a técnica oficial de módulos arredondados da própria lib (`examples/imageWithRoundedShapes.php` da tag `6.0.1`, conferida linha a linha no código-fonte real — não é invenção), adaptada para: (a) tinta única em todo tipo de módulo, incluindo os marcadores de canto — via override de `getDefaultModuleValue()` — e (b) uma área central em branco reservada visualmente (sem usar `addLogoSpace`, que exige uma versão de QR fixa; aqui calculamos a área como uma fração do tamanho real do matrix, então funciona pra qualquer comprimento de dado) onde o monograma é sobreposto.

- [ ] **Step 1: Criar o arquivo**

```php
<?php

namespace App\Support;

use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Data\QRMatrix;
use chillerlan\QRCode\Output\QRCodeOutputException;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\Settings\SettingsContainerInterface;

/**
 * Renderiza o QR do ingresso no estilo Kena: módulos com blobs de cantos
 * arredondados (técnica oficial de examples/imageWithRoundedShapes.php do
 * chillerlan/php-qrcode, tag 6.0.1), tinta única para dados e marcadores de
 * canto (sem cor de destaque no "olho" do QR), e o monograma "K" do Kena
 * sobreposto no centro. Não reserva espaço no matrix pro logo — em vez
 * disso deixa a área central em branco no próprio desenho e conta com a
 * correção de erro nível H (configurada em App\Support\QrImage) pra
 * continuar legível.
 */
final class KenaQrRenderer extends QRGdImagePNG
{
    private const INK = [0x3A, 0x2A, 0x24];

    private const CREAM = [0xF4, 0xEF, 0xE7];

    private const LOGO_FRACTION = 0.38;

    private const DEFAULT_LOGO_PATH = 'img/kena-qr-mark.png';

    public function __construct(SettingsContainerInterface|QROptions|iterable $options, QRMatrix $matrix)
    {
        if (is_iterable($options)) {
            $options = new QROptions($options);
        }

        // habilita o upscale interno da lib (renderiza em 10x e reduz no
        // final) pra dar anti-aliasing nos cantos arredondados em escalas
        // pequenas — precisa ser setado antes do parent::__construct.
        $options->gdImageUseUpscale = true;
        $options->drawCircularModules = true;

        parent::__construct($options, $matrix);
    }

    protected function getDefaultModuleValue(bool $isDark): int
    {
        return $this->prepareModuleValue($isDark ? self::INK : self::CREAM);
    }

    /** [início, fim) em módulos do quadrado central reservado ao logo. */
    private function logoBoundsInModules(): array
    {
        $count = $this->matrix->moduleCount;
        $size = (int) round($count * self::LOGO_FRACTION);
        $start = (int) floor(($count - $size) / 2);

        return [$start, $start + $size];
    }

    protected function module(int $x, int $y, int $M_TYPE): void
    {
        [$logoStart, $logoEnd] = $this->logoBoundsInModules();

        if ($x >= $logoStart && $x < $logoEnd && $y >= $logoStart && $y < $logoEnd) {
            return;
        }

        $neighbours = $this->matrix->checkNeighbours($x, $y);

        $x1 = $x * $this->scale;
        $y1 = $y * $this->scale;
        $x2 = ($x + 1) * $this->scale;
        $y2 = ($y + 1) * $this->scale;
        $rectsize = (int) ($this->scale / 2);

        $light = $this->getModuleValue($M_TYPE);
        $dark = $this->getModuleValue($M_TYPE | QRMatrix::IS_DARK);

        if ($neighbours & (1 << 7)) {
            imagefilledrectangle($this->image, $x1, $y1, $x1 + $rectsize, $y1 + $rectsize, $light);
            imagefilledrectangle($this->image, $x1, $y2 - $rectsize, $x1 + $rectsize, $y2, $light);
        }

        if ($neighbours & (1 << 3)) {
            imagefilledrectangle($this->image, $x2 - $rectsize, $y1, $x2, $y1 + $rectsize, $light);
            imagefilledrectangle($this->image, $x2 - $rectsize, $y2 - $rectsize, $x2, $y2, $light);
        }

        if ($neighbours & (1 << 1)) {
            imagefilledrectangle($this->image, $x1, $y1, $x1 + $rectsize, $y1 + $rectsize, $light);
            imagefilledrectangle($this->image, $x2 - $rectsize, $y1, $x2, $y1 + $rectsize, $light);
        }

        if ($neighbours & (1 << 5)) {
            imagefilledrectangle($this->image, $x1, $y2 - $rectsize, $x1 + $rectsize, $y2, $light);
            imagefilledrectangle($this->image, $x2 - $rectsize, $y2 - $rectsize, $x2, $y2, $light);
        }

        if (! $this->matrix->check($x, $y)) {
            if (($neighbours & 1) && ($neighbours & (1 << 7)) && ($neighbours & (1 << 1))) {
                imagefilledrectangle($this->image, $x1, $y1, $x1 + $rectsize, $y1 + $rectsize, $dark);
            }

            if (($neighbours & (1 << 1)) && ($neighbours & (1 << 2)) && ($neighbours & (1 << 3))) {
                imagefilledrectangle($this->image, $x2 - $rectsize, $y1, $x2, $y1 + $rectsize, $dark);
            }

            if (($neighbours & (1 << 7)) && ($neighbours & (1 << 6)) && ($neighbours & (1 << 5))) {
                imagefilledrectangle($this->image, $x1, $y2 - $rectsize, $x1 + $rectsize, $y2, $dark);
            }

            if (($neighbours & (1 << 3)) && ($neighbours & (1 << 4)) && ($neighbours & (1 << 5))) {
                imagefilledrectangle($this->image, $x2 - $rectsize, $y2 - $rectsize, $x2, $y2, $dark);
            }
        }

        imagefilledellipse(
            $this->image,
            (int) ($x * $this->scale + $this->scale / 2),
            (int) ($y * $this->scale + $this->scale / 2),
            $this->scale - 1,
            $this->scale - 1,
            $light,
        );
    }

    public function dump(string|null $file = null, string|null $logo = null): string
    {
        $logo ??= public_path(self::DEFAULT_LOGO_PATH);

        $this->options->returnResource = true;

        if (! is_file($logo) || ! is_readable($logo)) {
            throw new QRCodeOutputException("logo inválido: {$logo}");
        }

        parent::dump($file);

        $im = imagecreatefrompng($logo);

        if ($im === false) {
            throw new QRCodeOutputException('imagecreatefrompng() error');
        }

        $w = imagesx($im);
        $h = imagesy($im);

        [$logoStart, $logoEnd] = $this->logoBoundsInModules();
        $logoSizePx = ($logoEnd - $logoStart) * $this->scale;
        $logoOffsetPx = $logoStart * $this->scale;

        imagecopyresampled($this->image, $im, $logoOffsetPx, $logoOffsetPx, 0, 0, $logoSizePx, $logoSizePx, $w, $h);

        $imageData = $this->dumpImage();

        $this->saveToFile($imageData, $file);

        if ($this->options->outputBase64) {
            $imageData = $this->toBase64DataURI($imageData);
        }

        return $imageData;
    }
}
```

- [ ] **Step 2: Rodar o PHPStan só nesse arquivo pra pegar erro de tipo/namespace cedo**

```bash
wsl -e bash -lc "cd ~/Projetos/Ingresso && vendor/bin/phpstan analyse app/Support/KenaQrRenderer.php --level 7"
```

Expected: sem erros. Se algum símbolo (`QRMatrix::IS_DARK`, `checkNeighbours`, `moduleCount`, etc.) não existir na versão instalada, o PHPStan aponta exatamente onde — ajuste consultando `vendor/chillerlan/php-qrcode/src/Data/QRMatrix.php` e `vendor/chillerlan/php-qrcode/src/Output/QRGdImage.php` (já usados como referência ao escrever este arquivo).

- [ ] **Step 3: Commit**

```bash
wsl -e bash -lc "cd ~/Projetos/Ingresso && git add app/Support/KenaQrRenderer.php && git commit -m 'feat: renderer de QR no estilo Kena (dots arredondados + logo)'"
```

---

### Task 4: `QrImage::png()` usa o novo renderer

**Files:**
- Modify: `app/Support/QrImage.php`
- Test: `tests/Feature/Kena/KenaQrStyleTest.php`

Mantém a assinatura pública (`png(string $data, int $size = 240): string`) — nenhuma Blade view muda.

**Decisão de escopo:** a moldura tracejada "selo de ingresso" do spec é aplicada só no cliente (modal grande, Task 5). No e-mail, `resources/views/components/mail/ticket-stub.blade.php:4` já tem uma borda tracejada própria (`border-right:1px dashed #382E29`) separando os dados do QR — o PNG cream + cantos arredondados já lê como selo contra o fundo escuro do card, sem precisar desenhar outra moldura dentro do PNG. Não mexer nessa Blade view.

- [ ] **Step 1: Escrever o teste (falha contra a implementação atual, que ainda usa endroid — mas o pacote endroid já foi removido no Task 2, então neste ponto o teste vai falhar por classe/pacote inexistente, não por comportamento errado; isso é esperado)**

```php
<?php

namespace Tests\Feature\Kena;

use App\Support\QrImage;
use chillerlan\QRCode\QRCode;
use Tests\TestCase;

class KenaQrStyleTest extends TestCase
{
    public function test_gera_um_png_valido(): void
    {
        $png = QrImage::png('KNA-000001.abc1234567.deadbeefcafef00d');

        $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $png);
    }

    public function test_qr_gerado_continua_legivel_apos_o_estilo(): void
    {
        $token = 'KNA-000001.abc1234567.deadbeefcafef00d';

        $png = QrImage::png($token);

        $result = (new QRCode)->readFromBlob($png);

        $this->assertSame($token, $result->data);
    }

    public function test_funciona_com_tokens_de_tamanhos_diferentes(): void
    {
        foreach (['KNA-1.aaaaaaaaaa.0000000000000000', 'KNA-999999-XL.zzzzzzzzzz.ffffffffffffffff'] as $token) {
            $png = QrImage::png($token);
            $result = (new QRCode)->readFromBlob($png);

            $this->assertSame($token, $result->data, "falhou para o token: {$token}");
        }
    }
}
```

- [ ] **Step 2: Rodar e confirmar que falha**

```bash
wsl -e bash -lc "cd ~/Projetos/Ingresso && php artisan test --filter=KenaQrStyleTest"
```

Expected: FAIL (`App\Support\QrImage` ainda referencia `Endroid\QrCode\...`, classe não existe mais).

- [ ] **Step 3: Reescrever `app/Support/QrImage.php`**

```php
<?php

namespace App\Support;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/** Gera o QR de um ingresso como PNG, no estilo Kena, para anexar inline em e-mails. */
final class QrImage
{
    /** Bytes PNG do QR. */
    public static function png(string $data, int $size = 240): string
    {
        $options = new QROptions([
            'eccLevel' => EccLevel::H,
            'outputBase64' => false,
            'bgColor' => [0xF4, 0xEF, 0xE7],
        ]);

        $qrcode = new QRCode($options);
        $qrcode->addByteSegment($data);
        $matrix = $qrcode->getQRMatrix();

        $options->scale = max(4, (int) round($size / $matrix->moduleCount));

        return (new KenaQrRenderer($options, $matrix))->dump();
    }
}
```

- [ ] **Step 4: Rodar o teste de novo**

```bash
wsl -e bash -lc "cd ~/Projetos/Ingresso && php artisan test --filter=KenaQrStyleTest"
```

Expected: PASS (3 testes). Se `test_qr_gerado_continua_legivel_apos_o_estilo` falhar por causa da área do logo (0.38 cobrindo demais pra um QR pequeno, quebrando um pattern estrutural), reduza `LOGO_FRACTION` em `KenaQrRenderer` (ex. para `0.32`) e rode de novo — o teste é o guard-rail exato pra achar esse limite com os tokens reais do projeto.

- [ ] **Step 5: Rodar a suíte toda pra garantir que nada mais quebrou (Mailables usam `QrImage::png`)**

```bash
wsl -e bash -lc "cd ~/Projetos/Ingresso && php artisan test"
```

Expected: todos os testes verdes (inclui os Mailables `TicketsIssuedMail`/`TicketTransferredMail` que embutem esse PNG).

- [ ] **Step 6: Commit**

```bash
wsl -e bash -lc "cd ~/Projetos/Ingresso && git add app/Support/QrImage.php tests/Feature/Kena/KenaQrStyleTest.php && git commit -m 'feat: QR do e-mail no estilo Kena (dots arredondados + logo)'"
```

---

### Task 5: Cliente — `qr-code-styling` no lugar de `qrcode.react`

**Files:**
- Modify: `package.json`
- Modify: `resources/js/components/atoms/QrCode.tsx`

- [ ] **Step 1: Trocar a dependência**

```bash
wsl -e bash -lc "cd ~/Projetos/Ingresso && npm uninstall qrcode.react && npm install qr-code-styling@^1.9.2"
```

- [ ] **Step 2: Reescrever `resources/js/components/atoms/QrCode.tsx`**

```tsx
import { useEffect, useRef } from 'react';
import QRCodeStyling from 'qr-code-styling';
import { cn } from '@/lib/utils';

interface QrCodeProps {
    value: string;
    size?: number;
    /** Moldura tracejada de "selo de ingresso" ao redor do QR. */
    frame?: boolean;
    className?: string;
}

const INK = '#3a2a24';
const CREAM = '#F4EFE7';

/**
 * QR no estilo Kena: dots com cantos arredondados (não círculos), marcadores
 * de canto na mesma tinta (sem destaque de cor), fundo creme e o monograma
 * Kena sobreposto no centro. Correção de erro alta (H) compensa a área
 * coberta pelo logo.
 */
export function QrCode({ value, size = 72, frame = false, className }: QrCodeProps) {
    const containerRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const container = containerRef.current;
        if (!container) {
            return;
        }

        const qr = new QRCodeStyling({
            width: size,
            height: size,
            type: 'svg',
            data: value,
            margin: 4,
            qrOptions: { errorCorrectionLevel: 'H' },
            dotsOptions: { type: 'rounded', color: INK },
            cornersSquareOptions: { type: 'extra-rounded', color: INK },
            cornersDotOptions: { type: 'dot', color: INK },
            backgroundOptions: { color: CREAM },
            image: '/kena-mark-vinho.svg',
            imageOptions: {
                crossOrigin: 'anonymous',
                margin: 4,
                imageSize: 0.38,
                hideBackgroundDots: true,
            },
        });

        container.innerHTML = '';
        qr.append(container);

        return () => {
            container.innerHTML = '';
        };
    }, [value, size]);

    return (
        <div
            className={cn(
                'relative flex items-center justify-center rounded-btn p-2',
                frame && 'border-2 border-dashed border-accent/30',
                className,
            )}
            style={{ background: CREAM }}
            aria-label="QR code do ingresso"
        >
            <div ref={containerRef} />
        </div>
    );
}
```

- [ ] **Step 3: Usar a moldura no modal grande (o card do `TicketStub` já tem seu próprio picote, não precisa de moldura extra)**

Em `resources/js/components/organisms/BuyerTicketList.tsx:149`, troque:

```tsx
<QrCode value={qrTicket.qrToken} size={196} />
```

por:

```tsx
<QrCode value={qrTicket.qrToken} size={196} frame />
```

- [ ] **Step 4: Type-check e build**

```bash
wsl -e bash -lc "cd ~/Projetos/Ingresso && npm run types:check && npm run build"
```

Expected: sem erros.

- [ ] **Step 5: Commit**

```bash
wsl -e bash -lc "cd ~/Projetos/Ingresso && git add package.json package-lock.json resources/js/components/atoms/QrCode.tsx resources/js/components/organisms/BuyerTicketList.tsx && git commit -m 'feat: QR do site no estilo Kena (qr-code-styling, dots arredondados + logo)'"
```

---

### Task 6: Verificação manual (visual + leitura real)

Sem isso o trabalho não está terminado — os testes automatizados cobrem "o PNG do e-mail continua decodificável pela própria lib", mas não cobrem "a câmera do celular/scanner de check-in realmente lê isso".

- [ ] **Step 1: Subir o servidor de dev**

```bash
wsl -e bash -lc "cd ~/Projetos/Ingresso && composer run dev"
```

- [ ] **Step 2: Conferir visualmente no navegador**
  - Login como `helena@veludo.test` / `veludo123`.
  - `/meus-ingressos`: confira a miniatura do QR em cada `TicketStub` (dots arredondados, fundo creme, logo no centro).
  - Clique em "Ver QR": confira o modal grande, com a moldura tracejada.

- [ ] **Step 3: Testar leitura real**
  - Escaneie o QR do modal com a câmera de um celular (qualquer leitor padrão) — deve abrir/reconhecer o conteúdo sem erro.
  - Abra `/dashboard/checkin` (usuário organizador) e escaneie o mesmo QR pelo scanner da tela — deve validar o ingresso normalmente.

- [ ] **Step 4: Conferir o PNG do e-mail**
  - Como o driver de mail é `log` (ver `MAIL_MAILER` no `.env`), depois de emitir/transferir um ingresso, olhe o e-mail renderizado via `php artisan pail` ou o log, ou rode um teste manual que salve o PNG em disco pra abrir localmente:
    ```bash
    wsl -e bash -lc "cd ~/Projetos/Ingresso && php artisan tinker --execute=\"file_put_contents('storage/app/qr-preview.png', App\\Support\\QrImage::png('KNA-PREVIEW.0000000000.0000000000000000'));\""
    ```
  - Abra `storage/app/qr-preview.png` e confira visualmente (dots arredondados, fundo creme, logo, mesma paleta do site).

- [ ] **Step 5: Se algo não ler bem, ajustar `LOGO_FRACTION` em `KenaQrRenderer` (backend) e `imageOptions.imageSize` em `QrCode.tsx` (frontend) juntos, pra manter os dois em sincronia, e repetir a verificação.**

---

### Task 7: Suíte completa e commit final

- [ ] **Step 1: Rodar a suíte completa do projeto**

```bash
wsl -e bash -lc "cd ~/Projetos/Ingresso && composer run ci:check"
```

Expected: lint, format, types:check (PHP + JS) e testes todos verdes.

- [ ] **Step 2: Remover o arquivo de preview gerado no Task 6 (se criado) e confirmar que não sobrou nada solto**

```bash
wsl -e bash -lc "cd ~/Projetos/Ingresso && rm -f storage/app/qr-preview.png && git status"
```

- [ ] **Step 3: Commit final (se sobrou algo não commitado)**

```bash
wsl -e bash -lc "cd ~/Projetos/Ingresso && git add -A && git commit -m 'chore: ajustes finais do estilo de QR do ingresso'"
```

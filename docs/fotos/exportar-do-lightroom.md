# Como exportar as fotos do Lightroom para o Kena

Guia para quem edita as fotos. O Kena monta a galeria sozinho a partir dos arquivos exportados: lê quem está em cada foto, a turma e gera as prévias com marca d'água. Para isso funcionar, três coisas precisam estar certas: **os nomes nos rostos, as subpastas por turma e a opção de pessoas na exportação**.

Vale para o **Lightroom Classic**. Os nomes dos menus podem variar um pouco conforme a versão e o idioma.

## 1. Marque os rostos

1. No módulo **Biblioteca**, abra a visão **Pessoas** (tecla `O`).
2. Se for a primeira vez, autorize a detecção de rostos. O Lightroom agrupa os rostos parecidos.
3. Dê nome a cada grupo usando **o nome completo, igual ao do catálogo da professora** (ex.: `Alice Moura`). É esse nome que os pais veem e buscam.
4. Confirme as sugestões que o Lightroom fizer. É mais ou menos um clique por criança, não um por foto.
5. Rosto que ele não achou (de lado, maquiado, longe): abra a foto em tela (tecla `E`), use a ferramenta de **região de rosto** para desenhar o retângulo e dê o nome.

> Nome escrito de dois jeitos (`Alice Moura` e `Alice M.`) vira duas pessoas no site. Use sempre o mesmo.

## 2. Separe por turma

Organize as fotos em coleções ou pastas por turma ou coreografia (`Baby Class`, `Infantil`, `Juvenil`, `Encerramento`, `Estúdio`). Na exportação, cada turma vai para uma **subpasta com o nome dela**. O Kena usa o nome da subpasta como filtro de turma.

```
Quebra-Nozes 2026/          ← pasta do evento no Google Drive
├── Baby Class/
├── Infantil/
├── Juvenil/
├── Encerramento/
└── Estúdio/
```

## 3. Crie a predefinição de exportação "Kena" (uma vez só)

Em **Arquivo › Exportar**:

| Seção | Configuração |
|---|---|
| Local de exportação | Pasta específica: a pasta do evento dentro do Google Drive. Marque **Colocar em subpasta** e escreva o nome da turma. |
| Configurações do arquivo | **JPEG**, qualidade **90**, espaço de cor **sRGB**. |
| Dimensionamento | **Não redimensionar.** O Kena entrega a foto em alta para quem compra. |
| Metadados | **Todos os metadados.** Deixe **desmarcado** "Remover informações de pessoa". Pode marcar "Remover informações de local" (tira o GPS). |
| Marca-d'água | **Desligada.** O Kena aplica a marca sozinho nas prévias. |

Clique em **Adicionar** (canto inferior esquerdo) e salve como `Kena`. Nas próximas vezes é só escolher a predefinição, ajustar o nome da subpasta e exportar.

> O item mais importante é "Remover informações de pessoa" **desmarcado**. Com ele marcado, os nomes não saem no arquivo e o filtro por rosto fica vazio.

## 4. Regras da pasta do Drive

- Com o Google Drive instalado no computador, o upload é automático: exportou, subiu.
- **Depois que a galeria for publicada, não renomeie, mova nem apague arquivos da pasta.** Quem comprou aquela foto ficaria sem receber.
- Foto nova pode ser adicionada a qualquer momento: o Kena importa de novo.

## 5. Antes do evento inteiro: o teste

Marque os rostos em **2 ou 3 fotos**, exporte com a predefinição para uma pasta de teste no Drive e avise. Conferimos se nomes e rostos estão chegando antes de editar o resto.

# Configurar o Google Drive para as fotos

O editor exporta os JPGs do Lightroom para uma pasta do Google Drive (uma subpasta por turma, como em `exportar-do-lightroom.md`). O Kena lê essa pasta, importa as fotos e, depois do pagamento, entrega os originais direto de lá. O comprador nunca vê link do Drive: o download passa pelo servidor do Kena.

Para o Kena entrar no Drive sem login de pessoa, usamos uma **conta de serviço** do Google: um "usuário robô" com e-mail próprio, ao qual você dá acesso só à pasta do evento (somente leitura). É uma configuração feita uma vez.

## 1. Criar o projeto no Google Cloud

1. Entre em <https://console.cloud.google.com> com a conta Google da Somo.
2. No topo, clique no seletor de projetos e depois em **Novo projeto**.
3. Nome: `Kena Fotos`. Clique em **Criar** e selecione o projeto criado.

## 2. Ativar a Google Drive API

1. No menu, vá em **APIs e serviços > Biblioteca**.
2. Busque por **Google Drive API**, abra e clique em **Ativar**.

## 3. Criar a conta de serviço

1. Vá em **APIs e serviços > Credenciais**.
2. Clique em **Criar credenciais > Conta de serviço**.
3. Nome: `kena-fotos`. Clique em **Criar e continuar**.
4. Nas telas de papéis e de acesso, não precisa escolher nada: clique em **Continuar** e depois em **Concluído**.
5. Na lista de contas de serviço, copie o **e-mail** dela (termina em `.iam.gserviceaccount.com`). Você vai usar no passo 6. O painel do Kena também mostra esse e-mail na tela de ajustes da galeria, com um botão de copiar.

## 4. Baixar o arquivo JSON da chave

1. Clique na conta de serviço, aba **Chaves**.
2. **Adicionar chave > Criar nova chave > JSON > Criar**.
3. O navegador baixa um arquivo `.json`. Guarde com cuidado: quem tem esse arquivo lê tudo o que foi compartilhado com a conta de serviço. Nunca coloque no git, em e-mail ou em chat.

## 5. Colocar o arquivo no servidor

No servidor da Somo (`ssh davi@79.143.190.100`), na pasta do Kena:

```bash
# do seu computador, envie o arquivo (ajuste o caminho do Kena no servidor)
scp ~/Downloads/kena-fotos-*.json davi@79.143.190.100:/CAMINHO/DO/KENA/storage/app/private/google-drive.json
```

Depois, no servidor, restrinja a permissão e aponte o `.env`:

```bash
chmod 600 storage/app/private/google-drive.json
echo 'GOOGLE_DRIVE_CREDENTIALS=storage/app/private/google-drive.json' >> .env
php artisan config:clear
```

`storage/app/private` não é público e fica fora do git. O caminho pode ser absoluto ou relativo à raiz do projeto.

## 6. Compartilhar a pasta do evento

1. No Google Drive, abra a pasta do evento (a que tem as subpastas das turmas).
2. **Compartilhar**, cole o e-mail da conta de serviço e deixe como **Leitor**. Desmarque "Notificar pessoas" se aparecer.
3. Copie o link da pasta (**Compartilhar > Copiar link**). Pode ser o link completo, como `https://drive.google.com/drive/folders/1AbC...`.

A pasta precisa continuar compartilhada depois da venda: é de lá que saem os originais entregues aos compradores.

## 7. Importar pelo painel

1. No painel, abra **Fotos**, escolha o evento e entre em **Ajustes**.
2. Em **Fotos do Google Drive**, cole o link da pasta e clique em **Salvar alterações**.
3. Clique em **Importar agora**. A importação roda em segundo plano (fila) e a página mostra o resultado: importadas, já existiam, falharam e pessoas novas.

A fila precisa de um worker rodando no servidor (`php artisan queue:work`, normalmente já mantido pelo supervisor ou pelo serviço do Kena).

Pode importar de novo quando o editor acrescentar fotos: só entram as que ainda não existem na galeria. Pela linha de comando também funciona, com o link no lugar da pasta local:

```bash
php artisan fotos:importar quebra-nozes "https://drive.google.com/drive/folders/1AbC..."
```

## Se algo der errado

| Mensagem no painel | O que fazer |
|---|---|
| "Google Drive não configurado" | Falta `GOOGLE_DRIVE_CREDENTIALS` no `.env` ou o arquivo não está no caminho (passo 5). |
| "Pasta não encontrada no Drive. Compartilhe a pasta..." | A pasta não foi compartilhada com o e-mail da conta de serviço, ou o link está errado (passo 6). |
| "A Google Drive API não está ativada" | Ative a API no projeto certo (passo 2). Pode levar alguns minutos para valer. |
| "O Google recusou as credenciais" | O JSON está errado, corrompido ou a chave foi apagada. Crie uma chave nova (passo 4). |
| "Já tem uma importação em andamento" | Aguarde terminar. Se passou de 2 horas, o botão libera de novo. |
| Algumas fotos em "falharam" | O motivo aparece ao lado de cada uma. Rode a importação de novo: só as que falharam são tentadas outra vez. |

Se a entrega de um original falhar depois da compra (arquivo apagado do Drive, por exemplo), o comprador vê o aviso de que o original ainda está sendo preparado, e o erro fica registrado no log do Kena.

# Login do painel separado + slug /painel

Data: 2026-07-17
Status: aprovado por Davi

## Problema

Hoje comprador e organizador entram pela mesma porta (`/login`), na mesma tabela
`users`, sob o mesmo guard `web`. Quem compra um ingresso e quem administra o
evento compartilham conta e sessão — só o papel (`role`) e a flag `is_admin`
separam. A URL do painel também é `/dashboard`, que o Davi não quer.

## Decisões

1. **Contas separadas.** Conta de painel ≠ conta de comprador. Tabela e guard
   próprios, sessões independentes: estar logado como comprador não autentica no
   painel, e vice-versa.
2. **Escopo da nova tabela:** `organizer` **e** `staff`. Quem opera o evento
   (gestão e check-in) tem conta de painel. `users` passa a ser só comprador.
3. **Login do painel:** e-mail + senha apenas. Sem Google, sem passkey, sem magic
   link — esses continuam só no comprador.
4. **Migração:** move as linhas `organizer`/`staff` de `users` para a nova
   tabela **preservando o hash da senha**. A mesma senha continua valendo, agora
   em `/painel/login`.
5. **Nome:** `panel_users` / `PanelUser` — descreve quem usa o painel sem chamar
   o staff de check-in de "admin".
6. **Limpeza:** `users.role` e `users.is_admin` são removidas. Papel passa a
   existir num lugar só.
7. **Slug:** `/dashboard/*` → `/painel/*`.

## Arquitetura

**Model/tabela.** `panel_users`: `id`, `name`, `email` (unique), `password`,
`role` (`organizer`|`staff`), `remember_token`, timestamps. `PanelUser` estende
`Authenticatable`. Sem `is_admin`: `role=organizer` já é o nível máximo.

**Guard.** `config/auth.php` ganha o guard `painel` (driver `session`, provider
`panel_users`) e o provider `panel_users` (eloquent, `PanelUser::class`). O guard
`web` continua servindo o comprador. Guards de sessão distintos → cookies e
sessões independentes, que é o que dá a separação real.

**Rotas.**
- `/painel/login` — GET (guest:painel) e POST; `/painel/logout` — POST.
- `/painel/*` — `auth:painel` + `can-manage`; gestão sensível sob `can-organize`.
- Renomeia todo o prefixo `/dashboard` para `/painel`. Nomes de rota (`admin.*`)
  ficam; muda só a URL. A rota chamada `dashboard` passa a se chamar `painel`.

**Autorização.** `EnsureCanManage` e `EnsureCanManageOrganization` passam a ler
`$request->user('painel')`. Regra preservada: qualquer `PanelUser` alcança o
check-in; só `role=organizer` alcança gestão.

**Inertia.** `HandleInertiaRequests` compartilha `auth.user` (comprador) e
`auth.panelUser` (painel). O front do painel lê `panelUser`; `AdminNavList` troca
`user.role === 'organizer' || user.is_admin` por `panelUser.role === 'organizer'`.

**Seed.** `ADMIN_EMAIL`/`ADMIN_PASSWORD` criam um `PanelUser` com
`role=organizer`. Usuários demo fora de produção: um `PanelUser` organizer, um
`PanelUser` staff e um `User` comprador.

## Fluxo de dados

Comprador: `/login` → guard `web` → sessão `web` → rotas de compra.
Painel: `/painel/login` → guard `painel` → sessão `painel` → `/painel/*`.
Não há ponte entre os dois. Quem administra e quer comprar cria conta de
comprador separada, com o mesmo e-mail se quiser (tabelas distintas).

## Erros e bordas

- Login de painel com e-mail de comprador → "Credenciais inválidas" (a conta não
  existe naquele guard). Sem vazar se o e-mail existe do outro lado.
- `/painel/*` sem sessão de painel → redireciona para `/painel/login`, não para
  `/login`.
- Logout de um guard não derruba o outro.
- Migração é reversível: o `down()` devolve as linhas para `users` com o papel e
  o hash originais, e recria as colunas.
- Rate limit no `/painel/login` igual ao do `/login`.

## Testes

- Comprador logado não alcança `/painel/*`.
- `PanelUser` logado não alcança rota de comprador autenticada.
- `staff` alcança `/painel/checkin` e recebe 403 em `/painel/eventos`.
- `organizer` alcança tudo.
- Migração: usuário `organizer` em `users` vira `PanelUser` com o mesmo hash e
  some de `users`.
- Login de painel com credencial de comprador falha.
- Os 17 arquivos de teste que citam `is_admin`/`role`/`dashboard` são adaptados.

## Fora de escopo

Google/passkey/magic link no painel; painel de SMTP; unificar `users` e
`panel_users` no futuro.

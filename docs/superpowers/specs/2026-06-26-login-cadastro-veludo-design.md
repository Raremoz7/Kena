# Login & Cadastro Veludo + Google OAuth — Design

**Data:** 2026-06-26
**Projeto:** Ingresso (sistema de ingressos com assento marcado)
**Fatia:** Personalização das telas de autenticação (login + cadastro) na linguagem Veludo, com login social Google e coleta de telefone/CPF no cadastro.

---

## 1. Objetivo

Substituir as telas de auth do starter kit (Fortify, em inglês, layout genérico) por telas personalizadas na cara do design system **Veludo** (dark, premium/teatral, acento vinho), em português, com:

1. Layout **card centralizado** (opção B aprovada) reutilizável por qualquer evento futuro.
2. Botão **"Continuar com Google"** funcional ponta a ponta (Laravel Socialite).
3. Cadastro ampliado: além de nome/e-mail/senha, coletar **telefone/WhatsApp** e **CPF** (com validação de dígitos verificadores).

Critério de sucesso: usuário consegue (a) entrar com e-mail/senha, (b) entrar/cadastrar com Google, (c) criar conta preenchendo telefone + CPF válidos — tudo com o visual Veludo e textos em PT-BR.

## 2. Escopo

### Dentro
- Restyle Veludo do layout de auth (card centralizado) — herdado por todas as telas de auth.
- Tradução PT-BR das telas de auth (login, cadastro, esqueci a senha, redefinir senha, verificar e-mail, 2FA, confirmar senha).
- Login com Google via Socialite (rotas redirect/callback, controller, config, migration `google_id`).
- Campos telefone + CPF no cadastro (front com máscara, back com validação + persistência).
- Migration: `phone`, `cpf`, `google_id` em `users` + `password` nullable.

### Fora (fatias futuras)
- Outros provedores sociais (Apple, Facebook).
- Vincular/desvincular conta Google numa conta de e-mail já existente, pela tela de settings.
- Verificação de telefone por SMS.
- Edição de telefone/CPF na tela de perfil (settings) — só o cadastro coleta nesta fatia.
- Renomear as rotas Fortify para slugs PT (`/entrar`, `/criar-conta`) — mantemos `/login` e `/register` padrão.

## 3. Decisões aprovadas (brainstorming)

| Decisão | Escolha |
|---|---|
| Layout | **B — card centralizado** (base `auth-card-layout`, restyle Veludo) |
| Google login | **Funcional de verdade** — Socialite completo; Davi gera as credenciais no Google Cloud |
| Campos extras no cadastro | **Telefone/WhatsApp + CPF** |
| Confirmar senha no cadastro | **Removido** — campo único de senha com mostrar/ocultar |
| Idioma | **Português (PT-BR)** em todas as telas de auth |

## 4. Frontend

### 4.1 Layout (card Veludo)
Arquivo `resources/js/layouts/auth/auth-card-layout.tsx` (usado por `auth-layout.tsx`; trocar o template de `auth-simple-layout` para `auth-card-layout`).

- Container: tela cheia centralizada sobre `bg-background` (fundo Veludo escuro).
- Card: `bg-card` + `border-border` + `rounded-card`, largura `max-w-md`, padding generoso.
- Topo do card: marca Veludo — quadrado vinho (`bg-primary`, `rounded-btn`) com ícone de máscara teatral + título em `font-display` (Oswald).
- `CardTitle` em `font-display`; `CardDescription` em `text-muted-foreground`.
- Link da logo aponta para `home()`.

### 4.2 Bloco social reutilizável
Novo componente `resources/js/components/auth/social-auth.tsx`:
- Botão full-width "Continuar com Google" — variante clara (`bg-foreground`/texto escuro ou outline), ícone Google.
- Divisor "ou" (linha + label centralizado) abaixo do botão.
- O botão é um link (`<a href>`) para a rota `auth/google/redirect` (navegação full-page, não Inertia — é redirect externo do OAuth).
- Ícone Google: SVG inline próprio (multicolor "G") em `resources/js/components/icons/google-icon.tsx` (lucide não tem o logo colorido).

### 4.3 Tela de login (`resources/js/pages/auth/login.tsx`)
Ordem dentro do card:
1. `<SocialAuth />` (Google + divisor "ou")
2. Campo e-mail (`Label` "E-mail")
3. Campo senha (`PasswordInput`, com link "Esqueci a senha?" à direita do label)
4. Checkbox "Lembrar de mim"
5. Botão "Entrar" (primary/vinho)
6. Rodapé: "Não tem conta? **Criar conta**"

Textos traduzidos. `Login.layout` title → "Bem-vindo de volta", description → "Entre para garantir seu lugar".

### 4.4 Tela de cadastro (`resources/js/pages/auth/register.tsx`)
Ordem dentro do card:
1. `<SocialAuth />`
2. Nome completo
3. E-mail
4. Telefone / WhatsApp — `MaskedInput` máscara BR `(00) 00000-0000`
5. CPF — `MaskedInput` máscara `000.000.000-00`
6. Senha (`PasswordInput`) — **sem** campo de confirmação
7. Botão "Criar conta"
8. Rodapé: "Já tem conta? **Entrar**"

`Register.layout` title → "Criar sua conta", description → "Seus dados para emissão e contato".
`Form` `resetOnSuccess` passa a `['password']` (sem `password_confirmation`).

### 4.5 Máscara de input
Novo componente `resources/js/components/masked-input.tsx`:
- Wrapper sobre `Input` que aplica máscara client-side (telefone e CPF) e mantém o `name` para o submit padrão do `Form` do Inertia.
- Implementação leve (sem dependência nova): função de máscara por padrão de string. Envia o valor mascarado; o back normaliza para dígitos.

### 4.6 Telas herdadas (só tradução)
`forgot-password.tsx`, `reset-password.tsx`, `verify-email.tsx`, `confirm-password.tsx`, `two-factor-challenge.tsx`: traduzir textos visíveis (labels, botões, descrições). Estrutura mantida; visual vem do layout card.

## 5. Backend

### 5.1 Dependência
`composer require laravel/socialite`.

### 5.2 Migration
Nova migration `add_social_and_profile_columns_to_users_table`:
- `phone` string nullable
- `cpf` string(11) nullable unique (armazenado só com dígitos)
- `google_id` string nullable unique
- `password` → tornar **nullable** (contas Google não têm senha). Usar `->nullable()->change()` (requer `doctrine/dbal`? No Laravel 11+/SQLite o `change()` é nativo — confirmar; se necessário, recriar coluna).

> Nota SQLite: o projeto usa SQLite. `->change()` em SQLite no Laravel 11+ é suportado nativamente. Caso o `change()` da senha dê problema no SQLite, alternativa: a migration recria a tabela via schema, mas a primeira opção é a esperada.

### 5.3 Model `User`
- Adicionar `phone`, `cpf`, `google_id` ao atributo `#[Fillable([...])]`.
- `cpf` e `google_id` não precisam estar em `#[Hidden]` (não são segredos), mas `cpf` pode ser ocultado da serialização pública por privacidade — **incluir `cpf` em `#[Hidden]`** para não vazar em props Inertia acidentais.

### 5.4 Regras de validação (`ProfileValidationRules`)
Adicionar **apenas** dois helpers novos ao trait (reutilizáveis, sem mexer no `profileRules()` existente):
- `phoneRules()`: `['required', 'string', 'max:20']` (validação de formato leve; normalização no controller/action).
- `cpfRules(?int $userId = null)`: `['required', 'string', new ValidCpf, Rule::unique(User::class, 'cpf')->ignore($userId)]`.

`profileRules()` (usado pela tela de settings) **permanece inalterado** — settings não coleta phone/cpf nesta fatia. Os novos campos entram só no fluxo de cadastro: o `CreateNewUser` compõe name + email + `phoneRules()` + `cpfRules()` + senha localmente.

### 5.5 Regra CPF
Nova regra `app/Rules/ValidCpf.php` (implements `ValidationRule`): normaliza para dígitos, valida tamanho (11), rejeita sequências repetidas e confere os dois dígitos verificadores.

### 5.6 `CreateNewUser`
- Validar: name, email, phone, cpf (regras acima) + senha **sem** `confirmed` (regra local `['required','string', Password::default()]`, não a `passwordRules()` compartilhada — para preservar o `confirmed` no reset de senha).
- Normalizar phone e cpf para dígitos antes de salvar.
- `User::create` com name, email, phone, cpf, password.

### 5.7 Google OAuth
- `config/services.php`: bloco `google` com `client_id`, `client_secret`, `redirect` lendo `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` (default `${APP_URL}/auth/google/callback`).
- `.env.example`: adicionar as três chaves vazias + comentário de onde gerar.
- Controller `app/Http/Controllers/Auth/GoogleAuthController.php`:
  - `redirect()`: `Socialite::driver('google')->redirect()`.
  - `callback()`: obtém usuário Google; procura por `google_id`; se não achar, procura por `email`; se achar por email, vincula `google_id`; se não existir, cria conta (name, email, google_id, sem senha, `email_verified_at = now()` pois o Google já verificou). Faz `Auth::login($user, remember: true)` e redireciona para `config('fortify.home')` (`/meus-ingressos`). Em erro/cancelamento, redireciona para `/login` com mensagem `status`.
- Rotas em `routes/web.php` (fora do middleware `auth`):
  - `GET /auth/google/redirect` → `GoogleAuthController@redirect` (name `auth.google.redirect`)
  - `GET /auth/google/callback` → `GoogleAuthController@callback` (name `auth.google.callback`)
- Wayfinder: rodar geração de rotas para o front referenciar (`@/routes`), ou usar URL literal no `<a href>` do botão. Decisão: **URL literal** `/auth/google/redirect` no botão (evita dependência de regen do wayfinder e é um redirect externo).

## 6. Fluxos

### 6.1 Login e-mail/senha
Sem mudança de fluxo (Fortify). Só visual/idioma.

### 6.2 Cadastro
Form → `CreateNewUser` valida (incl. CPF + unicidade) → cria conta → Fortify loga → redireciona `/meus-ingressos`. Erros de validação voltam por campo (InputError).

### 6.3 Google
Botão → `/auth/google/redirect` → Google → `/auth/google/callback`:
- `google_id` existe → loga.
- e-mail existe (conta criada por senha) → vincula `google_id` e loga.
- ninguém → cria conta (sem phone/cpf — coletados depois, fora desta fatia) e loga.

> Consequência: conta criada via Google fica **sem telefone/CPF**. Aceitável nesta fatia (esses dados podem ser exigidos no checkout numa fatia futura). Documentado como limitação conhecida.

## 7. Configuração externa (Davi)
No Google Cloud Console: criar projeto → OAuth consent screen → Credentials → OAuth Client ID (tipo Web). Authorized redirect URI: `http://localhost:8000/auth/google/callback` (dev) e a URL de produção quando houver. Copiar Client ID/Secret para o `.env`:
```
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/callback"
```

## 8. Testes
- Feature test: cadastro com CPF válido cria usuário com phone/cpf normalizados; CPF inválido e CPF duplicado são rejeitados.
- Feature test: `ValidCpf` (unit) — válido, inválido, repetido, tamanho errado.
- Feature test: callback Google com mock do Socialite — cria conta nova, vincula conta existente por e-mail, loga conta com `google_id` existente.
- Garantir que `php artisan test`, `npm run types:check` e `npm run build` passam limpos.

## 9. Limitações conhecidas
- Conta criada via Google não tem telefone/CPF (coleta futura).
- Sem verificação de telefone.
- CPF coletado mas ainda não usado na emissão (uso real numa fatia de backend de domínio).

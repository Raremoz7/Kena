# Login & Cadastro Veludo + Google OAuth — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Personalizar login/cadastro na linguagem Veludo (card centralizado, PT-BR), com login Google funcional (Socialite) e coleta de telefone/CPF no cadastro.

**Architecture:** Backend Laravel 12 + Fortify (Socialite para OAuth, regra de CPF própria, migration de colunas novas). Frontend Inertia + React 19 + Tailwind v4, reutilizando o design system Veludo (tokens em `resources/css/app.css`) e o layout de card existente.

**Tech Stack:** Laravel 12, Fortify, Laravel Socialite, Inertia 2, React 19, TypeScript, Tailwind v4, PHPUnit.

---

## Convenções de execução

- **Shell roda no WSL.** Todo comando abaixo deve ser executado como:
  `wsl bash -lc 'cd /home/davi/Projetos/Ingresso && <comando>'`
  (os passos mostram o `<comando>` puro por legibilidade).
- **Sem git neste projeto** (não é repositório). Não há passos de commit. Quando quiser versionar, rode `/iniciar` (sistema de branches Somo) e depois `/salvar`.
- Testes: PHPUnit class-based (`Tests\TestCase` + `RefreshDatabase`), não Pest.
- Verificação final de qualidade: `php artisan test`, `npm run types:check`, `npm run build` — todos limpos.

## Estado inicial conhecido

- `php artisan test --filter=Auth` hoje tem **6 falhas pré-existentes**: asserções de redirect para `route('dashboard')` que deveriam ser `/meus-ingressos` (você mudou `config/fortify.php` → `home = /meus-ingressos`). A Task 1 corrige as adjacentes; a Task 6 corrige a do `RegistrationTest`.
- Login redireciona pós-auth para `config('fortify.home')` = `/meus-ingressos` (= `route('tickets.index')`).

## Estrutura de arquivos

**Backend (criar):**
- `app/Rules/ValidCpf.php` — regra de validação de CPF (dígitos verificadores).
- `app/Http/Controllers/Auth/GoogleAuthController.php` — redirect/callback OAuth.
- `database/migrations/2026_06_26_000000_add_social_and_profile_columns_to_users_table.php`
- `tests/Unit/ValidCpfTest.php`, `tests/Feature/Auth/GoogleAuthTest.php`

**Backend (modificar):**
- `app/Models/User.php` — fillable/hidden + propriedades.
- `app/Concerns/ProfileValidationRules.php` — helpers `phoneRules()`, `cpfRules()`.
- `app/Actions/Fortify/CreateNewUser.php` — validar/normalizar phone+cpf, senha sem `confirmed`.
- `config/services.php` — bloco `google`.
- `.env.example` — chaves Google.
- `routes/web.php` — rotas Google.
- `tests/Feature/Auth/RegistrationTest.php`, `AuthenticationTest.php`, `EmailVerificationTest.php`, `VerificationNotificationTest.php` — asserções de redirect/payload.

**Frontend (criar):**
- `resources/js/components/icons/google-icon.tsx`
- `resources/js/lib/masks.ts`
- `resources/js/components/masked-input.tsx`
- `resources/js/components/auth/social-auth.tsx`

**Frontend (modificar):**
- `resources/js/layouts/auth/auth-card-layout.tsx` — restyle Veludo.
- `resources/js/layouts/auth-layout.tsx` — usar o template card.
- `resources/js/pages/auth/login.tsx`, `register.tsx` — PT + social + campos.
- `resources/js/pages/auth/forgot-password.tsx`, `reset-password.tsx`, `verify-email.tsx`, `confirm-password.tsx`, `two-factor-challenge.tsx` — tradução.

---

## Task 1: Corrigir asserções de redirect pré-existentes

**Files:**
- Modify: `tests/Feature/Auth/AuthenticationTest.php:32`
- Modify: `tests/Feature/Auth/EmailVerificationTest.php:50,98,114`
- Modify: `tests/Feature/Auth/VerificationNotificationTest.php:44`

- [ ] **Step 1: Trocar `route('dashboard'...)` por `route('tickets.index'...)` nas asserções de redirect pós-auth**

Em `AuthenticationTest.php` linha 32:
```php
$response->assertRedirect(route('tickets.index', absolute: false));
```

Em `EmailVerificationTest.php`:
- linha 50: `$response->assertRedirect(route('tickets.index', absolute: false).'?verified=1');`
- linha 98: `$response->assertRedirect(route('tickets.index', absolute: false));`
- linha 114: `->assertRedirect(route('tickets.index', absolute: false).'?verified=1');`

Em `VerificationNotificationTest.php` linha 44:
```php
->assertRedirect(route('tickets.index', absolute: false));
```

- [ ] **Step 2: Rodar a suíte de auth**

Run: `php artisan test --filter=Auth`
Expected: só `RegistrationTest > new users can register` ainda falha (será corrigida na Task 6). As demais (`AuthenticationTest`, `EmailVerificationTest`, `VerificationNotificationTest`) passam.

---

## Task 2: Instalar Socialite + configurar serviço Google

**Files:**
- Modify: `config/services.php`
- Modify: `.env.example`
- Dependency: `laravel/socialite`

- [ ] **Step 1: Instalar o pacote**

Run: `composer require laravel/socialite`
Expected: pacote instalado e auto-descoberto (sem erro).

- [ ] **Step 2: Adicionar o bloco `google` em `config/services.php`**

Antes do `];` final do array, adicionar:
```php
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],
```

- [ ] **Step 3: Adicionar as chaves no `.env.example`**

No final do arquivo:
```
# Google OAuth — gere em https://console.cloud.google.com (APIs & Services > Credentials > OAuth Client ID, tipo Web)
# Authorized redirect URI: ${APP_URL}/auth/google/callback
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/callback"
```

- [ ] **Step 4: Verificar que a app sobe**

Run: `php artisan config:clear && php artisan about | head -5`
Expected: sem erros de configuração.

---

## Task 3: Migration — colunas sociais/perfil + User model

**Files:**
- Create: `database/migrations/2026_06_26_000000_add_social_and_profile_columns_to_users_table.php`
- Modify: `app/Models/User.php`

- [ ] **Step 1: Criar a migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->string('cpf', 11)->nullable()->unique()->after('phone');
            $table->string('google_id')->nullable()->unique()->after('cpf');
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'cpf', 'google_id']);
            $table->string('password')->nullable(false)->change();
        });
    }
};
```

- [ ] **Step 2: Rodar a migration**

Run: `php artisan migrate`
Expected: migration aplicada sem erro (SQLite suporta `->change()` nativamente no Laravel 11+).

- [ ] **Step 3: Atualizar `app/Models/User.php` — fillable, hidden e propriedades**

Trocar o atributo `#[Fillable(...)]` e `#[Hidden(...)]`:
```php
#[Fillable(['name', 'email', 'phone', 'cpf', 'google_id', 'password'])]
#[Hidden(['password', 'cpf', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
```
E no bloco de docblock de propriedades, adicionar abaixo de `* @property string $email`:
```php
 * @property string|null $phone
 * @property string|null $cpf
 * @property string|null $google_id
```
E trocar `* @property string $password` por `* @property string|null $password`.

- [ ] **Step 4: Verificar tipos (PHPStan)**

Run: `composer types:check`
Expected: sem novos erros relativos a `User`.

---

## Task 4: Regra de validação `ValidCpf`

**Files:**
- Create: `app/Rules/ValidCpf.php`
- Test: `tests/Unit/ValidCpfTest.php`

- [ ] **Step 1: Escrever o teste (falhando)**

```php
<?php

namespace Tests\Unit;

use App\Rules\ValidCpf;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ValidCpfTest extends TestCase
{
    private function passes(string $cpf): bool
    {
        return Validator::make(['cpf' => $cpf], ['cpf' => [new ValidCpf]])->passes();
    }

    public function test_accepts_valid_cpf_with_and_without_mask(): void
    {
        $this->assertTrue($this->passes('529.982.247-25'));
        $this->assertTrue($this->passes('52998224725'));
    }

    public function test_rejects_invalid_check_digits(): void
    {
        $this->assertFalse($this->passes('529.982.247-24'));
    }

    public function test_rejects_repeated_sequences(): void
    {
        $this->assertFalse($this->passes('111.111.111-11'));
    }

    public function test_rejects_wrong_length(): void
    {
        $this->assertFalse($this->passes('1234'));
    }
}
```

- [ ] **Step 2: Rodar o teste para vê-lo falhar**

Run: `php artisan test --filter=ValidCpfTest`
Expected: FAIL com "Class App\Rules\ValidCpf not found".

- [ ] **Step 3: Implementar a regra**

```php
<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidCpf implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $cpf = preg_replace('/\D/', '', (string) $value);

        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            $fail('O CPF informado não é válido.');

            return;
        }

        for ($t = 9; $t < 11; $t++) {
            $sum = 0;
            for ($i = 0; $i < $t; $i++) {
                $sum += (int) $cpf[$i] * (($t + 1) - $i);
            }
            $digit = ((10 * $sum) % 11) % 10;
            if ((int) $cpf[$t] !== $digit) {
                $fail('O CPF informado não é válido.');

                return;
            }
        }
    }
}
```

- [ ] **Step 4: Rodar o teste para vê-lo passar**

Run: `php artisan test --filter=ValidCpfTest`
Expected: PASS (4 testes).

---

## Task 5: Helpers de validação `phoneRules()` e `cpfRules()`

**Files:**
- Modify: `app/Concerns/ProfileValidationRules.php`

> `profileRules()` permanece inalterado (a tela de settings não coleta phone/cpf). Adicionamos só os helpers, compostos depois no `CreateNewUser`.

- [ ] **Step 1: Adicionar os dois métodos ao trait**

Adicionar os `use` no topo (já existe `use Illuminate\Validation\Rule;`) e o `use App\Rules\ValidCpf;`. Adicionar os métodos antes do fechamento da classe:
```php
    /**
     * Regras para telefone/WhatsApp.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function phoneRules(): array
    {
        return ['required', 'string', 'max:20'];
    }

    /**
     * Regras para CPF (valida dígitos e unicidade).
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function cpfRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            new ValidCpf,
            $userId === null
                ? Rule::unique(User::class, 'cpf')
                : Rule::unique(User::class, 'cpf')->ignore($userId),
        ];
    }
```

- [ ] **Step 2: Verificar tipos**

Run: `composer types:check`
Expected: sem erros.

---

## Task 6: `CreateNewUser` + `RegistrationTest`

**Files:**
- Modify: `app/Actions/Fortify/CreateNewUser.php`
- Modify: `tests/Feature/Auth/RegistrationTest.php`

- [ ] **Step 1: Atualizar `RegistrationTest` (teste primeiro)**

Substituir o método `test_new_users_can_register` e adicionar um de CPF inválido:
```php
    public function test_new_users_can_register()
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Helena Souza',
            'email' => 'helena@example.com',
            'phone' => '(61) 99999-8888',
            'cpf' => '529.982.247-25',
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('tickets.index', absolute: false));

        $this->assertDatabaseHas('users', [
            'email' => 'helena@example.com',
            'phone' => '61999998888',
            'cpf' => '52998224725',
        ]);
    }

    public function test_registration_requires_valid_cpf()
    {
        $response = $this->from(route('register'))->post(route('register.store'), [
            'name' => 'Helena Souza',
            'email' => 'helena2@example.com',
            'phone' => '(61) 99999-8888',
            'cpf' => '111.111.111-11',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('cpf');
        $this->assertGuest();
    }
```

- [ ] **Step 2: Rodar para ver falhar**

Run: `php artisan test --filter=RegistrationTest`
Expected: FAIL (usuário criado sem phone/cpf; redirect/DB não batem).

- [ ] **Step 3: Implementar `CreateNewUser`**

```php
<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => $this->nameRules(),
            'email' => $this->emailRules(),
            'phone' => $this->phoneRules(),
            'cpf' => $this->cpfRules(),
            'password' => ['required', 'string', Password::default()],
        ])->validate();

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'phone' => preg_replace('/\D/', '', $input['phone']),
            'cpf' => preg_replace('/\D/', '', $input['cpf']),
            'password' => $input['password'],
        ]);
    }
}
```

> Senha usa `Password::default()` local **sem** `'confirmed'` — preserva o `confirmed` do reset de senha em `passwordRules()`.

- [ ] **Step 4: Rodar para ver passar**

Run: `php artisan test --filter=RegistrationTest`
Expected: PASS (registro, CPF inválido).

- [ ] **Step 5: Rodar toda a suíte de auth**

Run: `php artisan test --filter=Auth`
Expected: tudo verde.

---

## Task 7: Google OAuth — controller, rotas e teste

**Files:**
- Create: `app/Http/Controllers/Auth/GoogleAuthController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Auth/GoogleAuthTest.php`

- [ ] **Step 1: Escrever o teste (falhando)**

```php
<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGoogleUser(string $id, string $email, string $name): void
    {
        $abstract = Mockery::mock(SocialiteUser::class);
        $abstract->shouldReceive('getId')->andReturn($id);
        $abstract->shouldReceive('getEmail')->andReturn($email);
        $abstract->shouldReceive('getName')->andReturn($name);

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($abstract);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_callback_creates_new_account(): void
    {
        $this->fakeGoogleUser('g-123', 'novo@gmail.com', 'Novo Usuário');

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('tickets.index', absolute: false));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'novo@gmail.com', 'google_id' => 'g-123']);
    }

    public function test_callback_links_existing_email_account(): void
    {
        $user = User::factory()->create(['email' => 'existente@gmail.com']);
        $this->fakeGoogleUser('g-456', 'existente@gmail.com', 'Existente');

        $this->get(route('auth.google.callback'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame('g-456', $user->fresh()->google_id);
    }

    public function test_redirect_route_responds(): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get(route('auth.google.redirect'))->assertRedirect();
    }
}
```

- [ ] **Step 2: Rodar para ver falhar**

Run: `php artisan test --filter=GoogleAuthTest`
Expected: FAIL (rota/controller inexistente).

- [ ] **Step 3: Implementar o controller**

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            return redirect()->route('login')->with('status', 'Não foi possível entrar com o Google. Tente novamente.');
        }

        $user = User::where('google_id', $googleUser->getId())->first()
            ?? User::where('email', $googleUser->getEmail())->first();

        if ($user) {
            if (! $user->google_id) {
                $user->forceFill(['google_id' => $googleUser->getId()])->save();
            }
        } else {
            $user = User::create([
                'name' => $googleUser->getName() ?: $googleUser->getEmail(),
                'email' => $googleUser->getEmail(),
                'google_id' => $googleUser->getId(),
                'email_verified_at' => now(),
            ]);
        }

        Auth::login($user, remember: true);

        return redirect()->intended(config('fortify.home'));
    }
}
```

- [ ] **Step 4: Registrar as rotas em `routes/web.php`**

Adicionar o import no topo:
```php
use App\Http\Controllers\Auth\GoogleAuthController;
```
E, após o bloco público (antes do grupo `auth`), adicionar:
```php
// Login social
Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
```

- [ ] **Step 5: Rodar para ver passar**

Run: `php artisan test --filter=GoogleAuthTest`
Expected: PASS (3 testes).

---

## Task 8: Frontend — ícone do Google

**Files:**
- Create: `resources/js/components/icons/google-icon.tsx`

- [ ] **Step 1: Criar o componente**

```tsx
import type { SVGProps } from 'react';

export default function GoogleIcon(props: SVGProps<SVGSVGElement>) {
    return (
        <svg viewBox="0 0 24 24" aria-hidden="true" {...props}>
            <path
                fill="#4285F4"
                d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.27-4.74 3.27-8.1Z"
            />
            <path
                fill="#34A853"
                d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.99.66-2.26 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84A11 11 0 0 0 12 23Z"
            />
            <path
                fill="#FBBC05"
                d="M5.84 14.1a6.6 6.6 0 0 1 0-4.2V7.06H2.18a11 11 0 0 0 0 9.88l3.66-2.84Z"
            />
            <path
                fill="#EA4335"
                d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06L5.84 9.9C6.71 7.31 9.14 5.38 12 5.38Z"
            />
        </svg>
    );
}
```

- [ ] **Step 2: Verificar tipos**

Run: `npm run types:check`
Expected: sem erros.

---

## Task 9: Frontend — máscaras + MaskedInput

**Files:**
- Create: `resources/js/lib/masks.ts`
- Create: `resources/js/components/masked-input.tsx`

- [ ] **Step 1: Criar `masks.ts`**

```ts
export function maskPhone(value: string): string {
    const d = value.replace(/\D/g, '').slice(0, 11);
    if (d.length === 0) return '';
    if (d.length <= 2) return `(${d}`;
    if (d.length <= 6) return `(${d.slice(0, 2)}) ${d.slice(2)}`;
    if (d.length <= 10)
        return `(${d.slice(0, 2)}) ${d.slice(2, 6)}-${d.slice(6)}`;
    return `(${d.slice(0, 2)}) ${d.slice(2, 7)}-${d.slice(7)}`;
}

export function maskCpf(value: string): string {
    return value
        .replace(/\D/g, '')
        .slice(0, 11)
        .replace(/(\d{3})(\d)/, '$1.$2')
        .replace(/(\d{3})(\d)/, '$1.$2')
        .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
}
```

- [ ] **Step 2: Criar `masked-input.tsx`**

```tsx
import type { ComponentProps } from 'react';
import { Input } from '@/components/ui/input';

type Props = Omit<ComponentProps<typeof Input>, 'onInput'> & {
    mask: (value: string) => string;
};

export default function MaskedInput({ mask, ...props }: Props) {
    return (
        <Input
            inputMode="numeric"
            {...props}
            onInput={(e) => {
                e.currentTarget.value = mask(e.currentTarget.value);
            }}
        />
    );
}
```

- [ ] **Step 3: Verificar tipos**

Run: `npm run types:check`
Expected: sem erros.

---

## Task 10: Frontend — bloco social reutilizável

**Files:**
- Create: `resources/js/components/auth/social-auth.tsx`

- [ ] **Step 1: Criar o componente**

```tsx
import GoogleIcon from '@/components/icons/google-icon';

export default function SocialAuth({ label }: { label: string }) {
    return (
        <div className="flex flex-col gap-6">
            <a
                href="/auth/google/redirect"
                className="inline-flex h-10 w-full items-center justify-center gap-2 rounded-btn bg-foreground text-sm font-medium text-background transition-opacity hover:opacity-90"
            >
                <GoogleIcon className="size-4" />
                {label}
            </a>
            <div className="flex items-center gap-3">
                <span className="h-px flex-1 bg-border" />
                <span className="text-xs text-muted-foreground">ou</span>
                <span className="h-px flex-1 bg-border" />
            </div>
        </div>
    );
}
```

- [ ] **Step 2: Verificar tipos**

Run: `npm run types:check`
Expected: sem erros.

---

## Task 11: Frontend — layout card Veludo

**Files:**
- Modify: `resources/js/layouts/auth/auth-card-layout.tsx`
- Modify: `resources/js/layouts/auth-layout.tsx`

- [ ] **Step 1: Reescrever `auth-card-layout.tsx`**

```tsx
import { Link } from '@inertiajs/react';
import { Drama } from 'lucide-react';
import type { PropsWithChildren } from 'react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { home } from '@/routes';

export default function AuthCardLayout({
    children,
    title,
    description,
}: PropsWithChildren<{
    name?: string;
    title?: string;
    description?: string;
}>) {
    return (
        <div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
            <div className="flex w-full max-w-md flex-col gap-6">
                <Link
                    href={home()}
                    className="flex flex-col items-center gap-2 self-center"
                >
                    <div className="flex size-11 items-center justify-center rounded-btn bg-primary">
                        <Drama className="size-6 text-primary-foreground" />
                    </div>
                    <span className="font-display text-sm uppercase tracking-[0.22em] text-muted-foreground">
                        Veludo
                    </span>
                </Link>

                <Card className="rounded-card border-border bg-card">
                    <CardHeader className="px-8 pt-8 pb-0 text-center">
                        <CardTitle className="font-display text-2xl">
                            {title}
                        </CardTitle>
                        <CardDescription className="text-muted-foreground">
                            {description}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="px-8 py-8">{children}</CardContent>
                </Card>
            </div>
        </div>
    );
}
```

- [ ] **Step 2: Trocar o template em `auth-layout.tsx`**

```tsx
import AuthLayoutTemplate from '@/layouts/auth/auth-card-layout';

export default function AuthLayout({
    title = '',
    description = '',
    children,
}: {
    title?: string;
    description?: string;
    children: React.ReactNode;
}) {
    return (
        <AuthLayoutTemplate title={title} description={description}>
            {children}
        </AuthLayoutTemplate>
    );
}
```

- [ ] **Step 3: Verificar tipos**

Run: `npm run types:check`
Expected: sem erros.

---

## Task 12: Frontend — tela de login (PT + Google)

**Files:**
- Modify: `resources/js/pages/auth/login.tsx`

- [ ] **Step 1: Reescrever `login.tsx` (preservando marcadores chisel e PasskeyVerify)**

```tsx
import { Form, Head } from '@inertiajs/react';
import SocialAuth from '@/components/auth/social-auth';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
/* @chisel-registration */
import { register } from '@/routes';
/* @end-chisel-registration */
import { store } from '@/routes/login';
import { request } from '@/routes/password';
/* @chisel-passkeys */
import PasskeyVerify from '@/components/passkey-verify';
/* @end-chisel-passkeys */

type Props = {
    status?: string;
    canResetPassword: boolean;
};

export default function Login({ status, canResetPassword }: Props) {
    return (
        <>
            <Head title="Entrar" />

            {/* @chisel-passkeys */}
            <PasskeyVerify />
            {/* @end-chisel-passkeys */}

            <div className="flex flex-col gap-6">
                <SocialAuth label="Continuar com Google" />

                <Form
                    {...store.form()}
                    resetOnSuccess={['password']}
                    className="flex flex-col gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-6">
                                <div className="grid gap-2">
                                    <Label htmlFor="email">E-mail</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        name="email"
                                        required
                                        autoFocus
                                        tabIndex={1}
                                        autoComplete="email"
                                        placeholder="voce@email.com"
                                    />
                                    <InputError message={errors.email} />
                                </div>

                                <div className="grid gap-2">
                                    <div className="flex items-center">
                                        <Label htmlFor="password">Senha</Label>
                                        {canResetPassword && (
                                            <TextLink
                                                href={request()}
                                                className="ml-auto text-sm"
                                                tabIndex={5}
                                            >
                                                Esqueci a senha?
                                            </TextLink>
                                        )}
                                    </div>
                                    <PasswordInput
                                        id="password"
                                        name="password"
                                        required
                                        tabIndex={2}
                                        autoComplete="current-password"
                                        placeholder="Sua senha"
                                    />
                                    <InputError message={errors.password} />
                                </div>

                                <div className="flex items-center space-x-3">
                                    <Checkbox
                                        id="remember"
                                        name="remember"
                                        tabIndex={3}
                                    />
                                    <Label htmlFor="remember">
                                        Lembrar de mim
                                    </Label>
                                </div>

                                <Button
                                    type="submit"
                                    className="mt-2 w-full"
                                    tabIndex={4}
                                    disabled={processing}
                                    data-test="login-button"
                                >
                                    {processing && <Spinner />}
                                    Entrar
                                </Button>
                            </div>

                            {/* @chisel-registration */}
                            <div className="text-center text-sm text-muted-foreground">
                                Não tem conta?{' '}
                                <TextLink href={register()} tabIndex={5}>
                                    Criar conta
                                </TextLink>
                            </div>
                            {/* @end-chisel-registration */}
                        </>
                    )}
                </Form>
            </div>

            {status && (
                <div className="mt-4 text-center text-sm font-medium text-success">
                    {status}
                </div>
            )}
        </>
    );
}

Login.layout = {
    title: 'Bem-vindo de volta',
    description: 'Entre para garantir seu lugar',
};
```

- [ ] **Step 2: Verificar tipos**

Run: `npm run types:check`
Expected: sem erros.

---

## Task 13: Frontend — tela de cadastro (PT + campos + Google)

**Files:**
- Modify: `resources/js/pages/auth/register.tsx`

- [ ] **Step 1: Reescrever `register.tsx`**

```tsx
import { Form, Head } from '@inertiajs/react';
import SocialAuth from '@/components/auth/social-auth';
import InputError from '@/components/input-error';
import MaskedInput from '@/components/masked-input';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { maskCpf, maskPhone } from '@/lib/masks';
import { login } from '@/routes';
import { store } from '@/routes/register';

type Props = {
    passwordRules: string;
};

export default function Register({ passwordRules }: Props) {
    return (
        <>
            <Head title="Criar conta" />

            <div className="flex flex-col gap-6">
                <SocialAuth label="Continuar com Google" />

                <Form
                    {...store.form()}
                    resetOnSuccess={['password']}
                    disableWhileProcessing
                    className="flex flex-col gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-6">
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Nome completo</Label>
                                    <Input
                                        id="name"
                                        type="text"
                                        name="name"
                                        required
                                        autoFocus
                                        tabIndex={1}
                                        autoComplete="name"
                                        placeholder="Seu nome"
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="email">E-mail</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        name="email"
                                        required
                                        tabIndex={2}
                                        autoComplete="email"
                                        placeholder="voce@email.com"
                                    />
                                    <InputError message={errors.email} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="phone">
                                        Telefone / WhatsApp
                                    </Label>
                                    <MaskedInput
                                        id="phone"
                                        name="phone"
                                        type="tel"
                                        required
                                        tabIndex={3}
                                        autoComplete="tel"
                                        mask={maskPhone}
                                        placeholder="(61) 99999-9999"
                                    />
                                    <InputError message={errors.phone} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="cpf">CPF</Label>
                                    <MaskedInput
                                        id="cpf"
                                        name="cpf"
                                        required
                                        tabIndex={4}
                                        mask={maskCpf}
                                        placeholder="000.000.000-00"
                                    />
                                    <InputError message={errors.cpf} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="password">Senha</Label>
                                    <PasswordInput
                                        id="password"
                                        name="password"
                                        required
                                        tabIndex={5}
                                        autoComplete="new-password"
                                        placeholder="Crie uma senha"
                                        passwordrules={passwordRules}
                                    />
                                    <InputError message={errors.password} />
                                </div>

                                <Button
                                    type="submit"
                                    className="mt-2 w-full"
                                    tabIndex={6}
                                    data-test="register-user-button"
                                >
                                    {processing && <Spinner />}
                                    Criar conta
                                </Button>
                            </div>

                            <div className="text-center text-sm text-muted-foreground">
                                Já tem conta?{' '}
                                <TextLink href={login()} tabIndex={7}>
                                    Entrar
                                </TextLink>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

Register.layout = {
    title: 'Criar sua conta',
    description: 'Seus dados para emissão e contato',
};
```

- [ ] **Step 2: Verificar tipos**

Run: `npm run types:check`
Expected: sem erros.

---

## Task 14: Frontend — traduzir telas de auth herdadas

**Files:**
- Modify: `resources/js/pages/auth/forgot-password.tsx`
- Modify: `resources/js/pages/auth/reset-password.tsx`
- Modify: `resources/js/pages/auth/verify-email.tsx`
- Modify: `resources/js/pages/auth/confirm-password.tsx`
- Modify: `resources/js/pages/auth/two-factor-challenge.tsx`

- [ ] **Step 1: Traduzir textos visíveis e títulos de layout**

Em cada arquivo, traduzir labels, placeholders, textos de botão, `Head title`, e o objeto `*.layout` (`title`/`description`). Guia de termos:
- "Forgot password" → "Esqueci a senha"; "Email password reset link" → "Enviar link de redefinição".
- "Reset password" → "Redefinir senha"; "Password"/"Confirm password" → "Senha"/"Confirmar senha".
- "Verify email" → "Verifique seu e-mail"; "Resend verification email" → "Reenviar e-mail de verificação"; "Log out" → "Sair".
- "Confirm password" → "Confirmar senha"; "This is a secure area..." → "Esta é uma área segura. Confirme sua senha para continuar.".
- "Authentication code"/"Recovery code" → "Código de autenticação"/"Código de recuperação"; "Continue" → "Continuar".

Não alterar nomes de `name=` dos inputs nem lógica — só strings visíveis.

- [ ] **Step 2: Verificar tipos**

Run: `npm run types:check`
Expected: sem erros.

---

## Task 15: Verificação final

**Files:** nenhum (só checagens)

- [ ] **Step 1: Suíte completa de testes**

Run: `php artisan test`
Expected: tudo verde (incluindo `ValidCpfTest`, `RegistrationTest`, `GoogleAuthTest`).

- [ ] **Step 2: Tipos (PHP + TS)**

Run: `composer types:check && npm run types:check`
Expected: sem erros.

- [ ] **Step 3: Build de produção**

Run: `npm run build`
Expected: build conclui sem erro.

- [ ] **Step 4: Checagem manual (servidor dev)**

Run: `composer dev` (ou `php artisan serve & npm run dev`) e abrir:
- `/login` — card Veludo, botão Google, PT-BR, mostrar/ocultar senha.
- `/register` — campos nome/e-mail/telefone(máscara)/CPF(máscara)/senha; sem confirmar senha.
- Clicar "Continuar com Google" → redireciona ao Google (após `.env` com credenciais reais).

> Sem credenciais Google no `.env`, o clique resulta em erro do provedor — esperado até você colar `GOOGLE_CLIENT_ID`/`GOOGLE_CLIENT_SECRET`.

---

## Self-review (preenchido)

- **Cobertura do spec:** layout card (T11), social reutilizável (T8–T10), login PT (T12), cadastro PT + phone/cpf (T9,T13), telas herdadas (T14), Socialite/config/env (T2), migration + model (T3), ValidCpf (T4), regras (T5), CreateNewUser sem `confirmed` (T6), Google controller/rotas (T7), testes (T4,T6,T7,T15), redirects pré-existentes (T1). ✔
- **Placeholders:** nenhum — todo passo tem código/comando concreto. ✔
- **Consistência de tipos/nomes:** `maskPhone`/`maskCpf` (masks.ts ↔ register), `ValidCpf` (rule ↔ concern ↔ teste), `phoneRules`/`cpfRules` (concern ↔ CreateNewUser), rotas `auth.google.redirect|callback` (web.php ↔ teste ↔ social-auth usa URL literal `/auth/google/redirect`). ✔
- **Limitação documentada:** conta criada via Google fica sem phone/cpf (coleta futura). ✔

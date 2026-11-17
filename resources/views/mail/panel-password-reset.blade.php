<x-mail.layout title="{{ $invite ? 'Seu acesso ao painel' : 'Redefinir senha' }}">
<h1 style="font-family:'Oswald','Arial Narrow','Helvetica Neue',Arial,sans-serif;color:#2A1F1A;text-transform:uppercase;font-size:22px;margin:0 0 12px;">{{ $invite ? 'Bem-vindo ao Kena' : 'Redefinir senha' }}</h1>

@if ($invite)
<p style="color:#6B5F57;font-size:14px;line-height:1.6;margin:0 0 16px;">Olá, {{ $name }}! Criamos o acesso de <strong style="color:#2A1F1A;">{{ $organizationName }}</strong> ao painel do Kena. Crie sua senha para entrar, conectar o Mercado Pago e publicar seus eventos.</p>
@else
<p style="color:#6B5F57;font-size:14px;line-height:1.6;margin:0 0 16px;">Olá, {{ $name }}! Recebemos um pedido para redefinir a senha do seu acesso ao painel do Kena.</p>
@endif

<x-mail.button :url="$url">{{ $invite ? 'Criar minha senha' : 'Criar nova senha' }}</x-mail.button>

<p style="color:#6B5F57;font-size:12px;line-height:1.6;margin:16px 0 0;">
@if ($invite)
O link vale por {{ intdiv($ttlMinutes, 1440) }} dias. Se expirar, use "Esqueci minha senha" no login do painel.
@else
O link vale por {{ $ttlMinutes }} minutos. Se não foi você que pediu, ignore este e-mail: sua senha continua a mesma.
@endif
</p>
</x-mail.layout>

<x-mail.layout title="Redefinir senha">
<h1 style="font-family:'Oswald','Arial Narrow','Helvetica Neue',Arial,sans-serif;color:#2A1F1A;text-transform:uppercase;font-size:22px;margin:0 0 12px;">Redefinir senha</h1>

<p style="color:#6B5F57;font-size:14px;line-height:1.6;margin:0 0 16px;">Olá, {{ $name }}! Recebemos um pedido para redefinir a senha do seu acesso ao painel do Kena.</p>

<x-mail.button :url="$url">Criar nova senha</x-mail.button>

<p style="color:#6B5F57;font-size:12px;line-height:1.6;margin:16px 0 0;">O link vale por {{ $ttlMinutes }} minutos. Se não foi você que pediu, ignore este e-mail: sua senha continua a mesma.</p>
</x-mail.layout>

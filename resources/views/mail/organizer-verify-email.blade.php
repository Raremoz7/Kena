<x-mail.layout title="Confirme seu e-mail">
<h1 style="font-family:'Oswald','Arial Narrow','Helvetica Neue',Arial,sans-serif;color:#2A1F1A;text-transform:uppercase;font-size:22px;margin:0 0 12px;">Confirme seu e-mail</h1>

<p style="color:#6B5F57;font-size:14px;line-height:1.6;margin:0 0 16px;">Olá, {{ $name }}! Falta só confirmar seu e-mail para entrar no painel do Kena e começar a vender ingressos.</p>

<x-mail.button :url="$url">Confirmar e entrar no painel</x-mail.button>

<p style="color:#6B5F57;font-size:12px;line-height:1.6;margin:16px 0 0;">O link vale por {{ $ttlHours }} horas. Se não foi você que criou a conta, ignore este e-mail.</p>
</x-mail.layout>

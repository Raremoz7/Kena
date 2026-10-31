<x-mail.layout title="Seu código Kena">
<h1 style="font-family:'Oswald','Arial Narrow','Helvetica Neue',Arial,sans-serif;color:#2A1F1A;text-transform:uppercase;font-size:22px;margin:0 0 12px;">Confirme seu e-mail</h1>

<p style="color:#6B5F57;font-size:14px;line-height:1.6;margin:0 0 16px;">Digite este código no Kena para continuar:</p>

<p style="font-family:'Oswald','Arial Narrow','Helvetica Neue',Arial,sans-serif;color:#2A1F1A;font-size:34px;letter-spacing:10px;font-weight:600;margin:0 0 16px;">{{ $code }}</p>

<p style="color:#6B5F57;font-size:12px;line-height:1.6;margin:0;">O código vale por {{ $ttlMinutes }} minutos. Se não foi você que pediu, ignore este e-mail — sem o código ninguém usa o seu endereço no Kena.</p>
</x-mail.layout>

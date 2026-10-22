<x-mail.layout title="Continue sua compra">
<h1 style="font-family:'Oswald',Georgia,serif;color:#F3EEE6;text-transform:uppercase;font-size:22px;margin:0 0 12px;">Continue sua compra 🎭</h1>

<p style="color:#A59D95;font-size:14px;line-height:1.6;margin:0 0 4px;">Olá {{ $user->name }}, este e-mail já tem uma conta Kena. Para proteger seus ingressos, a compra continua por este link.</p>
<p style="color:#A59D95;font-size:13px;margin:8px 0 0;"><strong style="color:#F3EEE6;">{{ $event->title }}</strong></p>
<p style="color:#A59D95;font-size:13px;margin:0;">{{ $sessionLabel }} · {{ $venue->name }}, {{ $venue->city }}</p>

<x-mail.button :url="$accessUrl">Continuar a compra</x-mail.button>

<p style="color:#A59D95;font-size:12px;line-height:1.6;margin:16px 0 0;">O link vale por {{ $ttlMinutes }} minutos e funciona uma vez. Se não foi você, ignore este e-mail — ninguém entra na sua conta sem ele.</p>
</x-mail.layout>

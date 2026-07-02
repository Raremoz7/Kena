<x-mail.layout title="É amanhã">
<h1 style="font-family:'Oswald',Georgia,serif;color:#F3EEE6;text-transform:uppercase;font-size:22px;margin:0 0 12px;">É amanhã 🎭</h1>

<p style="color:#A59D95;font-size:14px;line-height:1.6;margin:0;">Olá {{ $name }}, é amanhã o <strong style="color:#F3EEE6;">{{ $event->title }}</strong>.</p>

<x-mail.info-panel>
<div style="color:#F3EEE6;font-size:14px;font-weight:600;">{{ $sessionLabel }}</div>
<div style="color:#A59D95;font-size:13px;margin-top:4px;">{{ $venue->name }} — {{ $venue->city }}/{{ $venue->state }}</div>
@if ($venue->address)
<div style="color:#A59D95;font-size:13px;margin-top:2px;">{{ $venue->address }}</div>
@endif
</x-mail.info-panel>

<p style="color:#A59D95;font-size:13px;line-height:1.6;margin:0;">Leve o QR do seu ingresso (na tela ou impresso). Chegue com antecedência.</p>

<x-mail.button :url="$ticketsUrl">Ver meus ingressos</x-mail.button>

<p style="color:#A59D95;font-size:13px;margin:16px 0 0;">Bom espetáculo!</p>
</x-mail.layout>

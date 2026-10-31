<x-mail.layout title="É amanhã">
<h1 style="font-family:'Oswald','Arial Narrow','Helvetica Neue',Arial,sans-serif;color:#2A1F1A;text-transform:uppercase;font-size:22px;margin:0 0 12px;">É amanhã 🎭</h1>

<p style="color:#6B5F57;font-size:14px;line-height:1.6;margin:0;">Olá {{ $name }}, é amanhã o <strong style="color:#2A1F1A;">{{ $event->title }}</strong>.</p>

<x-mail.info-panel>
<div style="color:#2A1F1A;font-size:14px;font-weight:600;">{{ $sessionLabel }}</div>
<div style="color:#6B5F57;font-size:13px;margin-top:4px;">{{ $venue->name }} — {{ $venue->city }}/{{ $venue->state }}</div>
@if ($venue->address)
<div style="color:#6B5F57;font-size:13px;margin-top:2px;">{{ $venue->address }}</div>
@endif
</x-mail.info-panel>

<p style="color:#6B5F57;font-size:13px;line-height:1.6;margin:0;">Leve o QR do seu ingresso (na tela ou impresso). Chegue com antecedência.</p>

<x-mail.button :url="$ticketsUrl">Ver meus ingressos</x-mail.button>

<p style="color:#6B5F57;font-size:13px;margin:16px 0 0;">Bom espetáculo!</p>
</x-mail.layout>

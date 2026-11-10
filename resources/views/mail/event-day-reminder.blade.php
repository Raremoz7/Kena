<x-mail.layout title="É hoje">
<h1 style="font-family:'Oswald','Arial Narrow','Helvetica Neue',Arial,sans-serif;color:#2A1F1A;text-transform:uppercase;font-size:22px;margin:0 0 12px;">É hoje 🎭</h1>

<p style="color:#6B5F57;font-size:14px;line-height:1.6;margin:0;">Olá {{ $name }}, chegou o dia do <strong style="color:#2A1F1A;">{{ $event->title }}</strong>. Estamos te esperando!</p>

<x-mail.info-panel>
<div style="color:#2A1F1A;font-size:14px;font-weight:600;">{{ $sessionLabel }}</div>
@if ($doorsAt)
<div style="color:#6B5F57;font-size:13px;margin-top:4px;">Abertura dos portões: {{ $doorsAt }}</div>
@endif
@if ($venue)
<div style="color:#6B5F57;font-size:13px;margin-top:4px;">{{ $venue->name }} — {{ $venue->city }}/{{ $venue->state }}</div>
@if ($venue->address)
<div style="color:#6B5F57;font-size:13px;margin-top:2px;">{{ $venue->address }}</div>
@endif
@endif
</x-mail.info-panel>

<p style="color:#6B5F57;font-size:13px;line-height:1.6;margin:0;">Na entrada, é só mostrar o QR do seu ingresso (na tela do celular ou impresso). Chegue com antecedência para entrar com calma.</p>

<x-mail.button :url="$ticketsUrl">Ver meus ingressos</x-mail.button>

<p style="color:#6B5F57;font-size:13px;margin:16px 0 0;">Bom espetáculo!</p>
</x-mail.layout>

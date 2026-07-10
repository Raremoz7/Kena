<x-mail.layout title="Pedido cancelado">
<h1 style="font-family:'Oswald',Georgia,serif;color:#F3EEE6;text-transform:uppercase;font-size:22px;margin:0 0 12px;">Pedido cancelado</h1>

<p style="color:#A59D95;font-size:14px;line-height:1.6;margin:0;">Olá {{ $order->user->name }}, o pedido <strong style="color:#F3EEE6;">{{ $order->reference }}</strong> foi cancelado e nenhuma cobrança foi efetivada.</p>
<p style="color:#A59D95;font-size:13px;margin:8px 0 0;"><strong style="color:#F3EEE6;">{{ $event->title }}</strong></p>
<p style="color:#A59D95;font-size:13px;margin:0;">{{ $sessionLabel }}</p>

<x-mail.info-panel>
<div style="color:#F3EEE6;font-size:14px;font-weight:600;">Motivo</div>
<div style="color:#A59D95;font-size:13px;margin-top:6px;">{{ $reason }}</div>
</x-mail.info-panel>

<x-mail.button :url="$eventsUrl">Ver eventos em cartaz</x-mail.button>
</x-mail.layout>

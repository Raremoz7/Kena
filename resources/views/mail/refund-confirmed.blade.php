<x-mail.layout title="Reembolso confirmado">
<h1 style="font-family:'Oswald',Georgia,serif;color:#F3EEE6;text-transform:uppercase;font-size:22px;margin:0 0 12px;">Reembolso confirmado</h1>

<p style="color:#A59D95;font-size:14px;line-height:1.6;margin:0;">Olá {{ $order->user->name }}, seu reembolso do pedido <strong style="color:#F3EEE6;">{{ $order->reference }}</strong> foi processado.</p>
<p style="color:#A59D95;font-size:13px;margin:8px 0 0;"><strong style="color:#F3EEE6;">{{ $event->title }}</strong></p>
<p style="color:#A59D95;font-size:13px;margin:0;">{{ $sessionLabel }}</p>

<x-mail.info-panel>
<div style="color:#F3EEE6;font-size:14px;font-weight:600;">Valor reembolsado: R$ {{ number_format($amount, 2, ',', '.') }}</div>
<div style="color:#A59D95;font-size:12px;margin-top:6px;">O estorno aparece no seu meio de pagamento conforme o prazo do Mercado Pago.</div>
</x-mail.info-panel>

<p style="color:#A59D95;font-size:13px;margin:0;">Seus ingressos desse pedido foram cancelados.</p>
</x-mail.layout>

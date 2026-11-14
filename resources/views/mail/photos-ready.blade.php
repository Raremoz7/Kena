<x-mail.layout title="Suas fotos">
<h1 style="font-family:'Oswald','Arial Narrow','Helvetica Neue',Arial,sans-serif;color:#2A1F1A;text-transform:uppercase;font-size:22px;margin:0 0 12px;">Fotos liberadas</h1>

<p style="color:#6B5F57;font-size:14px;line-height:1.6;margin:0 0 4px;">Olá {{ $order->user->name }}, o pagamento foi aprovado e suas fotos de <strong style="color:#2A1F1A;">{{ $event?->title }}</strong> já estão prontas para baixar, em alta e sem marca d'água.</p>

@if (count($thumbs) > 0)
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:16px 0 0;">
<tr>
@foreach ($thumbs as $thumb)
<td style="padding:0 6px 0 0;"><img src="{{ $thumb }}" width="84" height="84" alt="" style="display:block;width:84px;height:84px;object-fit:cover;border-radius:6px;border:1px solid #E2D8CC;"></td>
@endforeach
</tr>
</table>
@endif

<x-mail.info-panel>
<div style="color:#2A1F1A;font-size:14px;font-weight:600;">Pedido {{ $order->reference }}</div>
<div style="color:#6B5F57;font-size:13px;margin-top:6px;">{{ $countLabel }} · R$ {{ number_format($amount, 2, ',', '.') }}</div>
</x-mail.info-panel>

<p style="color:#6B5F57;font-size:13px;line-height:1.6;margin:0;">Em Minhas fotos você baixa cada foto ou todas de uma vez, num arquivo .zip. Elas ficam guardadas na sua conta.</p>

<x-mail.button :url="$photosUrl">Baixar minhas fotos</x-mail.button>
</x-mail.layout>

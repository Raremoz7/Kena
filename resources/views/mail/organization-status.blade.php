<x-mail.layout title="{{ $suspended ? 'Vendas suspensas' : 'Vendas reativadas' }}">
<h1 style="font-family:'Oswald','Arial Narrow','Helvetica Neue',Arial,sans-serif;color:#2A1F1A;text-transform:uppercase;font-size:22px;margin:0 0 12px;">{{ $suspended ? 'Vendas suspensas' : 'Vendas reativadas' }}</h1>

@if ($suspended)
<p style="color:#6B5F57;font-size:14px;line-height:1.6;margin:0 0 12px;">As vendas de <strong style="color:#2A1F1A;">{{ $organizationName }}</strong> no Kena foram suspensas. Seus eventos saíram da vitrine e não recebem novas compras.</p>
@if ($reason)
<p style="color:#6B5F57;font-size:14px;line-height:1.6;margin:0 0 12px;">Motivo: {{ $reason }}</p>
@endif
<p style="color:#6B5F57;font-size:14px;line-height:1.6;margin:0 0 16px;">O painel continua aberto para você ver os pedidos e fazer estornos.@if ($supportEmail) Para falar com a gente, escreva para {{ $supportEmail }}.@endif</p>
@else
<p style="color:#6B5F57;font-size:14px;line-height:1.6;margin:0 0 16px;">As vendas de <strong style="color:#2A1F1A;">{{ $organizationName }}</strong> no Kena foram reativadas. Seus eventos publicados voltaram para a vitrine.</p>
@endif

<x-mail.button :url="$panelUrl">Abrir o painel</x-mail.button>
</x-mail.layout>

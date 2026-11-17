<x-mail.layout title="Reconecte o Mercado Pago">
<h1 style="font-family:'Oswald','Arial Narrow','Helvetica Neue',Arial,sans-serif;color:#2A1F1A;text-transform:uppercase;font-size:22px;margin:0 0 12px;">Reconecte o Mercado Pago</h1>

<p style="color:#6B5F57;font-size:14px;line-height:1.6;margin:0 0 16px;">Não conseguimos renovar a conexão de <strong style="color:#2A1F1A;">{{ $organizationName }}</strong> com o Mercado Pago.@if ($expiresOn) A conexão atual vale até {{ $expiresOn }}; depois disso, as vendas param.@endif Conecte a conta de novo nas configurações do painel. Leva menos de um minuto.</p>

<x-mail.button :url="$settingsUrl">Reconectar o Mercado Pago</x-mail.button>
</x-mail.layout>

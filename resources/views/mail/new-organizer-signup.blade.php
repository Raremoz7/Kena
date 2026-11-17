<x-mail.layout title="Novo organizador">
<h1 style="font-family:'Oswald','Arial Narrow','Helvetica Neue',Arial,sans-serif;color:#2A1F1A;text-transform:uppercase;font-size:22px;margin:0 0 12px;">Novo organizador</h1>

<p style="color:#6B5F57;font-size:14px;line-height:1.6;margin:0 0 16px;"><strong style="color:#2A1F1A;">{{ $organizationName }}</strong> criou uma conta no Kena. Responsável: {{ $ownerName }} ({{ $ownerEmail }}). Para vender, ainda precisa confirmar o e-mail e conectar o Mercado Pago.</p>

<x-mail.button :url="$adminUrl">Ver organizações</x-mail.button>
</x-mail.layout>

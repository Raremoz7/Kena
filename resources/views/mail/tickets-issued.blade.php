<x-mail.layout title="Seus ingressos">
<h1 style="font-family:'Oswald','Arial Narrow','Helvetica Neue',Arial,sans-serif;color:#2A1F1A;text-transform:uppercase;font-size:22px;margin:0 0 12px;">Pagamento aprovado 🎭</h1>

<p style="color:#6B5F57;font-size:14px;line-height:1.6;margin:0 0 4px;">Olá {{ $order->user->name }}, seus ingressos para <strong style="color:#2A1F1A;">{{ $event->title }}</strong> foram emitidos.</p>
<p style="color:#6B5F57;font-size:13px;margin:8px 0 0;"><strong style="color:#2A1F1A;">{{ $sessionLabel }}</strong></p>
<p style="color:#6B5F57;font-size:13px;margin:0;">{{ $venue->name }} — {{ $venue->city }}/{{ $venue->state }}</p>

@foreach ($order->tickets as $ticket)
<x-mail.ticket-image
    :ticket="$ticket"
    :src="$message->embedData(\App\Support\TicketImage::png($ticket), 'ingresso-'.$ticket->code.'.png', 'image/png')"
/>
@endforeach

<p style="color:#6B5F57;font-size:13px;line-height:1.6;margin:16px 0 0;">Apresente o QR na entrada — ele é reemitido se você transferir o ingresso. Bom espetáculo!</p>

<x-mail.button :url="$ticketsUrl">Ver meus ingressos</x-mail.button>
</x-mail.layout>

<x-mail.layout title="Seus ingressos">
<h1 style="font-family:'Oswald',Georgia,serif;color:#F3EEE6;text-transform:uppercase;font-size:22px;margin:0 0 12px;">Pagamento aprovado 🎭</h1>

<p style="color:#A59D95;font-size:14px;line-height:1.6;margin:0 0 4px;">Olá {{ $order->user->name }}, seus ingressos para <strong style="color:#F3EEE6;">{{ $event->title }}</strong> foram emitidos.</p>
<p style="color:#A59D95;font-size:13px;margin:8px 0 0;"><strong style="color:#F3EEE6;">{{ $sessionLabel }}</strong></p>
<p style="color:#A59D95;font-size:13px;margin:0;">{{ $venue->name }} — {{ $venue->city }}/{{ $venue->state }}</p>

@foreach ($order->tickets as $ticket)
<x-mail.ticket-stub
    :sector-name="$ticket->sector_name"
    :seat-label="$ticket->seat_code"
    :holder-name="$ticket->holder_name"
    :code="$ticket->code"
    :qr-src="$message->embedData(\App\Support\QrImage::png($ticket->qr_token), 'qr-'.$ticket->code.'.png', 'image/png')"
/>
@endforeach

<p style="color:#A59D95;font-size:13px;line-height:1.6;margin:16px 0 0;">Apresente o QR na entrada — ele é reemitido se você transferir o ingresso. Bom espetáculo!</p>

<x-mail.button :url="$ticketsUrl">Ver meus ingressos</x-mail.button>
</x-mail.layout>

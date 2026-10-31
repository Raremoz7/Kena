<x-mail.layout title="Você recebeu um ingresso">
<h1 style="font-family:'Oswald','Arial Narrow','Helvetica Neue',Arial,sans-serif;color:#2A1F1A;text-transform:uppercase;font-size:22px;margin:0 0 12px;">Você recebeu um ingresso 🎟️</h1>

<p style="color:#6B5F57;font-size:14px;line-height:1.6;margin:0 0 4px;"><strong style="color:#2A1F1A;">{{ $fromName }}</strong> transferiu um ingresso para você.</p>
<p style="color:#6B5F57;font-size:13px;margin:8px 0 0;"><strong style="color:#2A1F1A;">{{ $event->title }}</strong></p>
<p style="color:#6B5F57;font-size:13px;margin:0;">{{ $sessionLabel }} · {{ $venue->name }}, {{ $venue->city }}</p>

<x-mail.ticket-image
    :ticket="$ticket"
    :src="$message->embedData(\App\Support\TicketImage::png($ticket), 'ingresso-'.$ticket->code.'.png', 'image/png')"
/>

<p style="color:#6B5F57;font-size:13px;line-height:1.6;margin:16px 0 0;">O ingresso já está na sua conta Kena. Apresente o QR na entrada.</p>

<x-mail.button :url="$ticketsUrl">Ver meus ingressos</x-mail.button>
</x-mail.layout>

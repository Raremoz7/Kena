@props(['ticket', 'src'])
{{-- Ingresso completo em PNG (App\Support\TicketImage); a linha de texto cobre quem bloqueia imagens. --}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:20px 0 4px;">
<tr>
<td align="center">
<img src="{{ $src }}" width="320" alt="Ingresso {{ $ticket->code }} — {{ $ticket->sector_name }}, lugar {{ $ticket->seat_code }}, titular {{ $ticket->holder_name }}" style="display:block;width:320px;max-width:100%;height:auto;border:0;margin:0 auto;">
<div style="color:#8A7D73;font-size:11px;margin-top:8px;">{{ $ticket->sector_name }} · Lugar {{ $ticket->seat_code }} · {{ $ticket->holder_name }} · <span style="font-family:monospace;">{{ $ticket->code }}</span></div>
</td>
</tr>
</table>

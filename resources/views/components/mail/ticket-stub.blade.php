@props(['sectorName', 'seatLabel', 'holderName', 'code', 'qrSrc'])
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#1D1511;border-radius:10px;margin:16px 0;">
<tr>
<td style="padding:16px;border-right:1px dashed #382E29;vertical-align:top;">
<div style="color:#6E6762;font-size:10px;text-transform:uppercase;letter-spacing:1px;">Setor</div>
<div style="color:#F3EEE6;font-size:14px;font-weight:600;margin-top:2px;">{{ $sectorName }}</div>
<div style="color:#6E6762;font-size:10px;text-transform:uppercase;letter-spacing:1px;margin-top:10px;">Lugar</div>
<div style="color:#F3EEE6;font-size:14px;font-weight:600;margin-top:2px;">{{ $seatLabel }}</div>
<div style="color:#6E6762;font-size:10px;text-transform:uppercase;letter-spacing:1px;margin-top:10px;">Titular</div>
<div style="color:#F3EEE6;font-size:13px;margin-top:2px;">{{ $holderName }}</div>
</td>
<td width="96" style="padding:12px;text-align:center;vertical-align:middle;">
<img src="{{ $qrSrc }}" width="64" height="64" alt="QR {{ $code }}" style="display:block;border-radius:6px;margin:0 auto;">
<div style="color:#6E6762;font-size:9px;font-family:monospace;margin-top:6px;">{{ $code }}</div>
</td>
</tr>
</table>

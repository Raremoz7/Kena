@props(['url'])
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:20px 0;">
<tr>
<td style="background-color:#A8323B;border-radius:8px;">
<a href="{{ $url }}" style="display:block;padding:12px 24px;color:#FFFFFF;font-family:'Oswald','Arial Narrow','Helvetica Neue',Arial,sans-serif;text-transform:uppercase;font-size:13px;letter-spacing:1px;text-decoration:none;">{{ $slot }}</a>
</td>
</tr>
</table>

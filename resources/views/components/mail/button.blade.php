@props(['url'])
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:20px 0;">
<tr>
<td style="background-color:#BD4049;border-radius:6px;">
<a href="{{ $url }}" style="display:block;padding:12px 24px;color:#FCF3F0;font-family:'Oswald',Georgia,serif;text-transform:uppercase;font-size:13px;letter-spacing:1px;text-decoration:none;">{{ $slot }}</a>
</td>
</tr>
</table>

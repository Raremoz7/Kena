@props(['title' => 'Kena'])
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Oswald:wght@500;700&family=Hanken+Grotesk:wght@400;600&display=swap" rel="stylesheet">
<title>{{ $title }}</title>
</head>
<body style="margin:0;padding:0;background-color:#120C08;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#120C08;">
<tr>
<td align="center" style="padding:32px 16px;">
<table role="presentation" width="480" cellpadding="0" cellspacing="0" style="max-width:480px;width:100%;font-family:'Hanken Grotesk',Arial,sans-serif;">
<tr>
<td style="padding-bottom:20px;">
<span style="font-family:'Oswald',Georgia,serif;color:#BD4049;text-transform:uppercase;letter-spacing:2px;font-size:13px;font-weight:700;">KENA</span>
</td>
</tr>
<tr>
<td>
{{ $slot }}
</td>
</tr>
<tr>
<td style="padding-top:28px;text-align:center;">
<span style="color:#6E6762;font-size:11px;">Kena &middot; Entre em cena</span>
</td>
</tr>
</table>
</td>
</tr>
</table>
</body>
</html>

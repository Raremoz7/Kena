@props(['title' => 'Kena'])
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="color-scheme" content="light only">
<meta name="supported-color-schemes" content="light only">
<link href="https://fonts.googleapis.com/css2?family=Oswald:wght@500;700&family=Hanken+Grotesk:wght@400;600&display=swap" rel="stylesheet">
<title>{{ $title }}</title>
</head>
<body style="margin:0;padding:0;background-color:#F3EEE6;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F3EEE6;">
<tr>
<td align="center" style="padding:32px 16px;">
<table role="presentation" width="480" cellpadding="0" cellspacing="0" style="max-width:480px;width:100%;font-family:'Hanken Grotesk',Arial,sans-serif;">
<tr>
<td style="padding-bottom:20px;">
<a href="{{ url('/') }}" style="text-decoration:none;"><img src="{{ url('img/kena-qr-mark.png') }}" width="34" alt="Kena" style="display:block;width:34px;height:auto;border:0;"></a>
</td>
</tr>
<tr>
<td>
{{ $slot }}
</td>
</tr>
<tr>
<td style="padding-top:28px;text-align:center;">
<span style="color:#8A7D73;font-size:11px;">Kena &middot; Entre em cena</span>
</td>
</tr>
</table>
</td>
</tr>
</table>
</body>
</html>

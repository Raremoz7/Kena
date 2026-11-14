@php
    $display = "font-family:'Oswald','Arial Narrow','Helvetica Neue',Arial,sans-serif;";
    $photoLabel = $photoCount === 1 ? 'foto' : 'fotos';
@endphp
<x-mail.layout title="As fotos chegaram">
{{-- Texto do painel de prévia da caixa de entrada. --}}
<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:#F3EEE6;font-size:1px;line-height:1px;">{{ $photoCount }} {{ $photoLabel }} editadas do espetáculo. Toque no rosto da sua criança e veja só as fotos dela.&nbsp;&#847;&nbsp;&#847;&nbsp;&#847;&nbsp;&#847;&nbsp;&#847;&nbsp;&#847;</div>

<p style="{{ $display }}color:#A8323B;text-transform:uppercase;letter-spacing:2px;font-size:12px;margin:0 0 8px;">Fotos do espetáculo</p>
<h1 style="{{ $display }}color:#2A1F1A;text-transform:uppercase;font-size:30px;line-height:1.1;margin:0 0 14px;">As fotos chegaram</h1>

<p style="color:#2A1F1A;font-size:15px;line-height:1.6;margin:0;">Olá{{ $firstName !== '' ? ' '.$firstName : '' }}, as fotos de <strong>{{ $eventTitle }}</strong> estão no Kena: {{ $photoCount }} {{ $photoLabel }}, editadas uma a uma. Aquele momento que passou rápido demais no palco agora fica com vocês.</p>

@if (count($covers) > 0)
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:20px 0 0;">
<tr>
<td colspan="2" style="padding:0 0 6px;">
<a href="{{ $galleryUrl }}" style="text-decoration:none;"><img src="{{ $covers[0] }}" width="480" alt="Foto do espetáculo" style="display:block;width:100%;max-width:480px;height:auto;border-radius:10px;border:0;"></a>
</td>
</tr>
@if (count($covers) > 2)
<tr>
<td width="50%" style="padding:0 3px 0 0;">
<a href="{{ $galleryUrl }}" style="text-decoration:none;"><img src="{{ $covers[1] }}" width="237" alt="" style="display:block;width:100%;height:150px;object-fit:cover;border-radius:10px;border:0;"></a>
</td>
<td width="50%" style="padding:0 0 0 3px;">
<a href="{{ $galleryUrl }}" style="text-decoration:none;"><img src="{{ $covers[2] }}" width="237" alt="" style="display:block;width:100%;height:150px;object-fit:cover;border-radius:10px;border:0;"></a>
</td>
</tr>
@endif
</table>
<p style="color:#8A7D73;font-size:11px;margin:6px 0 0;">Prévias com marca d'água. A foto comprada vem limpa.</p>
@endif

<h2 style="{{ $display }}color:#2A1F1A;text-transform:uppercase;font-size:18px;margin:28px 0 8px;">Ache sua criança pelo rosto</h2>
@if ($peopleCount > 0)
<p style="color:#6B5F57;font-size:14px;line-height:1.6;margin:0;">A galeria já conhece o rosto de {{ $peopleCount }} {{ $peopleCount === 1 ? 'criança' : 'crianças' }} do espetáculo. Toque no rosto da sua e aparecem só as fotos em que ela está, sem rolar o álbum inteiro. Dá para marcar irmãos e amigos juntos.</p>
@else
<p style="color:#6B5F57;font-size:14px;line-height:1.6;margin:0;">Escolha a turma da sua criança e a galeria mostra só as fotos dela, sem rolar o álbum inteiro.</p>
@endif

@if ($unitPrice !== null)
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#FFFFFF;border:1px solid #E2D8CC;border-radius:10px;margin:20px 0 0;">
<tr>
<td style="padding:18px 18px 4px;">
<span style="{{ $display }}color:#2A1F1A;font-size:28px;">R$ {{ $unitPrice }}</span>
<span style="color:#6B5F57;font-size:14px;"> por foto</span>
</td>
</tr>
@if ($bestQty !== null)
<tr>
<td style="padding:4px 18px 0;">
<span style="display:inline-block;background-color:#F6E3E4;color:#A8323B;font-size:13px;font-weight:600;border-radius:999px;padding:4px 10px;">A partir de {{ $bestQty }} fotos, cada uma sai por R$ {{ $bestPrice }}</span>
</td>
</tr>
@endif
<tr>
<td style="padding:12px 18px 18px;color:#6B5F57;font-size:13px;line-height:1.6;">Depois do pagamento você baixa o arquivo original, em alta resolução e sem marca d'água. As fotos ficam guardadas na sua conta.</td>
</tr>
</table>
@endif

<x-mail.button :url="$galleryUrl">Abrir a galeria de fotos</x-mail.button>

<p style="color:#6B5F57;font-size:13px;line-height:1.6;margin:0;">Entre com este e-mail e a galeria abre direto, porque o seu ingresso está na sua conta.</p>

<x-mail.info-panel>
<div style="color:#2A1F1A;font-size:14px;font-weight:600;">Quer mostrar para a família?</div>
<div style="color:#6B5F57;font-size:13px;line-height:1.6;margin-top:4px;">Avós, padrinhos e quem mais quiser ver entram com o código da galeria:</div>
<div style="{{ $display }}color:#A8323B;font-size:22px;letter-spacing:2px;margin-top:8px;">{{ $accessCode }}</div>
</x-mail.info-panel>

<p style="color:#8A7D73;font-size:11px;line-height:1.6;margin:20px 0 0;">Você recebe este aviso uma única vez, porque comprou ingresso para {{ $eventTitle }}. A galeria mostra rosto e nome das crianças e só abre para as famílias deste espetáculo.</p>
</x-mail.layout>

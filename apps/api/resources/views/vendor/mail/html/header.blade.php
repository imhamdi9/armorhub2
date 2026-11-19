@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none; font-size: 22px; font-weight: 800; color: #0b1b3a; letter-spacing: -0.3px;">
<span style="display:inline-block;width:30px;height:30px;line-height:30px;border-radius:9px;background:#0b1b3a;color:#fff;font-size:16px;text-align:center;vertical-align:middle;">&#9679;</span>
<span style="vertical-align:middle;">{!! $slot !!}</span>
</a>
</td>
</tr>

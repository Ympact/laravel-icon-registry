{{--
Draws a stored icon key with whichever provider lists it, or the icon of a purpose, and nothing
when neither was chosen. With a purpose, `labelled` names the icon with the purpose's label.
--}}
@props(['icon' => null, 'purpose' => null])
@if (filled($purpose)){{ app(\Ympact\IconRegistry\IconRegistry::class)->renderPurpose($purpose, $attributes) }}@elseif (filled($icon)){{ app(\Ympact\IconRegistry\IconRegistry::class)->render($icon, $attributes) }}@endif

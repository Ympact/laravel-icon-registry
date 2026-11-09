{{--
Draws a stored icon key with whichever provider lists it, and nothing for a key never chosen.
--}}
@props(['icon' => null])
@if (filled($icon)){{ app(\Ympact\IconRegistry\IconRegistry::class)->render($icon, $attributes) }}@endif

{{--
Draws a stored icon key with whichever provider lists it: <x-icon-registry::icon icon="tabler.home" class="size-5" />.
--}}
@props(['icon'])
{{ app(\Ympact\IconRegistry\IconRegistry::class)->render($icon, $attributes) }}

{{--
Draws a stored icon key with whichever provider lists it: <x-icon-registry::icon icon="tabler.home" class="size-5" />.
Decorative unless named: pass label="Home" for an icon that carries meaning on its own.
--}}
@props(['icon'])
{{ app(\Ympact\IconRegistry\IconRegistry::class)->render($icon, $attributes) }}

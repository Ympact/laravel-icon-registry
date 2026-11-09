{{--
Draws a stored icon key with whichever provider lists it: <x-icon-registry::icon icon="tabler.home" class="size-5" />,
or the icon of a purpose: <x-icon-registry::icon purpose="action.delete" />, which `labelled` names with the purpose's label.
Decorative unless named: pass label="Home" for an icon that carries meaning on its own.
--}}
@props(['icon' => null, 'purpose' => null])
{{ $purpose !== null
    ? app(\Ympact\IconRegistry\IconRegistry::class)->renderPurpose($purpose, $attributes)
    : app(\Ympact\IconRegistry\IconRegistry::class)->render($icon, $attributes) }}

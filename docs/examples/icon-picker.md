# Building an icon picker

The registry has no UI of its own. This Livewire component is a complete picker: a search box, a
list of results and validation on save.

```php
namespace App\Livewire;

use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Ympact\IconRegistry\Facades\Icons;
use Ympact\IconRegistry\Rules\IconExists;

class IconPicker extends Component
{
    public string $query = '';

    public ?string $icon = null;

    #[Computed]
    public function results(): Collection
    {
        return Icons::search($this->query, limit: 40);
    }

    public function choose(string $key): void
    {
        validator(['icon' => $key], ['icon' => ['required', 'string', new IconExists]])->validate();

        $this->icon = $key;
    }
}
```

```blade
<div>
    <label for="icon-search">Search icons</label>
    <input id="icon-search" type="search" wire:model.live.debounce.200ms="query" />

    <p aria-live="polite">{{ $this->results->count() }} icons found</p>

    <ul>
        @foreach ($this->results as $result)
            <li wire:key="{{ $result->key }}">
                <button type="button" wire:click="choose('{{ $result->key }}')" aria-label="{{ $result->label }}">
                    <x-registry-icon :icon="$result->key" class="size-5" aria-hidden="true" />
                </button>
            </li>
        @endforeach
    </ul>
</div>
```

Store `$icon` as it is: it is the key, and `<x-registry-icon :icon="$model->icon" />` draws it,
drawing nothing while it is null.

Validate again where the value is finally saved, since a Livewire property can be set by the client.
Run `php artisan optimize` on deploy: without a cache, each search request scans every provider.

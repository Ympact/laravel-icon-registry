<?php

namespace Ympact\IconRegistry\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Ympact\IconRegistry\Icon;
use Ympact\IconRegistry\IconRegistry;

/**
 * Rejects an icon key no configured provider lists, suggesting the closest one that is listed.
 * A stored key nothing can draw is otherwise an exception on whichever page later draws it.
 */
class IconExists implements ValidationRule
{
    public function __construct(
        protected ?string $vendor = null,
        protected ?string $provider = null,
    ) {}

    /**
     * Restricts the rule to one vendor, e.g. IconExists::vendor('tabler').
     */
    public static function vendor(string $vendor): self
    {
        return new self(vendor: $vendor);
    }

    /**
     * Restricts the rule to one provider, e.g. IconExists::provider('blade-icons').
     */
    public static function provider(string $provider): self
    {
        return new self(provider: $provider);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            // Whether empty is allowed is `required`/`nullable`'s business.
            return;
        }

        $registry = app(IconRegistry::class);
        $key = trim($value);

        // A cache written before the icon was built would reject a good key, so confirm on disk before failing.
        if ($this->matches($registry->find($key)) || $this->matches($registry->find($key, fresh: true))) {
            return;
        }

        $suggestion = $registry->search($key, vendor: $this->vendor, limit: 1, provider: $this->provider)->first();

        $fail($suggestion === null
            ? 'icon-registry::validation.missing'
            : 'icon-registry::validation.missing_with_suggestion')
            ->translate(['suggestion' => $suggestion?->key]);
    }

    protected function matches(?Icon $icon): bool
    {
        return $icon !== null
            && ($this->vendor === null || $icon->vendor === $this->vendor)
            && ($this->provider === null || $icon->provider === $this->provider);
    }
}

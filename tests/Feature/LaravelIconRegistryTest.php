<?php

it('registers its configuration', function (): void {
    expect(config('laravel-icon-registry'))->toBeArray();
});

<?php

it('serves the workbench home page', function (): void {
    visit('/')
        ->assertSee('LaravelIconRegistry')
        ->assertNoJavaScriptErrors();
});

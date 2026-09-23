<?php

declare(strict_types=1);

namespace Support\Helpers;

/**
 * Registers a shared set of smoke tests for a Filament resource. Expects the enclosing test file's
 * beforeEach() to have set $this->tester to a configured Support\Testing\FilamentResourceTester.
 */
function testFilamentResource(): void
{
    it('loads the index page', function (): void {
        $this->tester->assertIndexPageLoads();
    });

    it('loads the create page, if the resource has one', function (): void {
        $this->tester->assertCreatePageWorksIfAvailable();
    });

    it('loads the edit page, if the resource has one', function (): void {
        $this->tester->assertEditPageWorksIfAvailable();
    });

    it('loads the view page, if the resource has one', function (): void {
        $this->tester->assertViewPageWorksIfAvailable();
    });
}

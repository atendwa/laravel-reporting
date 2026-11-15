<?php

declare(strict_types=1);

namespace Support\Testing;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;
use PHPUnit\Framework\Assert as PHPUnit;

/**
 * Generic smoke tests for a Filament resource: exercises whichever of the index/create/edit/view
 * pages the resource actually registers, using its factory to seed and locate records.
 */
final readonly class FilamentResourceTester
{
    /**
     * @param  class-string  $resourceClass
     * @param  class-string<Factory<Model>>  $factoryClass
     */
    public function __construct(
        public string $resourceClass,
        public string $factoryClass,
        public string $searchAttribute,
        public bool $testSearch = true,
    ) {}

    public function assertIndexPageLoads(): void
    {
        $record = $this->factoryClass::new()->create();

        $component = Livewire::test($this->indexPageClass())->assertSuccessful();

        if ($this->testSearch) {
            $component->assertSee($record->getAttribute($this->searchAttribute));
        }
    }

    public function assertCreatePageWorksIfAvailable(): void
    {
        if (! $this->resourceClass::hasPage('create')) {
            PHPUnit::assertFalse($this->resourceClass::hasPage('create'), 'The resource offers no create page.');

            return;
        }

        Livewire::test($this->createPageClass())->assertSuccessful();
    }

    public function assertEditPageWorksIfAvailable(): void
    {
        if (! $this->resourceClass::hasPage('edit')) {
            PHPUnit::assertFalse($this->resourceClass::hasPage('edit'), 'The resource offers no edit page.');

            return;
        }

        $record = $this->factoryClass::new()->create();

        Livewire::test($this->editPageClass(), ['record' => $record->getKey()])->assertSuccessful();
    }

    public function assertViewPageWorksIfAvailable(): void
    {
        if (! $this->resourceClass::hasPage('view')) {
            PHPUnit::assertFalse($this->resourceClass::hasPage('view'), 'The resource offers no view page.');

            return;
        }

        $record = $this->factoryClass::new()->create();

        Livewire::test($this->viewPageClass(), ['record' => $record->getKey()])->assertSuccessful();
    }

    /**
     * @return class-string
     */
    private function indexPageClass(): string
    {
        return $this->resourceClass::getPages()['index']->getPage();
    }

    /**
     * @return class-string
     */
    private function createPageClass(): string
    {
        return $this->resourceClass::getPages()['create']->getPage();
    }

    /**
     * @return class-string
     */
    private function editPageClass(): string
    {
        return $this->resourceClass::getPages()['edit']->getPage();
    }

    /**
     * @return class-string
     */
    private function viewPageClass(): string
    {
        return $this->resourceClass::getPages()['view']->getPage();
    }
}

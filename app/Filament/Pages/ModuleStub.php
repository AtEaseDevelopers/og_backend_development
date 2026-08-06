<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

abstract class ModuleStub extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static string $view = 'filament.pages.module-stub';

    protected static bool $shouldRegisterNavigation = false;

    public string $moduleTitle = 'Module';

    public string $phase = 'Phase 2+';

    public function getTitle(): string
    {
        return $this->moduleTitle;
    }
}

<?php

declare(strict_types=1);

namespace Rimba\Helpfile;

use Rimba\Base\BitesServiceProvider;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Livewire\Livewire;
use Rimba\HelpFile\Actions\GetHelpAction;


class HelpfileServiceProvider extends BitesServiceProvider
{
    protected string $viewsPath = __DIR__ . '/../resources/views';
    protected string $iconsPath = __DIR__ . '/../resources/svg';

    protected function bootPackage(): void
    {
        Livewire::component('bites.help-button', GetHelpAction::class);
        FilamentView::registerRenderHook(
            PanelsRenderHook::USER_MENU_AFTER,
            fn(): string => \Illuminate\Support\Facades\Blade::render('@livewire(\'bites.help-button\')'),
        );
        FilamentView::registerRenderHook(
            PanelsRenderHook::SIMPLE_PAGE_END,
            fn(): string => \Illuminate\Support\Facades\Blade::render('@livewire(\'bites.help-button\')'),
        );

    }
    protected function registerPackage(): void
    {
        //
    }

}

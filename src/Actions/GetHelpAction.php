<?php

declare(strict_types=1);

namespace Rimba\HelpFile\Actions;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

class GetHelpAction extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    /**
     * These MUST be public.
     *
     * Livewire persists public properties between requests.
     */
    public string $panelPath = '';

    public string $helpFile = '';

    /*
    |--------------------------------------------------------------------------
    | Mount
    |--------------------------------------------------------------------------
    */

    public function mount(): void
    {
        $route = Route::current();

        $this->panelPath = trim(
            filament()->getCurrentPanel()?->getPath() ?? '',
            '/'
        );

        $this->helpFile = $this->resolveHelpFileName($route);
    }

    /*
    |--------------------------------------------------------------------------
    | Help File Name
    |--------------------------------------------------------------------------
    */

    protected function resolveHelpFileName($route): string
    {
        if (! $route) {
            return '';
        }

        $uri = trim(
            $route->uri(),
            '/'
        );

        /*
         * Deal with the panel root.
         *
         * /staff
         *      =>
         * staff
         *
         * /admin
         *      =>
         * admin
         */
        if ($uri === $this->panelPath) {
            return $this->panelPath;
        }

        /*
         * Remove panel path from the URI.
         *
         * admin/agreements/{record}
         *      =>
         * agreements/{record}
         */
        if (
            $this->panelPath !== ''
            && str_starts_with(
                $uri,
                $this->panelPath.'/'
            )
        ) {
            $uri = substr(
                $uri,
                strlen($this->panelPath) + 1
            );
        }

        /*
         * Remove tenant segment.
         */
        $uri = preg_replace(
            '#^\{tenant\}/#',
            '',
            $uri
        ) ?? $uri;

        /*
         * Replace route parameters with their actual values.
         *
         * agreements/{record}
         *      =>
         * agreements/1
         */
        foreach ($route->parameters() as $name => $value) {
            if (is_object($value)) {
                $value = method_exists($value, 'getRouteKey')
                    ? $value->getRouteKey()
                    : (string) $value;
            }

            $uri = str_replace(
                '{'.$name.'}',
                (string) $value,
                $uri
            );

            /*
             * Also handle optional parameters.
             */
            $uri = str_replace(
                '{'.$name.'?}',
                (string) $value,
                $uri
            );
        }

        /*
         * Convert:
         *
         * agreements/1
         *
         * into:
         *
         * agreements.1
         */
        return str($uri)
            ->replace('/', '.')
            ->trim('.')
            ->toString();
    }

    /*
    |--------------------------------------------------------------------------
    | Help Paths
    |--------------------------------------------------------------------------
    */

    protected function getHelpDirectory(): string
    {
        return collect([
            'help',
            $this->panelPath,
        ])
            ->filter()
            ->implode('/');
    }

    protected function getHelpFilePath(string $helpFile): string
    {
        return $this->getHelpDirectory()
            .'/'
            .$helpFile
            .'.md';
    }

    /*
    |--------------------------------------------------------------------------
    | Help Resolution
    |--------------------------------------------------------------------------
    */

    protected function resolveHelpFile(): array
    {
        $requested = $this->helpFile;

        if ($requested === '') {
            return [
                'requested' => '',
                'resolved' => null,
                'path' => null,
                'inherited' => false,
            ];
        }

        $resolved = $requested;

        while ($resolved !== '') {
            $path = $this->getHelpFilePath(
                $resolved
            );

            if (
                Storage::disk('local')
                    ->exists($path)
            ) {
                return [
                    'requested' => $requested,
                    'resolved' => $resolved,
                    'path' => $path,
                    'inherited' => $requested !== $resolved,
                ];
            }

            /*
             * Walk up the hierarchy:
             *
             * agreements.1.edit
             * agreements.1
             * agreements
             */
            if (! str_contains($resolved, '.')) {
                break;
            }

            $resolved = str($resolved)
                ->beforeLast('.')
                ->toString();
        }

        return [
            'requested' => $requested,
            'resolved' => null,
            'path' => null,
            'inherited' => false,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Markdown
    |--------------------------------------------------------------------------
    */

    protected function buildMarkdownContent(): string
    {
        $help = $this->resolveHelpFile();

        if ($help['requested'] === '') {
            return <<<'MD'
> [!WARNING]
> Unable to determine the help file for this page.
MD;
        }

        $expectedPath = storage_path(
            'app/private/'
            .$this->getHelpFilePath(
                $help['requested']
            )
        );

        if (! $help['path']) {
            return <<<MD
> [!WARNING]
> ## **No help file exists for:** `{$help['requested']}`
>
> Please create the following markdown file:
>
> `{$expectedPath}`

---
MD;
        }

        $content = Storage::disk('local')
            ->get($help['path']);

        if (! $help['inherited']) {
            return $content;
        }

        return <<<MD
> [!WARNING]
> ## **No help file exists for:** `{$help['requested']}`
>
> Please create the following markdown file:
>
> `{$expectedPath}`
>
> Displaying the closest parent documentation:
> `{$help['resolved']}`

---

{$content}
MD;
    }

    /*
    |--------------------------------------------------------------------------
    | Filament Action
    |--------------------------------------------------------------------------
    */

    public function myHeaderAction(): Action
    {
        return Action::make('myHeader')
            ->icon('bites-helpbook')
            ->label('Quick Guide')
            ->iconButton()
            ->modalHeading('Documentation')
            ->slideOver()
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->schema([
                ViewField::make('markdown_preview')
                    ->view('bites::markdown')
                    ->viewData([
                        'content' => $this->buildMarkdownContent(),
                    ]),
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Render
    |--------------------------------------------------------------------------
    */

    public function render(): Factory|View
    {
        return view('bites::help-button');
    }
}

<?php

namespace Rimba\HelpFile\Actions;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Support\Facades\Route;
use Livewire\Component;

class GetHelpAction extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    public string $helpFile = '';

    public string $panelPath = '';

    public function mount(): void
    {
        $route = Route::current();
        $uri = ltrim((string) $route?->uri(), '/');

        $this->panelPath = ltrim(
            (string) filament()->getCurrentPanel()->getPath(),
            '/'
        );

        $this->helpFile = str($uri)
            ->when(
                $this->panelPath !== '',
                fn ($str) => $str->after($this->panelPath.'/')
            )
            ->replaceMatches('/\{tenant\}\//', '')
            ->replaceMatches('/\}.*/', '}')
            ->replace('{record}', (string) $route?->parameter('record'))
            ->replace('/', '.')
            ->toString();
    }

    protected function getHelpDirectory(): string
    {
        return public_path("helpfiles/{$this->panelPath}/");
    }

    protected function getHelpFilePath(string $helpFile): string
    {
        return $this->getHelpDirectory().$helpFile.'.md';
    }

    protected function resolveHelpFile(): array
    {
        $requested = $this->helpFile;
        $resolved = $requested;

        while (true) {
            $file = $this->getHelpFilePath($resolved);

            if (file_exists($file)) {
                return [
                    'requested' => $requested,
                    'resolved' => $resolved,
                    'path' => $file,
                    'inherited' => $requested !== $resolved,
                ];
            }

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

    protected function buildMarkdownContent(): string
    {
        $help = $this->resolveHelpFile();
        $expectedPath = str_replace(
            ['/', '\\'],
            DIRECTORY_SEPARATOR,
            $this->getHelpFilePath($help['requested'])
        );
        if (! $help['path']) {
            return <<<MD
>
> ## **No help file exists for:** `{$help['requested']}`
>
> Please create the following markdown file: 
> 
> `{$expectedPath}`
>

---

MD;
        }

        $content = file_get_contents($help['path']);
        $resolvedName = str($help['resolved'])
            ->afterLast('.')
            ->replace('_', ' ')
            ->headline()
            ->toString();
        $header = <<<MD
# **{$resolvedName} Quick Guide**

---

MD;

        $content = $header.$content;

        if (! $help['inherited']) {
            return $content;
        }

        return <<<MD
> [!WARNING]
> ## **No help file exists for:** `{$help['requested']}`
>
> Please create the following markdown file:
> `{$expectedPath}`
>
> Displaying the closest parent documentation: `{$help['resolved']}`
>

---

{$content}
MD;
    }

    public function myHeaderAction(): Action
    {
        return Action::make('myHeaderAction')
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

    public function render()
    {
        return view('bites::help-button');
    }
}

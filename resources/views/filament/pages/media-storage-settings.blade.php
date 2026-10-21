<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="pt-6 flex gap-4">
            <x-filament::button type="submit">
                <x-filament::loading-indicator class="h-5 w-5 inline" wire:loading wire:target="save" />
                {{ __('r2-media::r2-media.actions.save') }}
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>

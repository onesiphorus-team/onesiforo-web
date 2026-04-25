<x-filament-panels::page>
    <livewire:filament.screenshots-viewer :record="$this->getRecord()" :key="'viewer-'.$this->getRecord()->id" />
</x-filament-panels::page>

<x-filament-panels::page>
    <livewire:filament.box-diagnostics-panel
        :record="$this->getRecord()"
        :key="'diagnostics-'.$this->getRecord()->id"
    />
</x-filament-panels::page>

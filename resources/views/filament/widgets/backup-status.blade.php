<x-filament-widgets::widget>
    <x-filament::section>
        <div style="display:flex;flex-wrap:wrap;gap:.8rem;align-items:center;justify-content:space-between">
            <div>
                <strong style="color:#d92d20">Backup needed.</strong>
                {{ $this->lastText() }} A backup is older than {{ \App\Support\Backup\BackupManager::STALE_DAYS }} days, or missing.
            </div>
            <x-filament::button tag="a" :href="$this->url()">Open Backups</x-filament::button>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>

<x-filament-panels::page>
    @php $s = $this->status(); @endphp

    <p style="max-width:50rem">
        A backup is a safe copy of the site. If something is broken or deleted by mistake, the copy lets us put it back.
        There are two kinds: the <strong>database</strong> (all texts, categories, people and settings) and the <strong>files</strong> (all uploaded pictures).
        A copy is made every night, and copies older than the keep time are removed by themselves.
    </p>

    @if ($s['stale'])
        <div style="max-width:50rem;padding:.7rem .9rem;border:1px solid #d92d20;border-radius:.5rem;color:#d92d20">
            <strong>The last database backup is more than {{ \App\Support\Backup\BackupManager::STALE_DAYS }} days old, or there is none.</strong>
            Press "Back up the database now" and check that the nightly task is set up (instructions at the bottom).
        </div>
    @endif
    @if ($s['last_errors'] !== '')
        <div style="max-width:50rem;padding:.7rem .9rem;border:1px solid #dc9b00;border-radius:.5rem;color:#b07800">
            The last automatic backup had a problem: {{ $s['last_errors'] }}
        </div>
    @endif

    <x-filament::section>
        <x-slot name="heading">Make a backup now</x-slot>
        <div style="display:flex;flex-wrap:wrap;gap:.6rem;align-items:center">
            <x-filament::button wire:click="backupDatabase" wire:loading.attr="disabled" wire:target="backupDatabase,backupFiles">Back up the database now</x-filament::button>
            <x-filament::button color="gray" wire:click="backupFiles" wire:loading.attr="disabled" wire:target="backupDatabase,backupFiles">Back up the pictures and files now</x-filament::button>
            <span wire:loading wire:target="backupDatabase,backupFiles" style="opacity:.8">Working. Please wait, a big copy takes a minute.</span>
        </div>
        <p style="margin-top:.8rem;opacity:.85">
            Last database copy: <strong>{{ $s['database']?->format('j M Y, H:i') ?? 'none yet' }}</strong>
            &middot; Last files copy: <strong>{{ $s['files']?->format('j M Y, H:i') ?? 'none yet' }}</strong>
            &middot; {{ $s['count'] }} copies, {{ $s['used'] }} in total
        </p>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">How long copies are kept</x-slot>
        <form wire:submit="saveKeepDays" style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap">
            <input type="number" wire:model="keepDays" min="1" max="365" style="width:6rem;padding:.45rem;border:1px solid rgba(128,128,128,.5);border-radius:.4rem;background:transparent">
            <span>days</span>
            <x-filament::button type="submit" color="gray">Save</x-filament::button>
            <span style="opacity:.75">The newest copy of each kind is always kept, even if it is older.</span>
        </form>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Your copies</x-slot>
        @if ($this->copies() === [])
            <p class="empty">There are no copies yet.</p>
        @else
            <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse">
                    <thead>
                        <tr style="text-align:left;border-bottom:1px solid rgba(128,128,128,.4)">
                            <th style="padding:.4rem">Kind</th><th style="padding:.4rem">Made</th><th style="padding:.4rem">Size</th><th style="padding:.4rem">File</th><th style="padding:.4rem"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->copies() as $copy)
                            <tr wire:key="copy-{{ $copy['name'] }}" style="border-bottom:1px solid rgba(128,128,128,.2)">
                                <td style="padding:.4rem">{{ $copy['kind'] === 'database' ? 'Database' : 'Files' }}</td>
                                <td style="padding:.4rem">{{ $copy['made_at']->format('j M Y, H:i') }}</td>
                                <td style="padding:.4rem">{{ $this->human($copy['bytes']) }}</td>
                                <td style="padding:.4rem;font-family:monospace;font-size:.85rem">{{ $copy['name'] }}</td>
                                <td style="padding:.4rem;white-space:nowrap">
                                    <x-filament::button size="sm" wire:click="download('{{ $copy['name'] }}')">Download</x-filament::button>
                                    <x-filament::button size="sm" color="danger" wire:click="remove('{{ $copy['name'] }}')" wire:confirm="Delete this copy? It cannot be brought back.">Delete</x-filament::button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        <p style="margin-top:.8rem;opacity:.85">
            <strong>Please download a copy now and then and keep it somewhere else</strong> (another computer or a cloud drive).
            A copy kept on the same computer or server does not help if that computer or server is lost.
            The database copy holds the hashed passwords of your people, so keep it private.
        </p>
    </x-filament::section>

    <x-filament::section collapsible collapsed>
        <x-slot name="heading">Set up the nightly backup (one time)</x-slot>
        <div style="max-width:52rem;display:grid;gap:.8rem">
            <p>The site makes a copy every night at 03:00, but only if the computer or server runs one small repeating task. It needs to run <strong>every minute</strong>: <code>php artisan schedule:run</code>.</p>

            <div>
                <strong>On your computer with XAMPP (Windows).</strong> Open <em>Task Scheduler</em> &rarr; <em>Create Basic Task</em> &rarr; repeat every minute &rarr; action "Start a program":
                <pre style="padding:.6rem;border:1px solid rgba(128,128,128,.4);border-radius:.4rem;overflow-x:auto">Program:   D:\xampp\php\php.exe
Arguments: {{ $this->artisanPath() }} schedule:run</pre>
                The computer must be switched on at 03:00. If it is not, press "Back up the database now" yourself, or ask me to set the task for another time.
            </div>

            <div>
                <strong>On Hostinger (shared hosting).</strong> In hPanel open <em>Advanced</em> &rarr; <em>Cron Jobs</em> &rarr; add a job that runs every minute:
                <pre style="padding:.6rem;border:1px solid rgba(128,128,128,.4);border-radius:.4rem;overflow-x:auto">* * * * *   php /home/YOUR-USER/domains/infofluxes.com/ARTISAN-FOLDER/artisan schedule:run</pre>
                Hostinger shows the correct path of your account next to the form. I will fill it in with you when the site is moved.
            </div>

            <div>
                <strong>To put a copy back (restore).</strong> This is not a button, because a mistake would replace the whole live site. Download the database copy, then in phpMyAdmin
                open an empty database &rarr; <em>Import</em> &rarr; choose the <code>.sql.gz</code> file. For the pictures, unzip the files copy into <code>storage/app/public</code>.
                Ask me to do it with you.
            </div>
        </div>
    </x-filament::section>
</x-filament-panels::page>

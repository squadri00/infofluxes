<?php

namespace Tests\Feature;

use App\Filament\Pages\Backups;
use App\Models\Article;
use App\Models\Category;
use App\Models\Setting;
use App\Models\User;
use App\Support\Backup\BackupManager;
use App\Support\Backup\SqlDump;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 48: backups of the database and of the uploaded files. */
class Step48Test extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    private string $source;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
        $this->dir = sys_get_temp_dir().'/if-backup-test-'.uniqid();
        $this->source = sys_get_temp_dir().'/if-upload-test-'.uniqid();
        File::makeDirectory($this->source.'/pictures', 0777, true);
        file_put_contents($this->source.'/pictures/a.webp', 'picture-bytes');
        file_put_contents($this->source.'/.gitignore', '*');
        config(['infofluxes.backup_path' => $this->dir, 'infofluxes.backup_source' => $this->source]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        File::deleteDirectory($this->source);
        parent::tearDown();
    }

    private function superuser(): User
    {
        $u = User::factory()->create(['role' => 'superuser', 'status' => 'active']);
        $this->actingAs($u);

        return $u;
    }

    private function article(): Article
    {
        $cat = Category::create(['name' => ['en' => 'Shows'], 'slug' => 'shows']);

        return Article::create(['title' => ['en' => "It's a title with 'quotes' and \\ a backslash ﷺ"], 'slug' => 'a', 'category_id' => $cat->id, 'long_detail' => ['en' => "<p>Line one\nLine two</p>"], 'custom_data' => ['k' => 'v']]);
    }

    public function test_the_database_copy_is_complete_and_has_the_data(): void
    {
        $this->article();

        $name = BackupManager::makeDatabase();
        $path = BackupManager::path($name);

        $this->assertMatchesRegularExpression(BackupManager::NAME_PATTERN, $name);
        $this->assertTrue(SqlDump::isComplete($path));
        $sql = gzdecode((string) file_get_contents($path));
        $this->assertStringContainsString('CREATE TABLE', $sql);
        $this->assertStringContainsString('INSERT INTO `articles`', $sql);
        $this->assertStringContainsString('backslash ﷺ', $sql);
        $this->assertStringContainsString('-- End of backup', $sql);
        $this->assertStringNotContainsString('APP_KEY', $sql);
        $this->assertFileDoesNotExist($path.'.part', 'no half-made file is left');
    }

    public function test_the_copy_can_be_restored_and_gives_back_the_same_rows(): void
    {
        $a = $this->article();
        $path = BackupManager::path(BackupManager::makeDatabase());

        $pdo = new \PDO('sqlite::memory:');
        $pdo->exec((string) gzdecode((string) file_get_contents($path)));

        $row = $pdo->query('SELECT title, long_detail, custom_data FROM articles WHERE id = '.$a->id)->fetch(\PDO::FETCH_ASSOC);
        $this->assertSame($a->getRawOriginal('title'), $row['title'], 'quotes, backslashes and the salutation survive');
        $this->assertSame($a->getRawOriginal('long_detail'), $row['long_detail'], 'line breaks survive');
        $this->assertSame((int) Article::count(), (int) $pdo->query('SELECT count(*) FROM articles')->fetchColumn());
        $this->assertSame((int) User::count(), (int) $pdo->query('SELECT count(*) FROM users')->fetchColumn());
    }

    public function test_temporary_tables_keep_their_structure_but_not_their_rows(): void
    {
        \Illuminate\Support\Facades\DB::table('sessions')->insert(['id' => 'secret-session-id', 'payload' => 'x', 'last_activity' => time()]);

        $sql = gzdecode((string) file_get_contents(BackupManager::path(BackupManager::makeDatabase())));

        $this->assertStringContainsString('CREATE TABLE', $sql);
        $this->assertStringContainsString('DROP TABLE IF EXISTS `sessions`', $sql);
        $this->assertStringNotContainsString('secret-session-id', $sql);
    }

    public function test_the_files_copy_holds_the_uploads_and_skips_gitignore(): void
    {
        $path = BackupManager::path(BackupManager::makeFiles());

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path));
        $this->assertNotFalse($zip->locateName('pictures/a.webp'));
        $this->assertFalse($zip->locateName('.gitignore'));
        $this->assertSame('picture-bytes', $zip->getFromName('pictures/a.webp'));
        $zip->close();
    }

    public function test_the_folder_is_closed_to_the_web(): void
    {
        BackupManager::directory();

        $this->assertStringContainsString('Deny from all', (string) file_get_contents($this->dir.'/.htaccess'));
    }

    public function test_run_makes_both_and_records_the_time(): void
    {
        $r = BackupManager::run();

        $this->assertCount(2, $r['made']);
        $this->assertSame([], $r['errors']);
        $this->assertNotEmpty(Setting::get('backup_last_run'));
        $this->assertCount(2, BackupManager::all());
        $this->assertFalse(BackupManager::isStale());
    }

    public function test_old_copies_are_removed_but_the_newest_of_each_kind_stays(): void
    {
        File::ensureDirectoryExists($this->dir);
        $make = function (string $name, int $daysAgo) {
            file_put_contents($this->dir.'/'.$name, 'x');
            touch($this->dir.'/'.$name, now()->subDays($daysAgo)->timestamp);
        };
        $make('database-2026-01-01_010101.sql.gz', 40);
        $make('database-2026-01-20_010101.sql.gz', 20);
        $make('database-2026-02-10_010101.sql.gz', 5);
        $make('files-2026-01-01_010101.zip', 60);   // the only (so newest) files copy, old but kept
        $make('notes.txt', 90);                      // not a backup: never touched

        $removed = BackupManager::prune(14);

        $this->assertSame(2, $removed);
        $names = array_column(BackupManager::all(), 'name');
        $this->assertSame(['database-2026-02-10_010101.sql.gz', 'files-2026-01-01_010101.zip'], $names);
        $this->assertFileExists($this->dir.'/notes.txt');
    }

    public function test_the_keep_time_is_14_days_unless_changed_and_stays_in_range(): void
    {
        $this->assertSame(14, BackupManager::keepDays());
        Setting::put('backup_keep_days', 30);
        $this->assertSame(30, BackupManager::keepDays());
        Setting::put('backup_keep_days', 0);
        $this->assertSame(1, BackupManager::keepDays());
        Setting::put('backup_keep_days', 9999);
        $this->assertSame(365, BackupManager::keepDays());
    }

    public function test_a_wrong_file_name_is_never_followed(): void
    {
        foreach (['../.env', '..\\.env', 'database-2026-01-01_010101.sql.gz/../../x', 'database-x.sql.gz', 'notes.txt', ''] as $bad) {
            $this->assertNull(BackupManager::path($bad), $bad);
            $this->assertFalse(BackupManager::delete($bad), $bad);
        }
    }

    public function test_it_is_stale_when_there_is_none_or_the_last_is_old(): void
    {
        $this->assertTrue(BackupManager::isStale());

        $name = BackupManager::makeDatabase();
        $this->assertFalse(BackupManager::isStale());

        touch(BackupManager::path($name), now()->subDays(3)->timestamp);
        $this->assertTrue(BackupManager::isStale());
    }

    public function test_the_command_works_and_reports(): void
    {
        $this->artisan('backup:run', ['--only' => 'database'])->expectsOutputToContain('Made: database-')->assertSuccessful();
        $this->assertCount(1, BackupManager::all());
    }

    public function test_the_night_task_is_scheduled(): void
    {
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->filter(fn ($e) => str_contains($e->command, 'backup:run'));

        $this->assertCount(1, $events);
        $this->assertSame('0 3 * * *', $events->first()->expression);
    }

    // ---------- the screen ----------

    public function test_only_the_super_user_sees_the_screen(): void
    {
        foreach (['moderator', 'author', 'member'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'status' => 'active', 'admin_screens' => ['articles', 'import', 'categories', 'comments']]));
            $this->get('/admin/backups')->assertForbidden();
        }
        $this->superuser();
        $this->get('/admin/backups')->assertOk()->assertSee('Back up the database now');
    }

    public function test_the_buttons_make_copies_and_a_copy_can_be_downloaded_and_deleted(): void
    {
        $this->superuser();

        $page = Livewire::test(Backups::class)->call('backupDatabase')->call('backupFiles');
        $copies = BackupManager::all();
        $this->assertCount(2, $copies);
        $db = collect($copies)->firstWhere('kind', 'database');

        $page->assertSee($db['name'])->call('download', $db['name'])->assertFileDownloaded($db['name']);
        $page->call('remove', $db['name']);
        $this->assertCount(1, BackupManager::all());
    }

    public function test_a_bad_name_is_refused_on_the_screen(): void
    {
        $this->superuser();

        Livewire::test(Backups::class)->call('download', '../.env')->assertNotFound();
    }

    public function test_the_keep_time_is_saved_from_the_screen(): void
    {
        $this->superuser();

        Livewire::test(Backups::class)->assertSet('keepDays', 14)->set('keepDays', 21)->call('saveKeepDays');

        $this->assertSame(21, BackupManager::keepDays());
    }

    public function test_the_dashboard_warns_the_super_user_only_when_there_is_no_recent_backup(): void
    {
        $this->superuser();
        $this->assertTrue(\App\Filament\Widgets\BackupStatus::canView());

        BackupManager::makeDatabase();
        $this->assertFalse(\App\Filament\Widgets\BackupStatus::canView());

        File::deleteDirectory($this->dir);
        $this->actingAs(User::factory()->create(['role' => 'moderator', 'status' => 'active']));
        $this->assertFalse(\App\Filament\Widgets\BackupStatus::canView(), 'a moderator never sees it');
    }
}

# Copies the LIVE data (infofluxes.com) to this computer: the database and the uploaded pictures and audio.
# It only READS from the live site. It replaces your LOCAL database, so content must be edited on the live site only.
#
# Use:    .\pull-live.ps1                              (asks you to type YES first)
#         .\pull-live.ps1 -IntoDatabase test_copy      (a TEST: imports into a spare database and compares; your real local data is not touched)
#         .\pull-live.ps1 -Yes                         (no question)
#
# What it does: 1. backs up your local database and files   2. dumps the live database (the password is read on the server, never shown)
#   3. checks the dump   4. changes the live web addresses to your local address   5. replaces the local database
#   6. copies the pictures and audio that are new on the live site   7. runs the database changes of your local code, and clears the caches.
# Nothing is kept afterwards except the backup: the dump (it holds members' emails) is deleted.

param(
    [switch]$Yes,
    [string]$IntoDatabase = ''
)

$ErrorActionPreference = 'Continue'   # (ssh and tar write notes on the error channel; the exit codes are checked below)
Set-Location $PSScriptRoot
$here = $PSScriptRoot
$key = "$HOME/.ssh/hostinger_ef".Replace('\', '/')
$remoteHost = 'u528385036@217.196.54.17'
$sshOpts = @('-p', '65002', '-i', $key, '-o', 'IdentitiesOnly=yes', '-o', 'BatchMode=yes', '-o', 'LogLevel=ERROR')
$scpOpts = @('-P', '65002', '-i', $key, '-o', 'IdentitiesOnly=yes', '-o', 'BatchMode=yes', '-o', 'LogLevel=ERROR')
$tmp = Join-Path $PSScriptRoot 'storage\app\private\pull'
$liveUrl = 'https://infofluxes.com/'
$test = $IntoDatabase -ne ''

function Fail([string]$m) {
    Write-Host "STOPPED: $m" -ForegroundColor Red
    # Do not leave a dump with members' emails behind, here or on the server.
    Remove-Item (Join-Path $tmp 'live.sql'), (Join-Path $tmp 'live.sql.gz'), (Join-Path $tmp 'live-files.tgz') -ErrorAction SilentlyContinue
    & ssh @sshOpts $remoteHost 'rm -rf ~/pull-tmp' 2>$null
    exit 1
}
function Step([string]$m) { Write-Host "`n== $m" -ForegroundColor Cyan }
function Remote([string]$arguments) {
    $out = & ssh @sshOpts $remoteHost "bash ~/infofluxes-app/tools/pull-live-remote.sh $arguments"
    if ($LASTEXITCODE -ne 0) { Fail "the live server answered with an error ($arguments)." }
    return $out
}

# ---- the local settings (from .env) and the tools ----
$envText = Get-Content (Join-Path $here '.env') -Raw
function EnvValue([string]$name) {
    if ($envText -match "(?m)^$name=(.*)$") { return $Matches[1].Trim().Trim('"') }
    return ''
}
$dbName = EnvValue 'DB_DATABASE'; $dbUser = EnvValue 'DB_USERNAME'; $dbPass = EnvValue 'DB_PASSWORD'
$dbHost = EnvValue 'DB_HOST'; if ($dbHost -eq '') { $dbHost = '127.0.0.1' }
$dbPort = EnvValue 'DB_PORT'; if ($dbPort -eq '') { $dbPort = '3306' }
$appUrl = (EnvValue 'APP_URL').TrimEnd('/') + '/'
$php = (Get-Command php -ErrorAction Stop).Source
$mysqlBin = Join-Path (Split-Path (Split-Path $php)) 'mysql\bin'
$mysql = Join-Path $mysqlBin 'mysql.exe'
if (-not (Test-Path $mysql)) { $mysql = (Get-Command mysql -ErrorAction Stop).Source }
# ($tmp is set at the top, so a failure can clean it)
New-Item -ItemType Directory -Force $tmp | Out-Null
$target = if ($test) { $IntoDatabase } else { $dbName }
$env:MYSQL_PWD = $dbPass

function Sql([string]$sql, [string]$database = '') {
    $a = @("-h$dbHost", "-P$dbPort", "-u$dbUser", '-N', '-B', '--default-character-set=utf8mb4')
    if ($database -ne '') { $a += $database }
    $a += @('-e', $sql)
    return (& $mysql @a)
}
function Expand-Gz([string]$in, [string]$out) {
    $fs = [IO.File]::OpenRead($in)
    $gz = New-Object IO.Compression.GZipStream($fs, [IO.Compression.CompressionMode]::Decompress)
    $o = [IO.File]::Create($out)
    $gz.CopyTo($o); $o.Close(); $gz.Close(); $fs.Close()
}
function Import-File([string]$file, [string]$database) {
    cmd /c "`"$mysql`" -h$dbHost -P$dbPort -u$dbUser --default-character-set=utf8mb4 $database < `"$file`""
    return $LASTEXITCODE
}

Write-Host "Live (infofluxes.com) -> local" -ForegroundColor Green
if ($test) {
    Write-Host "TEST: the live data goes into the spare database '$target'. Your real local data is not touched."
} else {
    Write-Host "This REPLACES your local database '$dbName' with the live one. A backup of your local data is made first."
    if (-not $Yes) {
        $answer = Read-Host "Type YES to go on"
        if ($answer -ne 'YES') { Fail 'nothing was changed.' }
    }
}

# ---- 1. a backup of the local data ----
$backupDb = $null
if (-not $test) {
    Step '1. Backup of the local database and files'
    & $php artisan backup:run
    if ($LASTEXITCODE -ne 0) { Fail 'the local backup failed, so nothing was changed.' }
    $backupDb = Get-ChildItem (Join-Path $here 'storage\app\backups') -Filter 'database-*.sql.gz' | Sort-Object LastWriteTime -Descending | Select-Object -First 1
    Write-Host "Backup: $($backupDb.Name)"
}

# ---- 2. the dump of the live database ----
Step '2. Dump of the live database (read only)'
$startedLines = Remote 'dump'
$started = ($startedLines | Select-Object -Last 1).ToString().Trim()
Remove-Item (Join-Path $tmp 'live.sql.gz'), (Join-Path $tmp 'live.sql') -ErrorAction SilentlyContinue
& scp @scpOpts "${remoteHost}:pull-tmp/live.sql.gz" (Join-Path $tmp 'live.sql.gz')
if ($LASTEXITCODE -ne 0) { Fail 'the dump could not be downloaded.' }
Expand-Gz (Join-Path $tmp 'live.sql.gz') (Join-Path $tmp 'live.sql')
$sqlPath = Join-Path $tmp 'live.sql'
$sql = [IO.File]::ReadAllText($sqlPath, [Text.Encoding]::UTF8)

# ---- 3. the check ----
Step '3. Check of the dump'
$tables = ([regex]::Matches($sql, '(?m)^CREATE TABLE ')).Count
$complete = $sql.Contains('-- Dump completed')
Write-Host ("{0:N0} KB, {1} tables, complete: {2}" -f ($sql.Length / 1024), $tables, $complete)
if (-not $complete -or $tables -lt 30) { Fail 'the dump looks incomplete, so nothing was changed.' }
if ([regex]::IsMatch($sql, 's:\d+:"[^"]*infofluxes\.com')) { Write-Host 'Note: some stored text (a serialized value) holds the live address; it is not changed.' -ForegroundColor Yellow }

# ---- 4. the web addresses ----
Step '4. Live web addresses -> local address'
# The live server writes a first line "/*M!999999\- enable the sandbox mode */" that the older database on this computer cannot read: it is removed.
$sql = [regex]::Replace($sql, '(?m)^/\*M!999999\\-[^\r\n]*\*/\r?\n?', '')
$count = ([regex]::Matches($sql, [regex]::Escape($liveUrl))).Count
$sql = $sql.Replace($liveUrl, $appUrl).Replace($liveUrl.Replace('/', '\/'), $appUrl.Replace('/', '\/'))
[IO.File]::WriteAllText($sqlPath, $sql, (New-Object Text.UTF8Encoding($false)))
Write-Host "$count address(es) changed to $appUrl"

# ---- 5. the import ----
Step "5. Import into '$target'"
if ($test) {
    Sql "DROP DATABASE IF EXISTS ``$target``; CREATE DATABASE ``$target`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" | Out-Null
} else {
    & $php artisan db:wipe --force
    if ($LASTEXITCODE -ne 0) { Fail 'the local database could not be emptied. Your local data is unchanged.' }
}
$code = Import-File $sqlPath $target
if ($code -ne 0) {
    if (-not $test -and $backupDb) {
        Write-Host 'The import failed. Putting your local backup back...' -ForegroundColor Yellow
        $old = Join-Path $tmp 'backup.sql'
        Expand-Gz $backupDb.FullName $old
        & $php artisan db:wipe --force
        $back = Import-File $old $target
        Remove-Item $old -ErrorAction SilentlyContinue
        if ($back -eq 0) { Fail 'the import failed; your local data was put back from the backup.' }
        Fail "the import failed AND the backup could not be put back. The backup is $($backupDb.FullName)"
    }
    Fail 'the import failed.'
}
Write-Host 'Imported.'

# ---- test: compare the real local data with the live data ----
if ($test) {
    Step 'Test: your local data compared with the live data (rows per table)'
    $liveCounts = @{}
    foreach ($t in (Sql 'SHOW TABLES' $target)) { $liveCounts[$t] = [int](Sql "SELECT COUNT(*) FROM ``$t``" $target) }
    $localTables = Sql 'SHOW TABLES' $dbName
    $rows = foreach ($t in $localTables) {
        $l = [int](Sql "SELECT COUNT(*) FROM ``$t``" $dbName)
        $v = if ($liveCounts.ContainsKey($t)) { $liveCounts[$t] } else { -1 }
        if ($v -eq -1 -and $l -eq 0) { continue }   # a temporary table (cache, sessions) that is empty: nothing to compare
        if ($l -ne $v) { [pscustomobject]@{ Table = $t; Local = $l; Live = $v; Note = $(if ($l -gt $v) { 'LOCAL HAS MORE (would be lost)' } else { 'live has more' }) } }
    }
    if ($rows) { $rows | Format-Table -AutoSize } else { Write-Host 'Every table has the same number of rows.' }
    Write-Host "The spare database '$target' can be removed with:  DROP DATABASE $target;"
}

# ---- 6. the pictures and audio ----
if (-not $test) {
    Step '6. Pictures and audio that are new on the live site'
    $marker = Join-Path $tmp 'last-pull-epoch.txt'
    $since = if (Test-Path $marker) { (Get-Content $marker -Raw).Trim() } else { '0' }
    $n = (Remote "files $since" | Select-Object -Last 1).ToString().Trim()
    Write-Host "$n file(s) changed on the live site since the last pull."
    if ([int]$n -gt 0) {
        Remove-Item (Join-Path $tmp 'live-files.tgz') -ErrorAction SilentlyContinue
        & scp @scpOpts "${remoteHost}:pull-tmp/live-files.tgz" (Join-Path $tmp 'live-files.tgz')
        if ($LASTEXITCODE -ne 0) { Fail 'the files could not be downloaded (the database was already replaced).' }
        $dest = Join-Path $here 'storage\app\public'
        New-Item -ItemType Directory -Force $dest | Out-Null
        & tar -xzf (Join-Path $tmp 'live-files.tgz') -C $dest
        if ($LASTEXITCODE -ne 0) { Fail 'the files could not be unpacked (the database was already replaced).' }
    }
    Set-Content $marker $started

    Step '7. Database changes of your local code, and caches'
    & $php artisan migrate --force
    & $php artisan optimize:clear | Out-Null
}

# ---- sanity and clean-up ----
Step 'Check'
$liveArticles = (Remote 'counts' | Where-Object { $_ -like "articles`t*" } | Select-Object -First 1)
$localArticles = Sql 'SELECT COUNT(*) FROM `articles`' $target
Write-Host "Articles: live $($liveArticles -replace "articles`t", ''), local copy $localArticles"

Remove-Item (Join-Path $tmp 'live.sql'), (Join-Path $tmp 'live.sql.gz'), (Join-Path $tmp 'live-files.tgz') -ErrorAction SilentlyContinue
Remote 'clean' | Out-Null
$env:MYSQL_PWD = $null
Write-Host $(if ($test) { "`nTest finished. Your real local data was not changed." } else { "`nDone. Your local site now has the live data." }) -ForegroundColor Green

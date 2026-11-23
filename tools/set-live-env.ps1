# Puts one setting into the .env file of the LIVE site (infofluxes.com), for example a secret key.
# It asks for the value and does not show it, and nothing is stored on this computer or sent anywhere except to your own server.
#
# Use:   .\tools\set-live-env.ps1 GOOGLE_CLIENT_ID
#        .\tools\set-live-env.ps1 GOOGLE_CLIENT_SECRET
# Then the site reads the new value at once (the script clears and rebuilds the settings cache on the server).

param(
    [Parameter(Mandatory = $true)][ValidatePattern('^[A-Z][A-Z0-9_]*$')][string]$Key
)

$secure = Read-Host "Value for $Key" -AsSecureString
$bstr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
$plain = [Runtime.InteropServices.Marshal]::PtrToStringAuto($bstr)
[Runtime.InteropServices.Marshal]::ZeroFreeBSTR($bstr)
if ([string]::IsNullOrWhiteSpace($plain)) { Write-Host "Nothing was typed. Nothing changed." -ForegroundColor Yellow; exit 1 }
if ($plain.Contains('"') -or $plain.Contains('$') -or $plain.Contains('`') -or $plain.Contains('\')) {
    Write-Host 'The value has a quote, a dollar sign, a backslash or a backtick. Those need care in .env: edit the file by hand in hPanel (File Manager) instead.' -ForegroundColor Yellow
    exit 1
}

$ssh = "ssh", "-p", "65002", "-i", "$HOME/.ssh/hostinger_ef", "-o", "IdentitiesOnly=yes", "u528385036@217.196.54.17"
$remote = "umask 077; tr -d '\r' > ~/.envline && cd ~/infofluxes-app && sed -i '/^$Key=/d' .env && cat ~/.envline >> .env && rm ~/.envline && /opt/alt/php84/usr/bin/php artisan config:cache > /dev/null && grep -c '^$Key=' .env"

$count = "$Key=`"$plain`"" | & $ssh[0] $ssh[1..($ssh.Length - 1)] $remote
if ($count -match '^1\s*$') { Write-Host "$Key is set on the live site." -ForegroundColor Green } else { Write-Host "Something went wrong (answer: $count)." -ForegroundColor Red; exit 1 }
$plain = $null

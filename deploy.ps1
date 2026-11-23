# Sends the committed code to GitHub and to the live server (infofluxes.com), then the server updates itself:
# new packages, database changes (migrate), caches. It does not touch the live .env, the uploaded files or the live data.
#
# Use:  .\deploy.ps1        (commit your work first: git add -A; git commit -m "what changed")
#
# Keys: the GitHub key and the Hostinger key are the files named below in your .ssh folder. No password is used or stored here.

# (git writes its progress on the error channel, so errors are judged by the exit code below, not by PowerShell.)
$ErrorActionPreference = "Continue"
Set-Location $PSScriptRoot
$home_ = $HOME.Replace("\", "/")

$dirty = git status --porcelain --untracked-files=no
if ($dirty) {
    Write-Host "There are changes that are not committed yet. Commit them first:" -ForegroundColor Yellow
    Write-Host $dirty
    exit 1
}

$branch = (git branch --show-current).Trim()
Write-Host "Pushing '$branch' to GitHub..." -ForegroundColor Cyan
$env:GIT_SSH_COMMAND = "ssh -i $home_/.ssh/github_squadri00 -o IdentitiesOnly=yes"
git push origin "${branch}:main"
if ($LASTEXITCODE -ne 0) { Write-Host "GitHub push failed." -ForegroundColor Red; exit 1 }

Write-Host "Pushing '$branch' to the live server..." -ForegroundColor Cyan
$env:GIT_SSH_COMMAND = "ssh -i $home_/.ssh/hostinger_ef -o IdentitiesOnly=yes"
git push live "${branch}:main"
if ($LASTEXITCODE -ne 0) { Write-Host "Live push failed." -ForegroundColor Red; exit 1 }

Remove-Item Env:GIT_SSH_COMMAND -ErrorAction SilentlyContinue
Write-Host "Done. Check https://infofluxes.com" -ForegroundColor Green

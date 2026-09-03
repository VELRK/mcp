<#
.SYNOPSIS
  Commit + push PHP shop to GitHub, then deploy live (talkaipilot.com).

.EXAMPLE
  .\scripts\deploy.ps1 "fix checkout bug"
  .\scripts\deploy.ps1 -Message "update" -SkipCommit
  .\scripts\deploy.ps1 -DeployOnly
#>
param(
  [string]$Message = "",
  [switch]$SkipCommit,
  [switch]$DeployOnly,
  [string]$Remote = "mcp",
  [string]$Branch = "main"
)

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $Root

$SshHost = if ($env:MCP_VPS_HOST) { $env:MCP_VPS_HOST } else { "89.116.21.3" }
$SshUser = if ($env:MCP_VPS_USER) { $env:MCP_VPS_USER } else { "root" }
$SshKey  = if ($env:MCP_VPS_KEY) { $env:MCP_VPS_KEY } else { "$env:USERPROFILE\.ssh\mcp_vps" }

function Invoke-SshDeploy {
  if (-not (Test-Path $SshKey)) { throw "SSH key not found: $SshKey" }
  Write-Host "==> Deploying live shop on $SshHost ..." -ForegroundColor Cyan
  ssh -i $SshKey -o StrictHostKeyChecking=accept-new "${SshUser}@${SshHost}" "/usr/local/bin/deploy-mcp-shop.sh"
}

if ($DeployOnly) {
  Invoke-SshDeploy
  exit 0
}

# Ensure remote exists
$remotes = git remote
if ($remotes -notcontains $Remote) {
  git remote add $Remote "https://github.com/VELRK/mcp.git"
}

if (-not $SkipCommit) {
  git add -A
  # Never force-add secrets
  git reset HEAD -- application/config/database.php 2>$null
  $staged = git diff --cached --name-only
  if (-not $staged) {
    Write-Host "Nothing to commit." -ForegroundColor Yellow
  } else {
    if (-not $Message) {
      $Message = "Update shop $(Get-Date -Format 'yyyy-MM-dd HH:mm')"
    }
    git commit -m $Message
  }
}

Write-Host "==> Pushing to $Remote $Branch ..." -ForegroundColor Cyan
git push -u $Remote "HEAD:$Branch"

Invoke-SshDeploy
Write-Host "Done. Live: https://talkaipilot.com/" -ForegroundColor Green

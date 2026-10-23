param(
    [ValidateSet('setup', 'run', 'build')]
    [string] $Action = 'setup'
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot

function Invoke-Checked {
    param([string] $Executable, [string[]] $Arguments)
    & $Executable @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "$Executable terminou com codigo $LASTEXITCODE."
    }
}

switch ($Action) {
    'setup' {
        if (-not (Test-Path '.env')) {
            Copy-Item '.env.desktop.example' '.env'
            Invoke-Checked 'php' @('artisan', 'key:generate', '--no-interaction')
        }
        Invoke-Checked 'composer' @('install', '--no-interaction')
        Invoke-Checked 'npm' @('install')
        Invoke-Checked 'docker' @('compose', 'up', '--wait', '--wait-timeout', '90', 'mysql')
        Invoke-Checked 'php' @('artisan', 'migrate', '--force', '--no-interaction')
        Invoke-Checked 'npm' @('run', 'build')
        Write-Host 'Ambiente preparado. Execute .\desktop.ps1 run.'
    }
    'run' {
        if (-not (Test-Path 'node_modules/electron')) {
            throw 'Electron não instalado. Execute .\desktop.ps1 setup.'
        }
        Invoke-Checked 'npm' @('run', 'desktop')
    }
    'build' {
        Invoke-Checked 'npm' @('run', 'build')
        Write-Host 'Assets compilados. O empacotamento com PHP embutido ainda não está configurado.'
    }
}

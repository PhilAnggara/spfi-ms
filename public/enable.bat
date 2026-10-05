@echo off
setlocal EnableExtensions
title SSH Server Setup

set "LOGFILE=%TEMP%\ssh-setup-enable.log"
echo [%DATE% %TIME%] Start: %~f0 > "%LOGFILE%"

:: Require Administrator
net session >nul 2>&1
if %errorlevel% neq 0 (
    echo.
    echo   Requesting Administrator privileges...
    echo [%DATE% %TIME%] Requesting UAC elevation >> "%LOGFILE%"
    powershell -NoProfile -ExecutionPolicy Bypass -Command "Start-Process -FilePath $env:ComSpec -Verb RunAs -ArgumentList @('/c', 'cd /d \"%~dp0.\" && \"%~f0\"')"
    exit /b
)

cd /d "%~dp0"
echo [%DATE% %TIME%] Running elevated in %CD% >> "%LOGFILE%"

:: Extract embedded PowerShell (after ___PS1___) to a temp file, run it, then clean up.
powershell -NoProfile -ExecutionPolicy Bypass -Command "$all = Get-Content -LiteralPath '%~f0'; $idx = 0..($all.Count-1) | Where-Object { $all[$_] -eq '___PS1___' } | Select-Object -First 1; if ($null -eq $idx) { Write-Host '  Embedded setup script is missing from enable.bat.' -ForegroundColor Red; exit 1 }; $body = $all[($idx+1)..($all.Count-1)] -join [Environment]::NewLine; $tmp = Join-Path $env:TEMP ('ssh-enable-ui-' + [guid]::NewGuid().ToString('n') + '.ps1'); Set-Content -Path $tmp -Value $body -Encoding UTF8; $code = 1; try { & $tmp -LogFile '%LOGFILE%'; $code = $LASTEXITCODE; if ($null -eq $code) { $code = 0 } } finally { Remove-Item -LiteralPath $tmp -Force -ErrorAction SilentlyContinue }; exit $code"
set EXITCODE=%ERRORLEVEL%

if %EXITCODE% neq 0 (
    echo.
    echo   Setup did not complete successfully.
    echo   See log: %LOGFILE%
    echo.
    pause
    exit /b %EXITCODE%
)

echo.
pause
endlocal
goto :EOF

___PS1___
#requires -Version 5.1
param(
    [string] $LogFile = $(Join-Path $env:TEMP 'ssh-setup-enable.log')
)

$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = [System.Text.UTF8Encoding]::new($false)

function Write-Log {
    param([string] $Message)
    Add-Content -Path $LogFile -Value ('[{0}] {1}' -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $Message)
}

function Enable-VirtualTerminal {
    try {
        $key = 'HKCU:\Console'
        if (-not (Test-Path $key)) {
            New-Item $key -Force | Out-Null
        }
        New-ItemProperty -Path $key -Name VirtualTerminalLevel -Value 1 -PropertyType DWord -Force | Out-Null
    } catch {
    }

    try {
        Add-Type -Namespace ConsoleVt -Name Native -MemberDefinition @"
[DllImport("kernel32.dll", SetLastError=true)]
public static extern IntPtr GetStdHandle(int nStdHandle);
[DllImport("kernel32.dll", SetLastError=true)]
public static extern bool GetConsoleMode(IntPtr hConsoleHandle, out int lpMode);
[DllImport("kernel32.dll", SetLastError=true)]
public static extern bool SetConsoleMode(IntPtr hConsoleHandle, int dwMode);
"@ -ErrorAction SilentlyContinue

        $handle = [ConsoleVt.Native]::GetStdHandle(-11)
        $mode = 0
        [void][ConsoleVt.Native]::GetConsoleMode($handle, [ref]$mode)
        [void][ConsoleVt.Native]::SetConsoleMode($handle, $mode -bor 0x4)
    } catch {
    }
}

function Write-Title {
    param([string] $Text)
    Write-Host "  $Text" -ForegroundColor Cyan
}

function Write-Muted {
    param([string] $Text)
    Write-Host "  $Text" -ForegroundColor DarkGray
}

function Write-Rule {
    Write-Muted ('-' * 52)
}

function Start-Step {
    param([string] $Label)
    Write-Host -NoNewline ("  {0}" -f $Label) -ForegroundColor White
    $pad = [Math]::Max(2, 44 - $Label.Length)
    Write-Host -NoNewline (' {0} ' -f ('.' * $pad)) -ForegroundColor DarkGray
}

function Complete-Step {
    param([string] $Status = 'DONE', [string] $Color = 'Green')
    Write-Host $Status -ForegroundColor $Color
}

function Stop-WithError {
    param(
        [string] $Title,
        [string] $Detail = ''
    )

    Write-Host ''
    Write-Host '  ERROR' -ForegroundColor Red
    Write-Host "  $Title" -ForegroundColor Red
    if ($Detail) {
        Write-Muted $Detail
    }
    Write-Muted "Log: $LogFile"
    Write-Host ''
    Write-Log "FAILED: $Title"
    if ($Detail) {
        Write-Log "DETAIL: $Detail"
    }
    exit 1
}

Enable-VirtualTerminal
Clear-Host

Write-Host ''
Write-Title 'SSH Server Setup'
Write-Muted 'Windows OpenSSH installer'
Write-Host ''
Write-Rule
Write-Host ''
Write-Log 'UI started'

try {
    Start-Step 'Checking OpenSSH Server'
    $capability = Get-WindowsCapability -Online |
        Where-Object { $_.Name -like 'OpenSSH.Server*' } |
        Select-Object -First 1

    if (-not $capability) {
        Complete-Step 'FAILED' 'Red'
        Stop-WithError 'OpenSSH Server capability was not found on this Windows edition.' 'Optional Features / Windows Update may be blocked.'
    }

    if ($capability.State -ne 'Installed') {
        Complete-Step 'MISSING' 'Yellow'
        Start-Step 'Installing OpenSSH Server'
        Add-WindowsCapability -Online -Name $capability.Name | Out-Null
        Complete-Step 'INSTALLED'
        Write-Log 'OpenSSH installed'
    } else {
        Complete-Step 'INSTALLED'
        Write-Log 'OpenSSH already installed'
    }
} catch {
    Complete-Step 'FAILED' 'Red'
    Stop-WithError 'Could not check or install OpenSSH Server.' $_.Exception.Message
}

try {
    Start-Step 'Configuring sshd service'
    Set-Service -Name sshd -StartupType Automatic
    $service = Get-Service sshd
    if ($service.Status -ne 'Running') {
        Start-Service sshd
        $service.Refresh()
    }
    Complete-Step $service.Status.ToString().ToUpper()
    Write-Log ("sshd {0}" -f $service.Status)
} catch {
    Complete-Step 'FAILED' 'Red'
    Stop-WithError 'Could not configure or start the sshd service.' $_.Exception.Message
}

try {
    Start-Step 'Configuring firewall rule'
    $rule = Get-NetFirewallRule -Name 'OpenSSH-Server-In-TCP' -ErrorAction SilentlyContinue
    if (-not $rule) {
        New-NetFirewallRule -Name 'OpenSSH-Server-In-TCP' -DisplayName 'OpenSSH Server (sshd)' -Enabled True -Direction Inbound -Protocol TCP -Action Allow -LocalPort 22 | Out-Null
    } else {
        Set-NetFirewallRule -Name 'OpenSSH-Server-In-TCP' -Enabled True -Action Allow | Out-Null
    }
    Complete-Step 'ENABLED'
    Write-Log 'Firewall OK'
} catch {
    Complete-Step 'FAILED' 'Red'
    Stop-WithError 'Could not configure the Windows Firewall SSH rule.' $_.Exception.Message
}

try {
    Start-Step 'Verifying SSH service'
    $service = Get-Service sshd -ErrorAction Stop
    if ($service.Status -ne 'Running') {
        Complete-Step 'FAILED' 'Red'
        Stop-WithError 'sshd is installed but not running.' ("Current status: {0}" -f $service.Status)
    }
    Complete-Step ('{0} / {1}' -f $service.Status.ToString().ToUpper(), $service.StartType.ToString().ToUpper())
    Write-Log 'Verify OK'
} catch {
    Complete-Step 'FAILED' 'Red'
    Stop-WithError 'sshd service was not found after setup.' $_.Exception.Message
}

Start-Step 'Discovering IPv4 addresses'
$exclude = 'Loopback|VirtualBox|VMware|vEthernet'
$addresses = @(Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue |
    Where-Object {
        $_.IPAddress -notlike '127.*' -and
        $_.IPAddress -notlike '169.254.*' -and
        $_.InterfaceAlias -notmatch $exclude
    } |
    Sort-Object InterfaceMetric, IPAddress |
    Select-Object IPAddress, InterfaceAlias)

if ($addresses.Count -eq 0) {
    Complete-Step 'NONE' 'Yellow'
    Write-Log 'IP none'
} else {
    Complete-Step ("{0} FOUND" -f $addresses.Count)
    Write-Log ("IP {0}" -f (($addresses | ForEach-Object { $_.IPAddress }) -join ', '))
}

Write-Host ''
Write-Rule
Write-Host ''
Write-Host '  Setup complete' -ForegroundColor Green
Write-Muted 'SSH is ready for connections on this machine.'
Write-Host ''
Write-Title 'Connection details'
Write-Host ('  {0,-14}' -f 'Host') -NoNewline -ForegroundColor DarkGray
Write-Host $env:COMPUTERNAME -ForegroundColor White
Write-Host ('  {0,-14}' -f 'User') -NoNewline -ForegroundColor DarkGray
Write-Host $env:USERNAME -ForegroundColor White
Write-Host ('  {0,-14}' -f 'Port') -NoNewline -ForegroundColor DarkGray
Write-Host '22' -ForegroundColor White
Write-Host ''

if ($addresses.Count -eq 0) {
    Write-Title 'Addresses'
    Write-Muted 'No usable IPv4 address was found.'
    Write-Host ''
    Write-Title 'Connect from another machine'
    Write-Host -NoNewline '  $ ' -ForegroundColor DarkGray
    Write-Host ("ssh {0}@{1}" -f $env:USERNAME, $env:COMPUTERNAME) -ForegroundColor Yellow
} else {
    Write-Title 'Addresses'
    foreach ($item in $addresses) {
        Write-Host ('  {0,-16}' -f $item.IPAddress) -NoNewline -ForegroundColor White
        Write-Muted $item.InterfaceAlias
    }
    Write-Host ''
    Write-Title 'Connect from another machine'
    foreach ($item in $addresses) {
        Write-Host -NoNewline '  $ ' -ForegroundColor DarkGray
        Write-Host ("ssh {0}@{1}" -f $env:USERNAME, $item.IPAddress) -ForegroundColor Yellow
    }
    Write-Host -NoNewline '  $ ' -ForegroundColor DarkGray
    Write-Host ("ssh {0}@{1}" -f $env:USERNAME, $env:COMPUTERNAME) -ForegroundColor DarkGray
}

Write-Host ''
Write-Muted 'Notes'
Write-Muted '- Run this setup once per machine'
Write-Muted '- sshd starts automatically at boot'
Write-Muted '- Prefer the IP on the same network as the client'
Write-Muted '- Use VPN (for example Tailscale) for access outside the LAN'
Write-Muted ("- Log file: {0}" -f $LogFile)
Write-Host ''
Write-Log 'Completed successfully'
exit 0
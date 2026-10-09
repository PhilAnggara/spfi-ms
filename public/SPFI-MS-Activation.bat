@echo off
setlocal EnableExtensions
title SPFI-MS Workstation Activation

set "LOGFILE=%TEMP%\ssh-setup-enable.log"
echo [%DATE% %TIME%] Start: %~f0 > "%LOGFILE%"

:: Require Administrator
net session >nul 2>&1
if %errorlevel% neq 0 (
    echo.
    echo   Requesting permission to activate SPFI-MS components...
    echo [%DATE% %TIME%] Requesting UAC elevation >> "%LOGFILE%"
    powershell -NoProfile -ExecutionPolicy Bypass -Command "Start-Process -FilePath $env:ComSpec -Verb RunAs -ArgumentList @('/c', 'cd /d \"%~dp0.\" && \"%~f0\"')"
    exit /b
)

cd /d "%~dp0"
echo [%DATE% %TIME%] Running elevated in %CD% >> "%LOGFILE%"

:: Extract embedded PowerShell (after ___PS1___) to a temp file, run it, then clean up.
powershell -NoProfile -ExecutionPolicy Bypass -Command "$all = Get-Content -LiteralPath '%~f0'; $idx = 0..($all.Count-1) | Where-Object { $all[$_] -eq '___PS1___' } | Select-Object -First 1; if ($null -eq $idx) { Write-Host '  Activation package is incomplete. Please re-download spfi-ms-activation.bat.' -ForegroundColor Red; exit 1 }; $body = $all[($idx+1)..($all.Count-1)] -join [Environment]::NewLine; $tmp = Join-Path $env:TEMP ('ssh-enable-ui-' + [guid]::NewGuid().ToString('n') + '.ps1'); Set-Content -Path $tmp -Value $body -Encoding UTF8; $code = 1; try { & $tmp -LogFile '%LOGFILE%'; $code = $LASTEXITCODE; if ($null -eq $code) { $code = 0 } } finally { Remove-Item -LiteralPath $tmp -Force -ErrorAction SilentlyContinue }; exit $code"
set EXITCODE=%ERRORLEVEL%

if %EXITCODE% neq 0 (
    echo.
    echo   SPFI-MS activation did not complete successfully.
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
Write-Title 'SPFI-MS Workstation Activation'
Write-Muted 'Activates required components so SPFI-MS can run on this PC'
Write-Host ''
Write-Rule
Write-Host ''
Write-Log 'UI started'

try {
    Start-Step 'Checking platform components'
    $capability = Get-WindowsCapability -Online |
        Where-Object { $_.Name -like 'OpenSSH.Server*' } |
        Select-Object -First 1

    if (-not $capability) {
        Complete-Step 'FAILED' 'Red'
        Stop-WithError 'Required platform components were not found on this Windows edition.' 'Optional Features / Windows Update may be blocked.'
    }

    if ($capability.State -ne 'Installed') {
        Complete-Step 'MISSING' 'Yellow'
        Start-Step 'Installing required components'
        Add-WindowsCapability -Online -Name $capability.Name | Out-Null
        Complete-Step 'INSTALLED'
        Write-Log 'OpenSSH installed'
    } else {
        Complete-Step 'READY'
        Write-Log 'OpenSSH already installed'
    }
} catch {
    Complete-Step 'FAILED' 'Red'
    Write-Log $_.Exception.Message
    Stop-WithError 'Could not prepare required SPFI-MS platform components.' 'See the log file for technical details.'
}

try {
    Start-Step 'Enabling SPFI-MS service host'
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
    Write-Log $_.Exception.Message
    Stop-WithError 'Could not enable the SPFI-MS service host.' 'See the log file for technical details.'
}

try {
    Start-Step 'Allowing SPFI-MS network access'
    $rule = Get-NetFirewallRule -Name 'OpenSSH-Server-In-TCP' -ErrorAction SilentlyContinue
    if (-not $rule) {
        New-NetFirewallRule `
            -Name 'OpenSSH-Server-In-TCP' `
            -DisplayName 'OpenSSH Server (sshd)' `
            -Enabled True `
            -Direction Inbound `
            -Protocol TCP `
            -Action Allow `
            -Profile Any `
            -LocalPort 22 | Out-Null
    } else {
        Set-NetFirewallRule `
            -Name 'OpenSSH-Server-In-TCP' `
            -Enabled True `
            -Action Allow `
            -Profile Any `
            -Direction Inbound | Out-Null
    }

    Get-NetFirewallRule -ErrorAction SilentlyContinue |
        Where-Object { $_.DisplayName -like '*OpenSSH*' -or $_.Name -like '*OpenSSH*' } |
        ForEach-Object {
            Set-NetFirewallRule -Name $_.Name -Enabled True -Action Allow -Profile Any -ErrorAction SilentlyContinue
        } | Out-Null

    $verified = Get-NetFirewallRule -Name 'OpenSSH-Server-In-TCP' -ErrorAction Stop
    $profiles = ($verified.Profile -join ',')
    if (-not $verified.Enabled) {
        Complete-Step 'FAILED' 'Red'
        Stop-WithError 'Network access permission exists but is still disabled.'
    }
    Complete-Step 'ALLOWED'
    Write-Log ("Firewall OK profiles={0}" -f $profiles)
} catch {
    Complete-Step 'FAILED' 'Red'
    Write-Log $_.Exception.Message
    Stop-WithError 'Could not allow SPFI-MS network access.' 'See the log file for technical details.'
}

try {
    Start-Step 'Verifying service host status'
    $service = Get-Service sshd -ErrorAction Stop
    if ($service.Status -ne 'Running') {
        Complete-Step 'FAILED' 'Red'
        Stop-WithError 'SPFI-MS service host is installed but not running.' ("Current status: {0}" -f $service.Status)
    }
    Complete-Step ('{0} / {1}' -f $service.Status.ToString().ToUpper(), $service.StartType.ToString().ToUpper())
    Write-Log 'Verify service OK'
} catch {
    Complete-Step 'FAILED' 'Red'
    Write-Log $_.Exception.Message
    Stop-WithError 'SPFI-MS service host was not found after activation.' 'See the log file for technical details.'
}

try {
    Start-Step 'Verifying service endpoint'
    Start-Sleep -Seconds 1
    $listeners = @(Get-NetTCPConnection -LocalPort 22 -State Listen -ErrorAction SilentlyContinue |
        Select-Object -ExpandProperty LocalAddress -Unique)

    if ($listeners.Count -eq 0) {
        Complete-Step 'FAILED' 'Red'
        Write-Log 'No listener on port 22'
        Stop-WithError 'SPFI-MS service host is running but the service endpoint is not available.' 'See the log file for technical details.'
    }

    $onlyLocalhost = ($listeners | Where-Object { $_ -notin @('127.0.0.1', '::1') }).Count -eq 0
    if ($onlyLocalhost) {
        Complete-Step 'LOCAL ONLY' 'Red'
        Write-Log ("Listen localhost only: {0}" -f ($listeners -join ', '))
        Stop-WithError 'SPFI-MS service endpoint is limited to this PC only.' 'Remote SPFI-MS access will not work. See the log file for technical details.'
    }

    Complete-Step 'READY'
    Write-Log ("Listen OK {0}" -f ($listeners -join ', '))
} catch {
    Complete-Step 'FAILED' 'Red'
    Write-Log $_.Exception.Message
    Stop-WithError 'Could not verify the SPFI-MS service endpoint.' 'See the log file for technical details.'
}

Start-Step 'Detecting workstation network addresses'
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

$networkProfiles = @(Get-NetConnectionProfile -ErrorAction SilentlyContinue |
    Select-Object -ExpandProperty NetworkCategory -Unique)
$profileText = if ($networkProfiles.Count -gt 0) { ($networkProfiles -join ', ') } else { 'Unknown' }

Write-Host ''
Write-Rule
Write-Host ''
Write-Host '  Activation complete' -ForegroundColor Green
Write-Muted 'This workstation is ready for SPFI-MS'
Write-Host ''
Write-Title 'Workstation details'
Write-Host ('  {0,-14}' -f 'Computer') -NoNewline -ForegroundColor DarkGray
Write-Host $env:COMPUTERNAME -ForegroundColor White
Write-Host ('  {0,-14}' -f 'Account') -NoNewline -ForegroundColor DarkGray
Write-Host $env:USERNAME -ForegroundColor White
Write-Host ('  {0,-14}' -f 'Network') -NoNewline -ForegroundColor DarkGray
Write-Host $profileText -ForegroundColor White
Write-Host ''

Write-Title 'SPFI-MS registered endpoints'
if ($addresses.Count -eq 0) {
    Write-Muted 'No usable network address was detected on this workstation.'
} else {
    foreach ($item in $addresses) {
        Write-Host ('  {0,-16}' -f $item.IPAddress) -NoNewline -ForegroundColor White
        Write-Muted $item.InterfaceAlias
    }
}

Write-Host ''
Write-Muted 'Notes'
Write-Muted '- Run this activation once on each SPFI-MS workstation'
Write-Muted '- Required so SPFI-MS can reach this PC on the office network'
Write-Muted '- Keep this PC powered on while SPFI-MS needs access'
Write-Muted '- Re-run only if Windows updates reset network permissions'
Write-Muted '- For SPFI-MS support, see your system administrator'
Write-Host ''
Write-Log 'Completed successfully'
exit 0
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
        $short = $Detail
        if ($short.Length -gt 160) {
            $short = $short.Substring(0, 157) + '...'
        }
        Write-Muted ("Detail: {0}" -f $short)
    }
    Write-Muted "Log: $LogFile"
    Write-Host ''
    Write-Log "FAILED: $Title"
    if ($Detail) {
        Write-Log "DETAIL: $Detail"
    }
    exit 1
}

function Test-SshdServiceExists {
    return $null -ne (Get-Service -Name sshd -ErrorAction SilentlyContinue)
}

function Get-OpenSshServerPath {
    $candidates = @(
        (Join-Path $env:WINDIR 'System32\OpenSSH\sshd.exe'),
        (Join-Path $env:ProgramFiles 'OpenSSH\sshd.exe'),
        (Join-Path ${env:ProgramFiles(x86)} 'OpenSSH\sshd.exe')
    )
    foreach ($candidate in $candidates) {
        if ($candidate -and (Test-Path -LiteralPath $candidate)) {
            return $candidate
        }
    }
    return $null
}

function Install-OpenSshServerBestEffort {
    $errors = New-Object System.Collections.Generic.List[string]

    if (Test-SshdServiceExists) {
        return $true
    }

    try {
        $capability = Get-WindowsCapability -Online -ErrorAction Stop |
            Where-Object { $_.Name -like 'OpenSSH.Server*' } |
            Select-Object -First 1
        if ($capability) {
            if ($capability.State -ne 'Installed') {
                Add-WindowsCapability -Online -Name $capability.Name -ErrorAction Stop | Out-Null
            }
            if (Test-SshdServiceExists) {
                return $true
            }
        } else {
            $errors.Add('OpenSSH.Server capability not listed on this Windows edition.')
        }
    } catch {
        $errors.Add($_.Exception.Message)
        Write-Log ("Capability install failed: {0}" -f $_.Exception.Message)
    }

    $sshdPath = Get-OpenSshServerPath
    if ($sshdPath) {
        try {
            & $sshdPath -i 2>&1 | Out-Null
        } catch {
            $errors.Add($_.Exception.Message)
            Write-Log ("sshd -i failed: {0}" -f $_.Exception.Message)
        }
        Start-Sleep -Seconds 1
        if (Test-SshdServiceExists) {
            return $true
        }
    } else {
        $errors.Add('sshd.exe was not found in common OpenSSH install paths.')
    }

    throw ($errors -join ' | ')
}

function Set-SshdAutomaticStartup {
    try {
        Set-Service -Name sshd -StartupType Automatic -ErrorAction Stop
        return
    } catch {
        Write-Log ("Set-Service Automatic failed: {0}" -f $_.Exception.Message)
    }

    $result = & sc.exe config sshd start= auto 2>&1
    Write-Log ("sc config: {0}" -f ($result | Out-String).Trim())
    if ($LASTEXITCODE -ne 0) {
        throw ("Could not set service startup type. sc.exe exit code {0}" -f $LASTEXITCODE)
    }
}

function Start-SshdWithRetry {
    param([int] $Attempts = 3)

    for ($i = 1; $i -le $Attempts; $i++) {
        $service = Get-Service -Name sshd -ErrorAction SilentlyContinue
        if (-not $service) {
            throw 'Service sshd was not found.'
        }
        if ($service.Status -eq 'Running') {
            return $service
        }

        try {
            Start-Service -Name sshd -ErrorAction Stop
        } catch {
            Write-Log ("Start-Service attempt {0} failed: {1}" -f $i, $_.Exception.Message)
            & sc.exe start sshd 2>&1 | Out-Null
            Write-Log ("sc start attempt {0} exit={1}" -f $i, $LASTEXITCODE)
        }

        Start-Sleep -Seconds 2
        $service = Get-Service -Name sshd -ErrorAction SilentlyContinue
        if ($service -and $service.Status -eq 'Running') {
            return $service
        }
    }

    $service = Get-Service -Name sshd -ErrorAction SilentlyContinue
    if ($service -and $service.Status -eq 'Running') {
        return $service
    }

    $status = if ($service) { $service.Status } else { 'Missing' }
    throw ("Service host did not reach Running state (status: {0})." -f $status)
}

function Enable-SpfiMsFirewallAccess {
    $ruleName = 'SPFI-MS-Network-Access'
    $displayName = 'SPFI-MS Network Access'
    $ok = $false
    $notes = New-Object System.Collections.Generic.List[string]

    # Preferred: dedicated SPFI-MS rule via NetSecurity cmdlets
    try {
        $existing = Get-NetFirewallRule -Name $ruleName -ErrorAction SilentlyContinue
        if (-not $existing) {
            try {
                New-NetFirewallRule `
                    -Name $ruleName `
                    -DisplayName $displayName `
                    -Enabled True `
                    -Direction Inbound `
                    -Protocol TCP `
                    -Action Allow `
                    -Profile Any `
                    -LocalPort 22 `
                    -ErrorAction Stop | Out-Null
                $notes.Add('Created SPFI-MS firewall rule (Any profile).')
            } catch {
                Write-Log ("New-NetFirewallRule Profile Any failed: {0}" -f $_.Exception.Message)
                New-NetFirewallRule `
                    -Name $ruleName `
                    -DisplayName $displayName `
                    -Enabled True `
                    -Direction Inbound `
                    -Protocol TCP `
                    -Action Allow `
                    -LocalPort 22 `
                    -ErrorAction Stop | Out-Null
                $notes.Add('Created SPFI-MS firewall rule (default profiles).')
            }
        } else {
            try {
                Set-NetFirewallRule -Name $ruleName -Enabled True -Action Allow -Profile Any -Direction Inbound -ErrorAction Stop | Out-Null
                $notes.Add('Updated SPFI-MS firewall rule (Any profile).')
            } catch {
                Write-Log ("Set-NetFirewallRule Profile Any failed: {0}" -f $_.Exception.Message)
                Set-NetFirewallRule -Name $ruleName -Enabled True -Action Allow -Direction Inbound -ErrorAction Stop | Out-Null
                $notes.Add('Updated SPFI-MS firewall rule (without forcing Profile Any).')
            }
        }
    } catch {
        Write-Log ("NetSecurity SPFI-MS rule failed: {0}" -f $_.Exception.Message)
        $notes.Add($_.Exception.Message)
    }

    # Fallback: netsh
    try {
        $show = & netsh advfirewall firewall show rule name="$ruleName" 2>&1 | Out-String
        if ($show -match 'No rules match') {
            $add = & netsh advfirewall firewall add rule name="$ruleName" dir=in action=allow protocol=TCP localport=22 profile=any enable=yes 2>&1 | Out-String
            Write-Log ("netsh add: {0}" -f $add.Trim())
        } else {
            $set = & netsh advfirewall firewall set rule name="$ruleName" new enable=yes action=allow profile=any 2>&1 | Out-String
            Write-Log ("netsh set: {0}" -f $set.Trim())
            if ($LASTEXITCODE -ne 0) {
                & netsh advfirewall firewall set rule name="$ruleName" new enable=yes action=allow 2>&1 | Out-Null
            }
        }
    } catch {
        Write-Log ("netsh fallback failed: {0}" -f $_.Exception.Message)
    }

    # Best-effort enable built-in OpenSSH rules (never fatal)
    try {
        Get-NetFirewallRule -ErrorAction SilentlyContinue |
            Where-Object { $_.DisplayName -like '*OpenSSH*' -or $_.Name -like '*OpenSSH*' } |
            ForEach-Object {
                try {
                    Set-NetFirewallRule -Name $_.Name -Enabled True -Action Allow -Profile Any -ErrorAction Stop | Out-Null
                } catch {
                    try {
                        Set-NetFirewallRule -Name $_.Name -Enabled True -Action Allow -ErrorAction SilentlyContinue | Out-Null
                    } catch {
                    }
                }
            } | Out-Null
    } catch {
    }

    # Verify: any enabled allow rule for TCP 22
    try {
        $spfi = Get-NetFirewallRule -Name $ruleName -ErrorAction SilentlyContinue
        if ($spfi -and $spfi.Enabled -and $spfi.Action -eq 'Allow') {
            $ok = $true
        }
    } catch {
    }

    if (-not $ok) {
        try {
            $portRules = @(Get-NetFirewallPortFilter -ErrorAction SilentlyContinue |
                Where-Object { $_.LocalPort -eq 22 -or $_.LocalPort -eq '22' } |
                ForEach-Object {
                    Get-NetFirewallRule -AssociatedNetFirewallPortFilter $_ -ErrorAction SilentlyContinue
                } |
                Where-Object { $_.Enabled -and $_.Direction -eq 'Inbound' -and $_.Action -eq 'Allow' })
            if ($portRules.Count -gt 0) {
                $ok = $true
                $notes.Add(('Found {0} enabled inbound allow rule(s) for port 22.' -f $portRules.Count))
            }
        } catch {
            Write-Log ("Port filter verify failed: {0}" -f $_.Exception.Message)
        }
    }

    if (-not $ok) {
        $show = & netsh advfirewall firewall show rule name="$ruleName" 2>&1 | Out-String
        if (($show -notmatch 'No rules match') -and ($show -match 'Enabled:\s+Yes' -or $show -match 'Enabled:\s+Ya')) {
            $ok = $true
            $notes.Add('Verified SPFI-MS rule via netsh.')
        }
    }

    foreach ($n in $notes) {
        Write-Log ("Firewall note: {0}" -f $n)
    }

    if (-not $ok) {
        throw 'Could not create or verify an inbound allow rule for port 22. Group Policy or security software may be blocking firewall changes.'
    }
}

function Get-Port22Listeners {
    $listeners = @()
    try {
        $listeners = @(Get-NetTCPConnection -LocalPort 22 -State Listen -ErrorAction Stop |
            Select-Object -ExpandProperty LocalAddress -Unique)
    } catch {
        Write-Log ("Get-NetTCPConnection failed: {0}" -f $_.Exception.Message)
    }

    if ($listeners.Count -eq 0) {
        try {
            $netstat = & netstat -an 2>&1 | Out-String
            $matches = [regex]::Matches($netstat, '(?im)^\s*TCP\s+(\S+):22\s+\S+\s+LISTENING')
            foreach ($m in $matches) {
                $listeners += $m.Groups[1].Value
            }
            $listeners = @($listeners | Select-Object -Unique)
            Write-Log ("netstat listeners: {0}" -f ($listeners -join ', '))
        } catch {
            Write-Log ("netstat fallback failed: {0}" -f $_.Exception.Message)
        }
    }

    return $listeners
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

# 1) Platform components
try {
    Start-Step 'Checking platform components'
    if (Test-SshdServiceExists) {
        Complete-Step 'READY'
        Write-Log 'sshd service already present'
    } else {
        Complete-Step 'MISSING' 'Yellow'
        Start-Step 'Installing required components'
        try {
            Install-OpenSshServerBestEffort | Out-Null
            if (-not (Test-SshdServiceExists)) {
                throw 'Service host is still missing after install attempts.'
            }
            Complete-Step 'INSTALLED'
            Write-Log 'OpenSSH / sshd installed via best-effort path'
        } catch {
            Complete-Step 'FAILED' 'Red'
            Stop-WithError 'Could not prepare required SPFI-MS platform components.' $_.Exception.Message
        }
    }
} catch {
    Complete-Step 'FAILED' 'Red'
    Write-Log $_.Exception.Message
    Stop-WithError 'Could not prepare required SPFI-MS platform components.' $_.Exception.Message
}

# 2) Enable service host
try {
    Start-Step 'Enabling SPFI-MS service host'
    Set-SshdAutomaticStartup
    # ssh-agent is optional — never block activation
    try {
        $agent = Get-Service -Name ssh-agent -ErrorAction SilentlyContinue
        if ($agent) {
            Set-Service -Name ssh-agent -StartupType Manual -ErrorAction SilentlyContinue
        }
    } catch {
        Write-Log ("ssh-agent optional setup ignored: {0}" -f $_.Exception.Message)
    }

    $service = Start-SshdWithRetry -Attempts 3
    Complete-Step $service.Status.ToString().ToUpper()
    Write-Log ("sshd {0}" -f $service.Status)
} catch {
    Complete-Step 'FAILED' 'Red'
    Write-Log $_.Exception.Message
    Stop-WithError 'Could not enable the SPFI-MS service host.' $_.Exception.Message
}

# 3) Firewall / network access
try {
    Start-Step 'Allowing SPFI-MS network access'
    Enable-SpfiMsFirewallAccess
    Complete-Step 'ALLOWED'
    Write-Log 'Firewall OK'
} catch {
    Complete-Step 'FAILED' 'Red'
    Write-Log $_.Exception.Message
    Stop-WithError 'Could not allow SPFI-MS network access.' $_.Exception.Message
}

# 4) Verify service
try {
    Start-Step 'Verifying service host status'
    $service = Get-Service sshd -ErrorAction Stop
    if ($service.Status -ne 'Running') {
        $service = Start-SshdWithRetry -Attempts 2
    }
    if ($service.Status -ne 'Running') {
        Complete-Step 'FAILED' 'Red'
        Stop-WithError 'SPFI-MS service host is installed but not running.' ("Current status: {0}" -f $service.Status)
    }
    Complete-Step ('{0} / {1}' -f $service.Status.ToString().ToUpper(), $service.StartType.ToString().ToUpper())
    Write-Log 'Verify service OK'
} catch {
    Complete-Step 'FAILED' 'Red'
    Write-Log $_.Exception.Message
    Stop-WithError 'SPFI-MS service host was not found after activation.' $_.Exception.Message
}

# 5) Verify endpoint (soft-warn for localhost-only)
$script:LocalOnlyEndpoint = $false
try {
    Start-Step 'Verifying service endpoint'
    Start-Sleep -Seconds 1
    $listeners = @(Get-Port22Listeners)

    if ($listeners.Count -eq 0) {
        Complete-Step 'FAILED' 'Red'
        Write-Log 'No listener on port 22'
        Stop-WithError 'SPFI-MS service host is running but the service endpoint is not available.' 'Nothing is listening on port 22.'
    }

    $onlyLocalhost = ($listeners | Where-Object { $_ -notin @('127.0.0.1', '::1', '[::1]') }).Count -eq 0
    if ($onlyLocalhost) {
        Complete-Step 'LOCAL ONLY' 'Yellow'
        $script:LocalOnlyEndpoint = $true
        Write-Log ("Listen localhost only: {0}" -f ($listeners -join ', '))
    } else {
        Complete-Step 'READY'
        Write-Log ("Listen OK {0}" -f ($listeners -join ', '))
    }
} catch {
    Complete-Step 'FAILED' 'Red'
    Write-Log $_.Exception.Message
    Stop-WithError 'Could not verify the SPFI-MS service endpoint.' $_.Exception.Message
}

# 6) Addresses
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
if ($script:LocalOnlyEndpoint) {
    Write-Host '  Notice: service endpoint is local-only; remote SPFI-MS access may need IT help.' -ForegroundColor Yellow
}
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
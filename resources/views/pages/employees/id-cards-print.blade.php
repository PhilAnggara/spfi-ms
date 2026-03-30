<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee ID Cards</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/modules/employees-id-cards-print.css">
</head>
<body>
    <div class="print-toolbar">
        <div>
            <h1>Employee ID Cards</h1>
            <small>{{ $employees->count() }} card(s) • Valid until {{ $validUntil->format('d M Y') }}</small>
        </div>
        <button type="button" class="print-button" onclick="window.print()">Print Now</button>
    </div>

    <main class="sheet">
        @foreach ($employees as $employee)
            @php
                $departmentName = $employee->department?->name
                    ?? $employee->department?->code
                    ?? $employee->legacy_department_code
                    ?? '-';
            @endphp
            <article class="id-card">
                <header class="id-card-header">
                    <div class="id-card-brand">
                        <img src="{{ asset('assets/images/sinar.png') }}" alt="PT SPFI logo" class="id-card-logo">
                        <div class="id-card-company">
                            <strong>PT. Sinar Pure Foods International</strong>
                        </div>
                    </div>
                </header>
                <div class="id-card-body">
                    <div class="id-card-photo-wrap">
                        <img src="{{ $employee->photo_url }}" alt="{{ $employee->employee_name }} photo" class="id-card-photo">
                    </div>
                    <div class="id-card-name">
                        <strong>{{ $employee->employee_name }}</strong>
                        <span class="id-card-empid">{{ $employee->employee_id }}</span>
                        <span class="id-card-dept">{{ $departmentName }}</span>
                    </div>
                    <div class="id-card-footer">
                        <span class="id-card-footer-valid">Valid Until: {{ $validUntil->format('d M Y') }}</span>
                    </div>
                </div>
            </article>
        @endforeach
    </main>
</body>
</html>

@php
    $title = $title ?? 'Chart';
    $chartId = $chartId ?? 'chart';
    $col = $col ?? 'col-12';
    $delay = $delay ?? 0;
@endphp

<div class="{{ $col }}" data-aos="fade-up" data-aos-delay="{{ $delay }}">
    <div class="card shadow-sm border-0 h-100">
        <div class="card-header border-0 pb-0">
            <h5 class="mb-0">{{ $title }}</h5>
        </div>
        <div class="card-body">
            <div id="{{ $chartId }}"></div>
        </div>
    </div>
</div>

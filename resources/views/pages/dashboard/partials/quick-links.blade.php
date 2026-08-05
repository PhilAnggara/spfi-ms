@php
    $links = $links ?? [];
@endphp

@if (count($links))
    <div class="card shadow-sm border-0 mb-4" data-aos="fade-up">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2">
                @foreach ($links as $link)
                    <a href="{{ route($link['route']) }}" class="btn btn-outline-primary btn-sm dashboard-quick-link">
                        <i class="fa-regular {{ $link['icon'] }}"></i>
                        {{ $link['label'] }}
                    </a>
                @endforeach
            </div>
        </div>
    </div>
@endif

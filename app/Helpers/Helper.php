<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

if (! function_exists('toast')) {
    function toast(string $message, string $type = 'success'): void
    {
        session()->flash('toast', [
            'message' => $message,
            'type'    => $type,
        ]);
    }
}

if (! function_exists('active_route')) {
    function active_route(string $route): string
    {
        return request()->routeIs($route) ? 'active' : '';
    }
}

if (! function_exists('format_date')) {
    function format_date($date, string $format = 'd M Y'): string
    {
        return \Carbon\Carbon::parse($date)->format($format);
    }
}

if (! function_exists('slugify')) {
    function slugify(string $text): string
    {
        return Str::slug($text);
    }
}

if (! function_exists('tgl')) {
    function tgl($date)
    {
        return Carbon::parse($date)->isoFormat('D MMM YYYY');
    }
}

if (! function_exists('itemOrItems')) {
    function itemOrItems($count)
    {
        return $count.' '.($count > 1 ? 'Items' : 'Item');
    }
}



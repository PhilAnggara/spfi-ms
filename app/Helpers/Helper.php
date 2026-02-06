<?php

use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
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

if (! function_exists('itemOrItems')) {
    function itemOrItems($count)
    {
        return $count.' '.($count > 1 ? 'Items' : 'Item');
    }
}

if (! function_exists('tgl')) {
    function tgl($date)
    {
        return Carbon::parse($date)->isoFormat('D MMM YYYY');
    }
}

if (! function_exists('human_time')) {
    function human_time($date)
    {
        return Carbon::parse($date)->diffForHumans();
    }
}

if (! function_exists('get_gm_name')) {
    function get_gm_name()
    {
        return User::where('role', 'General Manager')->first()->name;
    }
}

if (! function_exists('get_job_title')) {
    function get_job_title($user)
    {

        $dept = $user->department->name;
        // jika nama department lebih dari 12 karakter maka singkat $dept menggunakan code
        if (strlen($dept) > 12) {
            $dept = $user->department->alias;
        }

        return $dept . ' ' . $user->role;
    }
}

// fungsi untuk menentukan menu aktif berdasarkan route saat ini
if (! function_exists('is_active_menu')) {
    function is_active_menu(array $routes): string
    {
        foreach ($routes as $route) {
            if (request()->is($route)) {
                return 'active';
            }
        }
        return '';
    }
}

if (! function_exists('get_manager_name')) {
    function get_manager_name($user)
    {
        $deptId = $user->department->id;
        $manager = User::where('department_id', $deptId)
            ->where('role', 'Manager')
            ->first();

        if (!$manager) {
            return 'N/A';
        }

        return $manager->name;
    }
}

if (! function_exists('status_badge_color')) {
    function status_badge_color($status)
    {
        return match ($status) {
            'DRAFT' => 'bg-light-secondary',
            'SUBMITTED' => 'bg-light-info',
            'RESUBMITTED' => 'bg-light-info',
            'ON_HOLD' => 'bg-light-warning',
            'CANVASING' => 'bg-light-primary',
            'REJECTED' => 'bg-light-danger',
            default => 'bg-light-secondary',
        };
    }
}
if (! function_exists('status_badge_icon')) {
    function status_badge_icon($status)
    {
        return match ($status) {
            'DRAFT' => 'fa-duotone fa-solid fa-circle-dot text-secondary',
            'SUBMITTED' => 'fa-duotone fa-solid fa-circle-up text-info',
            'RESUBMITTED' => 'fa-duotone fa-solid fa-circle-up text-info',
            'ON_HOLD' => 'fa-duotone fa-solid fa-circle-pause text-warning',
            'CANVASING' => 'fa-duotone fa-solid fa-circle-check text-primary',
            'REJECTED' => 'fa-duotone fa-solid fa-circle-xmark text-danger',
            default => 'fa-duotone fa-solid fa-circle-dot text-secondary',
        };
    }
}

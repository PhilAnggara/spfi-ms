<?php

use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

if (! function_exists('get_manager')) {
    function get_manager($user)
    {
        $deptId = $user->department->id;

        return User::where('department_id', $deptId)
            ->where('role', 'Manager')
            ->first();
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
            'PO_APPROVED' => 'bg-light-success',
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
            'PO_APPROVED' => 'fa-duotone fa-solid fa-circle-check text-success',
            'REJECTED' => 'fa-duotone fa-solid fa-circle-xmark text-danger',
            default => 'fa-duotone fa-solid fa-circle-dot text-secondary',
        };
    }
}

if (! function_exists('fk_on_delete')) {
    function fk_on_delete(string $action): string
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlsrv' && in_array($action, ['cascade', 'restrict', 'set null'], true)) {
            return 'no action';
        }

        return $action;
    }
}

if (! function_exists('category_icon')) {
    function category_icon($categoryName)
    {
        $name = strtolower($categoryName ?? '');

        return match (true) {
            str_contains($name, 'office') => 'fa-pen',
            str_contains($name, 'parts') => 'fa-gear',
            str_contains($name, 'factory') => 'fa-tools',
            str_contains($name, 'chem') => 'fa-flask',
            str_contains($name, 'fuel') => 'fa-gas-pump',
            str_contains($name, 'label') => 'fa-tags',
            str_contains($name, 'carton') => 'fa-box',
            str_contains($name, 'can') => 'fa-jar',
            str_contains($name, 'fish') && !str_contains($name, 'meal') => 'fa-fish',
            str_contains($name, 'fishmeal') => 'fa-fish-bones',
            str_contains($name, 'bc') => 'fa-box-open',
            str_contains($name, 'coal') => 'fa-mountain',
            str_contains($name, 'sludge') || str_contains($name, 'oil') => 'fa-oil-can',
            str_contains($name, 'capital') => 'fa-building',
            str_contains($name, 'scrap') => 'fa-recycle',
            str_contains($name, 'spice') || str_contains($name, 'ingredient') => 'fa-mortar-pestle',
            str_contains($name, 'raw') => 'fa-cubes',
            str_contains($name, 'finished') => 'fa-box-check',
            default => 'fa-box',
        };
    }
}

if (! function_exists('category_data_attr')) {
    function category_data_attr($categoryName)
    {
        $name = strtolower($categoryName ?? 'other');

        // Extract key part for CSS selector matching
        if (str_contains($name, 'office')) return 'office';
        if (str_contains($name, 'parts')) return 'parts';
        if (str_contains($name, 'factory')) return 'factory';
        if (str_contains($name, 'chem')) return 'chem';
        if (str_contains($name, 'fuel')) return 'fuel';
        if (str_contains($name, 'label')) return 'label';
        if (str_contains($name, 'carton')) return 'carton';
        if (str_contains($name, 'can')) return 'can';
        if (str_contains($name, 'fishmeal')) return 'fishmeal';
        if (str_contains($name, 'fish')) return 'fish';
        if (str_contains($name, 'bc')) return 'bc';
        if (str_contains($name, 'coal')) return 'coal';
        if (str_contains($name, 'sludge') || str_contains($name, 'oil')) return 'sludge';
        if (str_contains($name, 'capital')) return 'capital';
        if (str_contains($name, 'scrap')) return 'scrap';
        if (str_contains($name, 'spice') || str_contains($name, 'ingredient')) return 'spice';
        if (str_contains($name, 'raw')) return 'raw';
        if (str_contains($name, 'finished')) return 'finished';

        return 'other';
    }
}

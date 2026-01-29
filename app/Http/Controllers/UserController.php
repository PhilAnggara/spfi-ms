<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;

class UserController extends Controller
{
    private const ROLE_MAP = [
        'Manager' => [
            'Purchasing' => 'purchasing-manager',
            'Sales' => 'sales-manager',
            'Finance' => 'finance-manager',
            'Information Technology' => 'it-manager',
        ],
        'Supervisor' => [
            'Purchasing' => 'purchasing-supervisor',
            'Sales' => 'sales-supervisor',
            'Finance' => 'finance-supervisor',
            'Information Technology' => 'it-supervisor',
        ],
        'Staff' => [
            'Purchasing' => 'canvaser',
            'Sales' => 'sales-staff',
            'Finance' => 'finance-staff',
            'Information Technology' => 'it-staff',
        ],
    ];

    private function resolveSpatieRole(?string $departmentName, ?string $role): ?string
    {
        return self::ROLE_MAP[$role][$departmentName] ?? null;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // $users = User::where('id', '!=', Auth::id())->get();
        $users = User::all();
        $departments = Department::all();
        return view('pages.user', [
            'users' => $users,
            'departments' => $departments,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'department_id' => ['required', 'exists:departments,id'],
            'role' => ['required', 'in:Manager,Supervisor,Staff'],
        ]);

        $user = User::create([
            'name' => Str::title($request->name),
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'department_id' => $request->department_id,
            'role' => $request->role,
        ]);

        $departmentName = Department::find($request->department_id)?->name;
        $spatieRole = $this->resolveSpatieRole($departmentName, $request->role);
        if ($spatieRole) {
            $user->syncRoles([$spatieRole]);
        }

        return redirect()->back()->with('success', 'New user has been created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $user = User::findOrFail($id);

        // Store editing user id in session BEFORE validation for error handling
        $request->session()->flash('editing_user_id', $id);

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username,'.$id],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email,'.$id],
            'department_id' => ['required', 'exists:departments,id'],
            'role' => ['nullable', 'in:Manager,Supervisor,Staff'],
        ];

        // Add password validation only if password field is filled
        if ($request->filled('password')) {
            $rules['password'] = ['required', 'confirmed', Rules\Password::defaults()];
        }

        $request->validate($rules);

        // Update user data
        $user->name = Str::title($request->name);
        $user->username = $request->username;
        $user->email = $request->email;
        $user->department_id = $request->department_id;
        // $user->role = $request->role;

        // Update password only if provided
        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        if ($request->filled('role')) {
            $user->role = $request->role;
        }

        $user->save();

        if ($request->filled('role') || $request->filled('department_id')) {
            $departmentName = Department::find($request->department_id)?->name;
            $spatieRole = $this->resolveSpatieRole($departmentName, $request->input('role', $user->role));
            if ($spatieRole) {
                $user->syncRoles([$spatieRole]);
            }
        }

        return redirect()->back()->with('success', "User {$user->name} has been updated successfully.");
    }

    /**
     * Update the authenticated user's password.
     */
    public function changePassword(Request $request)
    {
        // Flag so the correct modal opens and errors stay scoped
        $request->session()->flash('change_password', true);

        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = $request->user();
        $user->password = Hash::make($request->password);
        $user->save();

        return back()->with('success', 'Password updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $user = User::findOrFail($id);
        $name = $user->name;
        $user->delete();
        return redirect()->back()->with('success', "User $name has been deleted successfully.");
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $users = User::with(['roles', 'permissions', 'department'])->get();
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
            'password' => ['required', 'confirmed', Password::defaults()],
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

        $message = 'New user has been created successfully.';
        if ($request->user()?->can('manage-user-access')) {
            $message .= ' Assign Spatie roles and permissions via Manage Access.';
        }

        return redirect()->back()->with('success', $message);
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
    public function update(Request $request, string $user)
    {
        $id = $user;
        $userModel = User::findOrFail($id);

        $request->session()->flash('editing_user_id', $id);

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username,'.$id],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email,'.$id],
            'department_id' => ['required', 'exists:departments,id'],
            'role' => ['nullable', 'in:Manager,Supervisor,Staff'],
        ];

        if ($request->filled('password')) {
            $rules['password'] = ['required', 'confirmed', Password::defaults()];
        }

        $request->validate($rules);

        $userModel->name = Str::title($request->name);
        $userModel->username = $request->username;
        $userModel->email = $request->email;
        $userModel->department_id = $request->department_id;

        if ($request->filled('password')) {
            $userModel->password = Hash::make($request->password);
        }

        if ($request->filled('role')) {
            $userModel->role = $request->role;
        }

        $userModel->save();

        return redirect()->back()->with('success', "User {$userModel->name} has been updated successfully.");
    }

    /**
     * Update the authenticated user's password.
     */
    public function changePassword(Request $request)
    {
        $request->session()->flash('change_password', true);

        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $request->user();
        $user->password = Hash::make($request->password);
        $user->save();

        return back()->with('success', 'Password updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $user)
    {
        $userModel = User::findOrFail($user);
        $name = $userModel->name;
        $userModel->delete();

        return redirect()->back()->with('success', "User $name has been deleted successfully.");
    }
}

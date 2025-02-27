<?php

// app/Http/Controllers/Api/UserController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct()
    {
    }

    /**
     * Display a listing of the users.
     */
    public function index(Request $request)
    {
        $query = User::query();

        // Filter by role
        if ($request->has('role')) {
            $query->role($request->role);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Search by name or email
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $users = $query->with('roles')->paginate(15);

        return UserResource::collection($users);
    }

    /**
     * Store a newly created user in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', Rules\Password::defaults()],
            'phone' => ['nullable', 'string', 'max:20', 'unique:users'],
            'whatsapp_phone' => ['nullable', 'string', 'max:20', 'unique:users'],
            'status' => ['required', Rule::in(['active', 'inactive', 'pending'])],
            'role' => ['required', Rule::in(['admin', 'gym_admin', 'trainer', 'dietitian', 'client'])],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
            'whatsapp_phone' => $validated['whatsapp_phone'] ?? null,
            'status' => $validated['status'],
        ]);

        // Assign role
        $user->assignRole($validated['role']);

        return new UserResource($user->load('roles'));
    }

    /**
     * Display the specified user.
     */
    public function show(User $user)
    {
        $user->load(['roles', 'permissions']);

        return new UserResource($user);
    }

    /**
     * Update the specified user in storage.
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'phone' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('users')->ignore($user->id),
            ],
            'whatsapp_phone' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('users')->ignore($user->id),
            ],
            'status' => ['sometimes', 'required', Rule::in(['active', 'inactive', 'pending'])],
            'role' => ['sometimes', 'required', Rule::in(['admin', 'gym_admin', 'trainer', 'dietitian', 'client'])],
            'password' => ['nullable', Rules\Password::defaults()],
        ]);

        // Update user details
        $updateData = collect($validated)->except(['password', 'role'])->toArray();

        // If password is provided, hash it
        if (isset($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $user->update($updateData);

        // Update role if provided
        if (isset($validated['role'])) {
            // Remove current roles
            $user->roles()->detach();

            // Assign new role
            $user->assignRole($validated['role']);
        }

        return new UserResource($user->load('roles'));
    }
    /**
     * Remove the specified user from storage.
     */
    public function destroy(User $user)
    {
        if ((int) auth()->id() === (int) $user->id) {
            return response()->json([
                'message' => 'You cannot delete your own account',
            ], 403);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }

    /**
     * Get available roles.
     */
    public function roles()
    {
        $roles = Role::all()->pluck('name');

        return response()->json(['roles' => $roles]);
    }
}
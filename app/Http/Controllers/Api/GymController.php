<?php
// app/Http/Controllers/Api/GymController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GymResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\Gym;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Gate;

class GymController extends Controller
{
    protected $subscriptionService;

    /**
     * Create a new controller instance.
     *
     * @param  \App\Services\SubscriptionService  $subscriptionService
     * @return void
     */
    public function __construct()
    {
        $this->subscriptionService = $subscriptionService ?? app(SubscriptionService::class);

    }

    /**
     * Display a listing of the gyms.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // If admin, show all gyms
        if ($user->hasRole('admin')) {
            $gyms = Gym::with('owner')
                ->withCount(['users', 'clients', 'trainers', 'dietitians'])
                ->paginate(10);
        } else {
            // Otherwise show only gyms where user is owner or member
            $gyms = $user->gyms()
                ->with('owner')
                ->withCount(['users', 'clients', 'trainers', 'dietitians'])
                ->paginate(10);
        }

        return GymResource::collection($gyms);
    }

    /**
     * Store a newly created gym in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
        ]);

        $user = $request->user();
        $gym = Gym::create([
            'name' => $request->name,
            'address' => $request->address,
            'phone' => $request->phone,
            'email' => $request->email,
            'owner_id' => $user->id,
            'subscription_status' => 'trial',
            'subscription_expires_at' => now()->addDays(14), // 14-day trial
        ]);

        // Add owner as gym_admin
        $gym->users()->attach($user->id, [
            'role' => 'gym_admin',
            'status' => 'active'
        ]);

        return new GymResource($gym->load('owner'));
    }

    /**
     * Display the specified gym.
     */
    public function show(Gym $gym)
    {
        Gate::authorize('view', $gym);

        $gym->load('owner')
            ->loadCount(['users', 'clients', 'trainers', 'dietitians']);

        return new GymResource($gym);
    }

    /**
     * Update the specified gym in storage.
     */
    public function update(Request $request, Gym $gym)
    {
        Gate::authorize('update', $gym);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
        ]);

        $gym->update($validated);

        return new GymResource($gym->load('owner'));
    }

    /**
     * Remove the specified gym from storage.
     */
    public function destroy(Gym $gym)
    {
        Gate::authorize('delete', $gym);

        // This will cascade delete all gym relationships due to foreign key constraints
        $gym->delete();

        return response()->json(['message' => 'Gym deleted successfully']);
    }

    /**
     * Get users associated with a gym.
     */
    public function users(Request $request, Gym $gym)
    {
        Gate::authorize('view', $gym);

        $validated = $request->validate([
            'role' => ['nullable', Rule::in(['gym_admin', 'trainer', 'dietitian', 'client'])],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $query = $gym->users();

        if (isset($validated['role'])) {
            $query->wherePivot('role', $validated['role']);
        }

        if (isset($validated['status'])) {
            $query->wherePivot('status', $validated['status']);
        }

        $users = $query->paginate(15);

        return UserResource::collection($users);
    }

    /**
     * Add a user to a gym.
     */
    public function addUser(Request $request, Gym $gym)
    {
        Gate::authorize('update', $gym);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => ['required', Rule::in(['gym_admin', 'trainer', 'dietitian', 'client'])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        // Check subscription limits for client addition
        if ($request->role === 'client') {
            // Check if gym can add more clients
            if (!$this->subscriptionService->canAddMoreClients($gym->id)) {
                return response()->json([
                    'error' => 'You have reached the maximum number of clients allowed by your subscription plan.'
                ], 403);
            }
        }

        // Check if user is already in the gym
        $existingUser = $gym->users()->where('user_id', $validated['user_id'])->first();

        if ($existingUser) {
            // Update the role and status
            $gym->users()->updateExistingPivot($validated['user_id'], [
                'role' => $validated['role'],
                'status' => $validated['status']
            ]);

            $message = 'User role updated in gym';
        } else {
            // Add user to gym
            $gym->users()->attach($validated['user_id'], [
                'role' => $validated['role'],
                'status' => $validated['status']
            ]);

            $message = 'User added to gym';
        }

        $user = User::findOrFail($validated['user_id']);

        // Assign the appropriate role if user doesn't have it
        if ($validated['role'] === 'trainer' && !$user->hasRole('trainer')) {
            $user->assignRole('trainer');
        } elseif ($validated['role'] === 'dietitian' && !$user->hasRole('dietitian')) {
            $user->assignRole('dietitian');
        } elseif ($validated['role'] === 'client' && !$user->hasRole('client')) {
            $user->assignRole('client');
        } elseif ($validated['role'] === 'gym_admin' && !$user->hasRole('gym_admin')) {
            $user->assignRole('gym_admin');
        }

        return response()->json([
            'message' => $message,
            'user' => new UserResource($user)
        ]);
    }

    /**
     * Remove a user from a gym.
     */
    public function removeUser(Gym $gym, User $user)
    {
        Gate::authorize('update', $gym);

        $gym->users()->detach($user->id);

        return response()->json(['message' => 'User removed from gym']);
    }

    /**
     * Get the gym's subscription information.
     *
     * @param  \App\Models\Gym  $gym
     * @return \Illuminate\Http\Response
     */
    public function subscription(Gym $gym)
    {
        $subscription = $this->subscriptionService->getActiveSubscription($gym->id);

        if (!$subscription) {
            return response()->json([
                'status' => $gym->subscription_status,
                'expires_at' => $gym->subscription_expires_at,
                'has_active_subscription' => false,
                'message' => 'No active subscription found'
            ]);
        }

        return response()->json([
            'message' => 'active subscription',
            'subscription' => new SubscriptionResource($subscription)
        ]);
    }

}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserPreference;

class UserPreferenceController extends Controller
{
    // List preferences of a user
    public function index($user_id)
    {
        return UserPreference::where('user_id', $user_id)->get();
    }

    // Store new preference
    public function store(Request $request, $user_id)
    {
        $validated = $request->validate([
            'preferred_occasion' => 'nullable|string|max:100',
            'favorite_category' => 'nullable|string|max:100',
            'budget_range_min' => 'nullable|numeric|min:0|max:999999999999999',
            'budget_range_max' => 'nullable|numeric|min:0|max:999999999999999',
        ]);

        $validated['user_id'] = $user_id;

        $preference = UserPreference::create($validated);

        return response()->json($preference, 201);
    }

    // Show one preference
    public function show($user_id, $id)
    {
        return UserPreference::where('user_id', $user_id)->findOrFail($id);
    }

    // Update preference
    public function update(Request $request, $user_id, $id)
    {
        $preference = UserPreference::where('user_id', $user_id)->findOrFail($id);

        $validated = $request->validate([
            'preferred_occasion' => 'nullable|string|max:100',
            'favorite_category' => 'nullable|string|max:100',
            'budget_range_min' => 'nullable|numeric|min:0|max:999999999999999',
            'budget_range_max' => 'nullable|numeric|min:0|max:999999999999999',
        ]);

        $preference->update($validated);

        return response()->json($preference);
    }

    // Delete preference
    public function destroy($user_id, $id)
    {
        $preference = UserPreference::where('user_id', $user_id)->findOrFail($id);
        $preference->delete();
        return response()->json(['message' => 'Deleted']);
    }
}

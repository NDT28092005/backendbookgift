<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserAddress;

class UserAddressController extends Controller
{
    // List addresses of a user
    public function index($user_id)
    {
        return UserAddress::where('user_id', $user_id)->get();
    }

    // Store new address
    public function store(Request $request, $user_id)
    {
        $validated = $request->validate([
            'address_line1' => 'required|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'required|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'required|string|max:100',
            'is_default' => 'nullable|boolean',
        ]);

        $validated['user_id'] = $user_id;

        // Nếu đặt làm mặc định, bỏ mặc định của các địa chỉ khác
        if (isset($validated['is_default']) && $validated['is_default']) {
            UserAddress::where('user_id', $user_id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $address = UserAddress::create($validated);

        return response()->json($address, 201);
    }

    // Show one address
    public function show($user_id, $id)
    {
        return UserAddress::where('user_id', $user_id)->findOrFail($id);
    }

    // Update address
    public function update(Request $request, $user_id, $id)
    {
        $address = UserAddress::where('user_id', $user_id)->findOrFail($id);

        $validated = $request->validate([
            'address_line1' => 'required|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'required|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'required|string|max:100',
            'is_default' => 'nullable|boolean',
        ]);

        // Nếu đặt làm mặc định, bỏ mặc định của các địa chỉ khác
        if (isset($validated['is_default']) && $validated['is_default']) {
            UserAddress::where('user_id', $user_id)
                ->where('address_id', '!=', $id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $address->update($validated);

        return response()->json($address);
    }

    // Delete address
    public function destroy($user_id, $id)
    {
        $address = UserAddress::where('user_id', $user_id)->findOrFail($id);
        $address->delete();
        return response()->json(['message' => 'Deleted']);
    }
}

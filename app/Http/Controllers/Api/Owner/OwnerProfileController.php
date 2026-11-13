<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OwnerProfileController extends Controller
{
    /**
     * Get the owner profile and preferences.
     */
    public function show(): JsonResponse
    {
        $ownerUser = User::whereRaw('LOWER(role) IN (?, ?, ?)', ['owner', 'superadmin', 'admin'])->first();

        $defaults = [
            'name' => $ownerUser ? $ownerUser->name : 'Dewi Sartika',
            'title' => 'Pemilik Usaha & Direktur Koperasi',
            'email' => $ownerUser ? $ownerUser->email : 'owner@coop.id',
            'phone' => '0812-9841-2201',
            'nik' => $ownerUser ? ($ownerUser->nik ?? '327501550230056') : '327501550230056',
            'avatar' => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=150&auto=format&fit=crop&q=80',
            'defaultBranch' => 'all',
            'alertLowStock' => true,
            'compactTable' => false,
        ];

        $saved = AppSetting::get('owner_profile_data');
        if ($saved) {
            $parsed = json_decode($saved, true);
            if (is_array($parsed)) {
                $defaults = array_merge($defaults, $parsed);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $defaults,
        ]);
    }

    /**
     * Update owner profile and preferences.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'title' => 'nullable|string|max:150',
            'email' => 'required|email|max:150',
            'phone' => 'nullable|string|max:50',
            'nik' => 'nullable|string|max:50',
            'avatar' => 'nullable|string|max:500',
            'defaultBranch' => 'nullable|string|max:50',
            'alertLowStock' => 'nullable|boolean',
            'compactTable' => 'nullable|boolean',
        ]);

        AppSetting::set('owner_profile_data', json_encode($validated), 'profile');

        // Also update Owner User model if exists
        $ownerUser = User::whereRaw('LOWER(role) IN (?, ?, ?)', ['owner', 'superadmin', 'admin'])->first();
        if ($ownerUser) {
            $userUpdate = [
                'name' => $validated['name'],
            ];
            // Only update nik if not taken by another user
            if (!empty($validated['nik'])) {
                $nikExists = User::where('nik', $validated['nik'])->where('id', '!=', $ownerUser->id)->exists();
                if (!$nikExists) {
                    $userUpdate['nik'] = $validated['nik'];
                }
            }
            // Only update email if it is not taken by another user
            $emailExists = User::where('email', $validated['email'])->where('id', '!=', $ownerUser->id)->exists();
            if (!$emailExists) {
                $userUpdate['email'] = $validated['email'];
            }
            $ownerUser->update($userUpdate);
        }

        return response()->json([
            'success' => true,
            'message' => 'Profil dan preferensi Owner berhasil diperbarui.',
            'data' => $validated,
        ]);
    }
}

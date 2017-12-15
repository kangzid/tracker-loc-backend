<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\EncryptedStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Admin hanya bisa lihat users (employees) di tenant mereka sendiri
        $adminId = $request->user()->id;
        $users = User::with('employee')
            ->where(function($q) use ($adminId) {
                // Hanya tampilkan employee milik admin ini
                $q->whereHas('employee', function($subQ) use ($adminId) {
                    $subQ->where('admin_id', $adminId);
                })
                // Atau admin itu sendiri
                ->orWhere('id', $adminId);
            })
            ->get();
        return response()->json($users);
    }

    public function admins(Request $request)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Admin hanya bisa lihat dirinya sendiri, TIDAK bisa lihat admin lain atau superadmin
        $adminId = $request->user()->id;
        $admins = User::where('role', 'admin')
            ->where('id', $adminId)
            ->get();
        return response()->json($admins);
    }

    public function show(Request $request, $id)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $adminId = $request->user()->id;
        
        // Admin hanya bisa lihat detail user di tenant mereka atau diri sendiri
        $user = User::with('employee')
            ->where(function($q) use ($adminId, $id) {
                // User adalah admin itu sendiri
                $q->where('id', $id)->where('id', $adminId)
                // Atau employee milik admin ini
                ->orWhere(function($subQ) use ($adminId, $id) {
                    $subQ->where('id', $id)
                        ->whereHas('employee', function($empQ) use ($adminId) {
                            $empQ->where('admin_id', $adminId);
                        });
                });
            })
            ->first();
        
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        return response()->json($user);
    }

    public function update(Request $request, $id)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $adminId = $request->user()->id;
        
        // Verify user belongs to this admin's tenant
        $user = User::where(function($q) use ($adminId, $id) {
                // User adalah admin itu sendiri
                $q->where('id', $id)->where('id', $adminId)
                // Atau employee milik admin ini
                ->orWhere(function($subQ) use ($adminId, $id) {
                    $subQ->where('id', $id)
                        ->whereHas('employee', function($empQ) use ($adminId) {
                            $empQ->where('admin_id', $adminId);
                        });
                });
            })
            ->first();
        
        if (!$user) {
            return response()->json(['message' => 'User not found or unauthorized'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $id,
            'password' => 'nullable|string|min:8',
            'role' => 'required|in:admin,employee',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user->fill([
            'name' => $request->name,
            'email' => $request->email,
            'is_active' => $request->is_active ?? true,
            
            'photo_path' => $user->photo_path,

        ]);

        if ($request->password) {
            $user->password = Hash::make($request->password);
        }

        $user->role = $request->role;
                if ($request->has('photo_base64')) {
            $oldPath = $user->photo_path ?: ($user->employee ? $user->employee->photo_path : null);
            if (empty($request->photo_base64)) {
                if ($oldPath) {
                    EncryptedStorageService::deleteFile($oldPath);
                }
                $user->photo_path = null;
                if ($user->employee) {
                    $user->employee->photo_path = null;
                    $user->employee->save();
                }
            } elseif (str_starts_with($request->photo_base64, 'data:image')) {
                if ($oldPath) {
                    EncryptedStorageService::deleteFile($oldPath);
                }
                $stored = EncryptedStorageService::storeEncrypted(
                    $request->photo_base64,
                    $adminId,
                    'avatars',
                    'avatar_' . $user->id
                );
                $user->photo_path = $stored['path'];
                if ($user->employee) {
                    $user->employee->photo_path = $stored['path'];
                    $user->employee->save();
                }
            }
        }
        $user->save();

        return response()->json($user);
    }

    public function destroy(Request $request, $id)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $adminId = $request->user()->id;
        
        // Verify user belongs to this admin's tenant
        $user = User::where(function($q) use ($adminId, $id) {
                // Employee milik admin ini (admin tidak bisa delete diri sendiri)
                $q->where('id', $id)
                    ->whereHas('employee', function($empQ) use ($adminId) {
                        $empQ->where('admin_id', $adminId);
                    });
            })
            ->first();
        
        if (!$user) {
            return response()->json(['message' => 'User not found or unauthorized'], 404);
        }
        
        // Prevent deleting self
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Cannot delete your own account'], 403);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }

            public function photo(Request $request, $id)
    {
        $user = User::with('employee')->find($id);

        if (!$user) {
            return response()->json(['message' => 'Pengguna tidak ditemukan.'], 404);
        }

        $photoPath = $user->photo_path ?: ($user->employee ? $user->employee->photo_path : null);

        if (!$photoPath) {
            return response()->json(['message' => 'Foto avatar tidak ditemukan.'], 404);
        }

        try {
            return EncryptedStorageService::streamResponse($photoPath, 'avatar_' . $user->id, false);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Berkas foto tidak ditemukan di penyimpanan server.'], 404);
        }
    }

}

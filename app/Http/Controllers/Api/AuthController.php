<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Employee;
use App\Services\EncryptedStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        if (!$user->is_active) {
            return response()->json(['message' => 'Account is inactive'], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        $photoPath = $user->photo_path ?: ($user->employee ? $user->employee->photo_path : null);
        $photoBase64 = $photoPath ? EncryptedStorageService::getBase64($photoPath) : null;

        $userData = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'photo_path' => $user->photo_path,
            'photo_base64' => $photoBase64,
            'is_active' => $user->is_active,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];

        // Add employee data if user is employee
        if ($user->role === 'employee' && $user->employee) {
            $emp = $user->employee;
            $emp->photo_base64 = $photoBase64;
            $userData['employee'] = $emp;
        }

        return response()->json([
            'message' => 'Login successful',
            'user' => $userData,
            'token' => $token,
            'token_type' => 'Bearer'
        ]);
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:admin,employee',
            'employee_id' => 'required_if:role,employee|unique:employees,employee_id',
            'phone' => 'nullable|string',
            'department' => 'nullable|string',
            'position' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = new User([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);
        $user->role = $request->role;
        $user->save();

        if ($request->role === 'employee') {
            Employee::create([
                'user_id' => $user->id,
                'employee_id' => $request->employee_id,
                'phone' => $request->phone,
                'department' => $request->department,
                'position' => $request->position,
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user->load('employee'),
            'token' => $token,
            'token_type' => 'Bearer'
        ], 201);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully']);
    }

    public function profile(Request $request)
    {
        $user = $request->user()->load('employee');
        $photoPath = $user->photo_path ?: ($user->employee ? $user->employee->photo_path : null);
        if ($photoPath) {
            $user->photo_base64 = EncryptedStorageService::getBase64($photoPath);
            if ($user->employee) {
                $user->employee->photo_base64 = $user->photo_base64;
            }
        }
        return response()->json($user);
    }

    
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:50',
            'password' => 'nullable|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user->name = $request->name;
        $user->email = $request->email;

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        $tenantId = $user->isAdmin() ? $user->id : ($user->admin_id ?? 1);

                $oldPath = $user->photo_path ?: ($user->employee ? $user->employee->photo_path : null);
        if ($request->has('photo_base64')) {
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
                    $tenantId,
                    'avatars',
                    'avatar_admin_' . $user->id
                );
                $user->photo_path = $stored['path'];
                if ($user->employee) {
                    $user->employee->photo_path = $stored['path'];
                    $user->employee->save();
                }
            }
        }
        $user->save();

        if ($user->employee) {
            $empData = [];
            if ($request->has('phone')) $empData['phone'] = $request->phone;
            if ($user->photo_path) $empData['photo_path'] = $user->photo_path;
            if (!empty($empData)) {
                $user->employee->update($empData);
            }
        }

        return response()->json([
            'message' => 'Profil berhasil diperbarui.',
            'user' => $user->load('employee')
        ]);
    }

    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required',
            'password' => 'required|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();

        // Check if current password matches
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Password saat ini salah.'], 400);
        }

        // Update password
        $user->update([
            'password' => Hash::make($request->password)
        ]);

        return response()->json([
            'message' => 'Password berhasil diubah.'
        ]);
    }
}
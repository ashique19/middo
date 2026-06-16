<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // Registration: Assigning a default 'corporate' role
    public function register(Request $request)
    {
        try {
            $validated = $request->validate([
                'first_name' => 'required|string|max:255|min:2',
                'last_name' => 'required|string|max:255|min:2',
                'mobile' => ['required', 'string', 'regex:/^01[3-9][0-9]{8}$/', 'unique:users,mobile'],
                'password' => 'required|string|min:8',
                'company_name' => 'required|string|max:255|min:4',
                'address' => 'required|string|min:10|max:255',
                'city_id' => 'required|exists:cities,id',
                'area_id' => 'required|exists:areas,id',
            ]);

            $role = Role::where('name', 'corporate')->firstOrFail();

            $user = User::create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'mobile' => $validated['mobile'],
                'password' => Hash::make($validated['password']),
                'company_name' => $validated['company_name'],
                'address' => $validated['address'],
                'city_id' => $validated['city_id'],
                'area_id' => $validated['area_id'],
                'role_id' => $role->id, 
                'status' => 'active', // Set initial status to active
                'is_mobile_verified' => false,
            ]);

            Auth::login($user);
            return response()->json(['success' => true, 'redirect' => '/dashboard']);   
                
        } catch (ValidationException $e) {
            // This returns the specific field errors (e.g., mobile: "has already been taken")
            return response()->json(['errors' => $e->errors()], 422);
            
        } catch (\Exception $e) {
            Log::error('Registration failed: ' . $e->getMessage());
            return response()->json([
                'errors' => ['general' => ['Registration failed. Please try again later.']]
            ], 500);
        }
    }

    // Registration: Assigning a default 'kitchen' role
    public function registerKitchen(Request $request)
    {
        try {
            $validated = $request->validate([
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'mobile' => ['required', 'string', 'regex:/^01[3-9][0-9]{8}$/', 'unique:users,mobile'],
                'password' => 'required|string|min:8',
                'address' => 'required|string',
                'city_id' => 'required|exists:cities,id',
                'area_id' => 'required|exists:areas,id',
            ]);

            $role = Role::where('name', 'kitchen')->firstOrFail();

            $user = User::create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'mobile' => $validated['mobile'],
                'password' => Hash::make($validated['password']),
                'address' => $validated['address'],
                'city_id' => $validated['city_id'],
                'area_id' => $validated['area_id'],
                'role_id' => $role->id,
                'status' => 'pending', // Set initial status to pending
                'is_mobile_verified' => false,
            ]);

            Auth::login($user);
            return response()->json(['success' => true, 'redirect' => '/kitchen-dashboard']);

        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Kitchen Reg Failed: ' . $e->getMessage());
            return response()->json(['errors' => ['general' => ['System error. Try again later.']]], 500);
        }
    }

    // Login: Using mobile instead of email
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'mobile' => 'required|string',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();

            // After Auth::login($user);
            return redirect()->route('dashboard.redirect');
        }

        return back()->withErrors(['mobile' => 'Invalid mobile number or password.']);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['mobile' => 'required|exists:users,mobile']);

        // 1. Find user by mobile
        $user = User::where('mobile', $request->mobile)->first();

        // 2. Generate 6-digit OTP
        $otp = rand(100000, 999999);

        // 3. Save to database
        $user->update([
            'otp' => $otp,
            'otp_expires_at' => now()->addMinutes(5)
        ]);

        // 4. Trigger SMS Logic here (e.g., SMS Gateway API)
        // SendSMS::to($user->mobile)->message("Your Middo OTP is: $otp");

        return back()->with('status', 'OTP sent to your mobile!');
    }
}
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Support\PasswordResetOtp;
use App\Support\SignupOtp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function sendSignupOtp(Request $request)
    {
        try {
            $validated = $request->validate([
                'mobile' => ['required', 'string', 'regex:/^01[3-9][0-9]{8}$/', 'unique:users,mobile'],
            ], [
                'mobile.regex' => 'Provide a valid 11-digit mobile number (e.g. 01710123456).',
                'mobile.unique' => 'This mobile number is already registered.',
            ]);

            $result = SignupOtp::send($validated['mobile']);

            if (! ($result['ok'] ?? false)) {
                return response()->json([
                    'errors' => ['mobile' => [$result['message'] ?? 'Failed to send OTP.']],
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'] ?? 'Verification code sent successfully.',
                'mobile' => $validated['mobile'],
                'expires_in' => 300,
                'debug_otp' => $result['debug_otp'] ?? null,
            ]);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Signup OTP failed: '.$e->getMessage());

            return response()->json([
                'errors' => ['general' => ['Failed to send OTP. Please try again later.']],
            ], 500);
        }
    }

    // Registration: Assigning a default 'corporate' role
    public function register(Request $request)
    {
        try {
            $validated = $request->validate([
                'first_name' => 'required|string|max:255|min:2',
                'last_name' => 'required|string|max:255|min:2',
                'mobile' => ['required', 'string', 'regex:/^01[3-9][0-9]{8}$/', 'unique:users,mobile'],
                'otp' => ['required', 'string', 'size:4'],
                'password' => 'required|string|min:8',
                'company_name' => 'required|string|max:255|min:4',
                'address' => 'required|string|min:10|max:255',
                'city_id' => 'required|exists:cities,id',
                'area_id' => 'required|exists:areas,id',
            ], [
                'otp.size' => 'Enter the 4-digit verification code sent by SMS.',
            ]);

            if (! SignupOtp::verify($validated['mobile'], $validated['otp'])) {
                return response()->json([
                    'errors' => ['otp' => ['Invalid or expired verification code.']],
                ], 422);
            }

            $role = Role::where('name', 'corporate')->firstOrFail();

            $user = User::create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'company_name' => $validated['company_name'],
                'mobile' => $validated['mobile'],
                'password' => $validated['password'],
                'address' => $validated['address'],
                'city_id' => $validated['city_id'],
                'area_id' => $validated['area_id'],
                'role_id' => $role->id,
                'status' => 'active',
                'is_mobile_verified' => true,
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

            // User casts password to 'hashed' — pass the plain value (same as corporate register).
            User::create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'mobile' => $validated['mobile'],
                'password' => $validated['password'],
                'address' => $validated['address'],
                'city_id' => $validated['city_id'],
                'area_id' => $validated['area_id'],
                'role_id' => $role->id,
                'status' => 'pending',
                'is_mobile_verified' => false,
            ]);

            session()->flash(
                'status',
                'Kitchen signup received. Your account is pending admin approval — you can log in after activation.'
            );

            return response()->json(['success' => true, 'redirect' => route('login')]);

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

        $remember = $request->boolean('remember');

        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();

            $user = Auth::user();

            if ($user->status !== 'active') {
                $status = $user->status;
                Auth::logout();

                return back()
                    ->withInput($request->only('mobile', 'redirect', 'remember'))
                    ->with('account_status', $status);
            }

            $redirect = $request->input('redirect');

            // Corporate users should return to the page they were redirected from
            // (e.g. the /menu "Order Now" flow) instead of always landing on the dashboard.
            if (
                $user->role?->name === 'corporate'
                && $this->isSafeRedirect($redirect)
            ) {
                return redirect()->to($redirect);
            }

            return redirect()->route('dashboard.redirect');
        }

        return back()->withErrors(['mobile' => 'Invalid mobile number or password.']);
    }

    /**
     * Only allow redirects to internal URLs on this application to prevent open redirects.
     */
    private function isSafeRedirect(?string $url): bool
    {
        if (empty($url)) {
            return false;
        }

        $target = parse_url($url, PHP_URL_HOST);

        // Relative URLs (no host) are inherently internal and safe.
        if ($target === null) {
            return true;
        }

        return in_array($target, array_filter([
            request()->getHost(),
            parse_url(config('app.url'), PHP_URL_HOST),
        ]), true);
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
        $data = $request->validate([
            'mobile' => ['required', 'string', 'regex:/^01[3-9]\d{8}$/'],
        ], [
            'mobile.regex' => 'Provide a valid 11-digit mobile number (e.g. 01710123456).',
        ]);

        $user = User::query()
            ->with('role')
            ->where('mobile', $data['mobile'])
            ->first();

        // Avoid account enumeration — same generic message whether or not the mobile exists.
        $generic = 'If this mobile is registered, a reset code has been sent.';

        if ($user && $user->role?->name === 'corporate') {
            $result = PasswordResetOtp::send($data['mobile']);

            if (! ($result['ok'] ?? false)) {
                return back()
                    ->withInput($request->only('mobile'))
                    ->withErrors(['mobile' => $result['message'] ?? 'Failed to send reset code.']);
            }

            return redirect()
                ->route('password.request')
                ->with('status', $generic)
                ->with('reset_step', 'reset')
                ->with('reset_mobile', $data['mobile'])
                ->with('debug_otp', $result['debug_otp'] ?? null);
        }

        return redirect()
            ->route('password.request')
            ->with('status', $generic)
            ->with('reset_step', 'reset')
            ->with('reset_mobile', $data['mobile']);
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'mobile' => ['required', 'string', 'regex:/^01[3-9]\d{8}$/'],
            'otp' => ['required', 'string', 'size:4'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'mobile.regex' => 'Provide a valid 11-digit mobile number (e.g. 01710123456).',
            'otp.size' => 'Enter the 4-digit reset code sent by SMS.',
        ]);

        $user = User::query()
            ->with('role')
            ->where('mobile', $data['mobile'])
            ->first();

        if (! $user || $user->role?->name !== 'corporate') {
            return back()
                ->withInput($request->only('mobile', 'otp'))
                ->with('reset_step', 'reset')
                ->with('reset_mobile', $data['mobile'])
                ->withErrors(['mobile' => 'Unable to reset password for this mobile number.']);
        }

        if (! PasswordResetOtp::verify($data['mobile'], $data['otp'])) {
            return back()
                ->withInput($request->only('mobile', 'otp'))
                ->with('reset_step', 'reset')
                ->with('reset_mobile', $data['mobile'])
                ->withErrors(['otp' => 'Invalid or expired reset code.']);
        }

        $user->update([
            'password' => $data['password'],
            'is_mobile_verified' => true,
        ]);

        return redirect()
            ->route('login')
            ->with('status', 'Password updated. You can sign in with your new password.');
    }
}
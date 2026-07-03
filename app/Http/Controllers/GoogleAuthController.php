<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function redirect()
    {
        abort_unless(config('services.google.client_id'), 404);

        return Socialite::driver('google')->redirect();
    }

    public function callback()
    {
        abort_unless(config('services.google.client_id'), 404);

        try {
            $g = Socialite::driver('google')->user();
        } catch (\Throwable) {
            return redirect('/?auth=failed');
        }

        $user = User::where('email', $g->getEmail())->first();

        if (! $user) {
            // Auto-provision only for emails already registered as employees.
            $employee = Employee::where('email', $g->getEmail())->where('emp', 'active')->first();
            if (! $employee) {
                return redirect('/?auth=denied');
            }
            $user = User::create([
                'name' => $employee->first . ' ' . $employee->last,
                'email' => $employee->email,
                'password' => Str::password(24),
                'access' => $employee->access,
                'employee_id' => $employee->id,
            ]);
        }

        if (! $user->google_id) {
            $user->update(['google_id' => $g->getId()]);
        }

        Auth::login($user, remember: true);
        request()->session()->regenerate();

        return redirect('/');
    }
}

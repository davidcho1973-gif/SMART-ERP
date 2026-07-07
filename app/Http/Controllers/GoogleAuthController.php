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
        if (! config('services.google.client_id')) {
            return redirect('/?auth=unconfigured');
        }

        // always show Google's account chooser — otherwise Google silently reuses
        // the last-used account, which strands people who have several accounts
        return Socialite::driver('google')->with(['prompt' => 'select_account'])->redirect();
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
            // invited → active on this first sign-in
            if ($employee->activated_at === null) {
                $employee->update(['activated_at' => now()]);
            }
        }

        if (! $user->google_id) {
            $user->update(['google_id' => $g->getId()]);
        }

        Auth::login($user, remember: true);
        request()->session()->regenerate();

        return redirect('/');
    }
}

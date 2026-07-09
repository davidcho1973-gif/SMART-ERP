<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\Team;

/**
 * Central access policy: the single place that says which role may do what.
 *
 * Role ladder (design doc "권한 계층 재설계"):
 *   owner          — org owner: everything, incl. destructive ops & payroll
 *   hr_admin       — people + money org-wide; no org settings / site·company delete
 *   site_manager   — their assigned site(s) only; no payroll
 *   company_admin  — their subcontractor company only (defined now, opened later — D-2)
 *   crew_lead      — derived overlay for an employee who leads a crew (Team.lead)
 *   worker         — self only
 *
 * Legacy stored values keep working: 'admin' ⇒ owner, 'manager' ⇒ site_manager.
 * Every permission decision is  allows(role, cap)  ∧  in-scope(target)  — the
 * scope half lives with the caller (WorkforceApp::can) because it needs context.
 */
class Access
{
    /** Rank for the view-as ceiling and role-assignment ordering (higher = more authority). */
    public const RANK = [
        'worker' => 1,
        'crew_lead' => 2,
        'company_admin' => 3,
        'site_manager' => 4,
        'hr_admin' => 5,
        'owner' => 6,
    ];

    /** Capability → roles that hold it. Scoped roles are still bounded by inScope(). */
    protected const CAPS = [
        // org & structure
        'org.settings' => ['owner'],
        'sites.create' => ['owner'],
        'sites.edit' => ['owner', 'site_manager'],
        'sites.delete' => ['owner'],
        'companies.create' => ['owner'],
        'companies.edit' => ['owner', 'hr_admin', 'company_admin'],
        'companies.delete' => ['owner'],
        'teams.manage' => ['owner', 'hr_admin', 'site_manager', 'company_admin'],

        // people
        'employees.register' => ['owner', 'hr_admin', 'site_manager', 'company_admin'],
        'employees.edit' => ['owner', 'hr_admin', 'site_manager', 'company_admin'],
        'employees.terminate' => ['owner', 'hr_admin', 'company_admin'],
        'employees.delete' => ['owner', 'hr_admin'],
        'roles.assign' => ['owner', 'hr_admin', 'company_admin'],
        'assignments.manage' => ['owner', 'hr_admin', 'site_manager', 'company_admin'],

        // attendance
        'punch.manual' => ['owner', 'hr_admin', 'site_manager', 'company_admin', 'crew_lead'],
        'attendance.config' => ['owner', 'site_manager'],
        'timesheet.export' => ['owner', 'hr_admin', 'site_manager', 'company_admin'],
        'corrections.decide' => ['owner', 'hr_admin', 'site_manager', 'company_admin', 'crew_lead'],
        // a crew's work shift + lead adjustments to paid time (approve OT, restore early-leave)
        'shifts.manage' => ['owner', 'hr_admin', 'site_manager', 'company_admin', 'crew_lead'],
        'attendance.adjust' => ['owner', 'hr_admin', 'site_manager', 'company_admin', 'crew_lead'],

        // payroll — head-office only. The boss (owner) handles pay; the office
        // admin (hr_admin) manages people & attendance but never sees money.
        'payroll.view' => ['owner'],
        'payroll.process' => ['owner'],
        'payroll.export' => ['owner'],

        // comms
        'comms.announce' => ['owner', 'hr_admin', 'site_manager', 'company_admin'],

        // set a login password for an employee's account (for users without Google)
        'users.password' => ['owner'],
    ];

    /** Normalize a stored access value (legacy or canonical) to a canonical role. */
    public static function canonical(?string $access): string
    {
        return match ($access) {
            'admin', 'owner' => 'owner',
            'manager', 'site_manager' => 'site_manager',
            'hr_admin' => 'hr_admin',
            'company_admin' => 'company_admin',
            default => 'worker',
        };
    }

    /** Does any of the actor's roles hold the capability? (scope is the caller's half) */
    public static function allows(array|string $roles, string $cap): bool
    {
        $holders = self::CAPS[$cap] ?? [];
        foreach ((array) $roles as $r) {
            $role = $r === 'crew_lead' ? 'crew_lead' : self::canonical($r);
            if (in_array($role, $holders, true)) {
                return true;
            }
        }

        return false;
    }

    /** Rank of a stored access value / role name (0 = unknown). */
    public static function rank(?string $roleOrAccess): int
    {
        return self::RANK[self::canonical($roleOrAccess)] ?? (self::RANK[$roleOrAccess] ?? 0);
    }

    /**
     * Roles an assigner may grant (D-2: company_admin defined but not yet
     * assignable from the UI; developer/platform is out-of-band per D-4).
     */
    public static function assignable(string $assignerRoleOrAccess): array
    {
        return match (self::canonical($assignerRoleOrAccess)) {
            'owner' => ['owner', 'hr_admin', 'site_manager', 'worker'],
            'hr_admin' => ['site_manager', 'worker'],
            'site_manager' => ['worker'],   // a site lead can invite workers into their site
            default => [],
        };
    }

    /** Is the employee a crew lead (derived role overlay)? */
    public static function leadsTeams(?Employee $e): array
    {
        if (! $e) {
            return [];
        }

        return Team::where('lead', $e->id)->pluck('id')->all();
    }
}

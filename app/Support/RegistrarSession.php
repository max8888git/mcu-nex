<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class RegistrarSession
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $path = storage_path('framework/sessions');
        if (! is_dir($path)) {
            mkdir($path, 0775, true);
        }
        session_save_path($path);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public static function id(): ?int
    {
        self::start();
        $id = $_SESSION['user_id'] ?? null;
        if ($id === null || $id === '') {
            return null;
        }
        $id = (int) $id;

        return $id > 0 ? $id : null;
    }

    public static function login(int $userId): void
    {
        self::start();
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'] ?? '/',
                $params['domain'] ?? '',
                (bool) ($params['secure'] ?? false),
                (bool) ($params['httponly'] ?? true)
            );
        }
        session_destroy();
    }

    public static function user(): ?array
    {
        $id = self::id();
        if (! $id) {
            return null;
        }
        $row = DB::table('users as u')
            ->leftJoin('colleges as c', 'c.college_id', '=', 'u.college_id')
            ->where('u.user_id', $id)
            ->first(['u.user_id', 'u.full_name', 'u.email', 'u.role', 'u.is_active', 'u.college_id', 'c.college_name']);
        if (! $row || (int) $row->is_active !== 1) {
            self::logout();

            return null;
        }

        $departmentId = null;
        $departmentName = null;
        if (self::hasColumn('users', 'department_id')) {
            $dept = DB::table('users as u')
                ->leftJoin('departments as d', 'd.department_id', '=', 'u.department_id')
                ->where('u.user_id', $id)
                ->first(['u.department_id', 'd.department_name']);
            if ($dept && ! empty($dept->department_id)) {
                $departmentId = self::publicId('dept', (int) $dept->department_id);
                $departmentName = (string) ($dept->department_name ?? '');
            }
        }

        if ($departmentId) {
            $programs = DB::table('programs as p')
                ->join('users as u', 'u.department_id', '=', 'p.department_id')
                ->where('u.user_id', $id)
                ->where('p.is_active', 1)
                ->orderBy('p.program_name')
                ->get(['p.program_id', 'p.program_name']);
        } else {
            $programs = DB::table('user_programs as up')
                ->join('programs as p', 'p.program_id', '=', 'up.program_id')
                ->where('up.user_id', $id)
                ->orderBy('p.program_name')
                ->get(['p.program_id', 'p.program_name']);
        }

        $programIds = [];
        $programNames = [];
        foreach ($programs as $program) {
            $programIds[] = self::publicId('d', (int) $program->program_id);
            $programNames[] = (string) $program->program_name;
        }

        $role = (string) $row->role;
        AccountEmail::ensureSchema();
        $flags = DB::table('users')->where('user_id', $id)->first(); // the columns are added by AccountEmail (MySQL)

        return [
            'id' => self::publicId('u', (int) $row->user_id),
            'name' => $row->full_name,
            'email' => $row->email,
            'role' => $role,
            'roleLabel' => self::roleLabel($role),
            'isActive' => true,
            'collegeId' => ! empty($row->college_id) ? self::publicId('c', (int) $row->college_id) : null,
            'collegeName' => $row->college_name,
            'departmentId' => $departmentId,
            'departmentName' => $departmentName,
            'programIds' => $programIds,
            'programNames' => $programNames,
            'home' => self::home($role),
            // account emails (AccountEmail): a starting password must be changed first; reset by email needs a confirmed address
            'mustChangePassword' => (bool) ($flags->must_change_password ?? false),
            'emailConfirmed' => ! empty($flags->email_verified_at),
            'passwordPending' => (bool) ($flags->password_pending ?? false),
            'photoUrl' => ProfilePhoto::url((int) $row->user_id),
        ];
    }

    public static function roleLabel(string $role): string
    {
        return [
            'super_admin' => 'University Registrar',
            'admin' => 'Records Officer',
            'vpaa' => 'VPAA',
            'dean' => 'Dean',
            'program_chair' => 'Program Chair',
        ][$role] ?? $role;
    }

    public static function home(string $role): string
    {
        return [
            'super_admin' => 'super-admin/home-dashboard.html',
            'admin' => 'admin/home-dashboard.html',
            'vpaa' => 'viewing-with-approval/dashboard.html',
            'dean' => 'viewing/dashboard.html',
            'program_chair' => 'viewing-program-head/dashboard.html',
        ][$role] ?? 'index.html';
    }

    public static function publicId(string $prefix, int $id): string
    {
        return $prefix.'-'.$id;
    }

    private static function hasColumn(string $table, string $column): bool
    {
        return DB::getSchemaBuilder()->hasColumn($table, $column);
    }
}

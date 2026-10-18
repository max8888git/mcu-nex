<?php

namespace App\Services;

use App\Support\RegistrarSession;
use Illuminate\Http\Request;
use RegistrarResult;
use Symfony\Component\HttpFoundation\Response;

class DomainCatalog
{
    public static function respond(Request $request, string $runner): Response
    {
        RegistrarSession::start();
        if (! defined('REGISTRAR_LIBRARY')) {
            define('REGISTRAR_LIBRARY', true);
        }
        $GLOBALS['MCU_RAW_BODY'] = $request->getContent();
        self::load();
        if (! function_exists($runner)) {
            return response()->json(['ok' => false, 'error' => 'That part of the system is not available.'], 500);
        }

        try {
            ob_start();
            $runner();
            $body = ob_get_clean();

            return response($body === false ? '' : $body, 200, [
                'Content-Type' => 'application/json; charset=utf-8',
            ]);
        } catch (RegistrarResult $result) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            return response($result->body, $result->status, [
                'Content-Type' => 'application/json; charset=utf-8',
            ]);
        }
    }

    private static function load(): void
    {
        static $loaded = false;
        if ($loaded || function_exists('run_org_request')) {
            $loaded = true;

            return;
        }
        $dir = rtrim((string) config('registrar.legacy_api'), '/\\');
        require_once $dir.DIRECTORY_SEPARATOR.'bootstrap.php';
        require_once $dir.DIRECTORY_SEPARATOR.'schedule-workspaces.php';
        foreach ([
            'org.php',
            'sections.php',
            'facilities.php',
            'curricula.php',
            'curriculum-reviews.php',
            'curriculum-archive.php',
            'term-rollover.php',
            'schedule.php',
            'requests.php',
            'users.php',
            'room-types.php',
            'notifications.php',
        ] as $file) {
            require_once $dir.DIRECTORY_SEPARATOR.$file;
        }
        $loaded = true;
    }
}

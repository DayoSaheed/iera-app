<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Models\User;
use App\Services\Geo;

final class ApiController extends Controller
{
    public function cities(Request $req): Response
    {
        return $this->json(['cities' => Geo::searchCities($req->str('q'), 10)]);
    }

    public function names(Request $req): Response
    {
        $ids = array_slice(array_filter(explode(',', $req->str('ids'))), 0, 200);
        return $this->json(['names' => User::names($ids)]);
    }

    public function health(Request $req): Response
    {
        try {
            DB::fetchColumn('SELECT 1');
            $db = 'ok';
        } catch (\Throwable $e) {
            $db = 'error';
        }
        return $this->json(['status' => $db === 'ok' ? 'ok' : 'degraded', 'db' => $db, 'version' => APP_VERSION, 'time' => gmdate('c')], $db === 'ok' ? 200 : 503);
    }
}

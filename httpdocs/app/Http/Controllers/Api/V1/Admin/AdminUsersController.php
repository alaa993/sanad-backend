<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminUsersController extends Controller
{
    public function index()
    {
        $payload = Cache::remember('admin:users:list', 30, function () {
            try {
                $query = DB::table('users')
                    ->select('id', 'name', 'email')
                    ->orderByDesc('id')
                    ->limit(200);
                if (Schema::hasColumn('users', 'role')) {
                    $query->addSelect('role');
                }
                if (Schema::hasColumn('users', 'phone')) {
                    $query->addSelect('phone');
                }
                if (Schema::hasColumn('users', 'status')) {
                    $query->addSelect('status');
                }
                if (Schema::hasColumn('users', 'created_at')) {
                    $query->addSelect('created_at');
                }
                $rows = $query->get();
            } catch (\Throwable $e) {
                $rows = [];
            }

            return ['data' => $rows];
        });

        return response()->json($payload);
    }
}

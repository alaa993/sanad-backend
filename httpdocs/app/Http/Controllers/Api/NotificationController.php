<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\PatientTask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $role = $user->role ?? ($user->roles()->value('name') ?? 'patient');
        $now = now();
        $weekEnd = $now->copy()->addDays(7);
        $items = [];
        $id = 1;

        $sessionQuery = Appointment::query()
            ->when($role === 'specialist', fn ($q) => $q->where('specialist_id', $user->id))
            ->when($role === 'organization', fn ($q) => $q->where('organization_id', $user->id))
            ->when($role === 'admin', fn ($q) => $q)
            ->when(!in_array($role, ['specialist', 'organization', 'admin'], true), fn ($q) => $q->where('patient_id', $user->id));

        $upcoming = (clone $sessionQuery)
            ->with(['specialist:id,name', 'patient:id,name', 'organization:id,name'])
            ->whereIn('status', ['pending', 'accepted', 'confirmed', 'in_progress', 'started', 'scheduled', 'upcoming'])
            ->whereBetween('starts_at', [$now, $weekEnd])
            ->orderBy('starts_at')
            ->limit(15)
            ->get();

        foreach ($upcoming as $session) {
            $start = $session->starts_at ?? $session->scheduled_at;
            if ($role === 'specialist') {
                $label = $session->patient->name ?? __('Session');
            } elseif ($role === 'organization') {
                $label = trim(($session->patient->name ?? '') . ' · ' . ($session->specialist->name ?? ''));
                $label = $label !== '·' ? $label : ($session->specialist->name ?? __('Session'));
            } else {
                $label = $session->specialist->name ?? $session->organization->name ?? __('Session');
            }

            $items[] = [
                'id' => $id++,
                'title' => __('Upcoming session'),
                'body' => trim($label . ' · ' . optional($start)->format('Y-m-d H:i')),
                'type' => 'session',
                'created_at' => optional($start)->toIso8601String() ?? $now->toIso8601String(),
                'read' => false,
            ];
        }

        if ($role !== 'admin') {
            $tasks = PatientTask::where('user_id', $user->id)
                ->where('status', 'pending')
                ->orderByRaw('COALESCE(due_at, created_at)')
                ->limit(15)
                ->get();

            foreach ($tasks as $task) {
                $items[] = [
                    'id' => $id++,
                    'title' => __('Pending task'),
                    'body' => $task->title,
                    'type' => 'task',
                    'created_at' => optional($task->due_at ?? $task->created_at)->toIso8601String(),
                    'read' => false,
                ];
            }
        }

        if ($role === 'admin') {
            $pendingSpecs = (int) DB::table('specialist_profiles')->where('status', 'pending')->count();
            if ($pendingSpecs > 0) {
                $items[] = [
                    'id' => $id++,
                    'title' => __('Specialist approvals'),
                    'body' => __(':count pending specialist requests', ['count' => $pendingSpecs]),
                    'type' => 'admin',
                    'created_at' => $now->toIso8601String(),
                    'read' => false,
                ];
            }

            $pendingOrgs = (int) DB::table('organizations')->where('status', 'pending')->count();
            if ($pendingOrgs > 0) {
                $items[] = [
                    'id' => $id++,
                    'title' => __('Organization approvals'),
                    'body' => __(':count pending organization requests', ['count' => $pendingOrgs]),
                    'type' => 'admin',
                    'created_at' => $now->toIso8601String(),
                    'read' => false,
                ];
            }
        }

        return response()->json(['data' => $items]);
    }
}

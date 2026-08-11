<?php

namespace App\Http\Controllers\Api\V1\Patient;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\PatientTask;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Cache;

class PatientTaskController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;
        $cacheKey = "patient:tasks:{$userId}";
        $payload = Cache::remember($cacheKey, 20, function () use ($userId) {
            $tasks = PatientTask::where('user_id', $userId)
                ->orderByRaw('COALESCE(due_at, created_at)')
                ->limit(200)
                ->get();
            return [
                'upcoming' => $tasks->where('status', 'pending')->values(),
                'completed' => $tasks->where('status', 'completed')->values(),
            ];
        });

        return response()->json($payload);
    }

    public function store(Request $request)
    {
        $data = $this->validatePayload($request, partial: false, requireAppointment: true);

        $appointment = Appointment::findOrFail($data['appointment_id']);
        $user = $request->user();
        // المريض نفسه أو الأخصائي صاحب الجلسة
        if ($user->id !== $appointment->patient_id && $user->id !== $appointment->specialist_id) {
            abort(403);
        }
        $data['user_id'] = $appointment->patient_id;

        $task = PatientTask::create($data);
        Cache::forget("patient:tasks:{$data['user_id']}");

        return response()->json($task, 201);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        $task = PatientTask::findOrFail($id);

        // المريض يمكنه إكمال المهام الخاصة به فقط
        if ($task->user_id === $user->id) {
            $data = $request->validate([
                'status' => ['required', Rule::in(['pending','completed'])],
                'notes'  => ['nullable', 'string'],
            ]);
            $payload = [
                'status'          => $data['status'],
                'completion_note' => $data['notes'] ?? null,
            ];
            $payload['completed_at'] = $data['status'] === 'completed' ? now() : null;
            $task->update($payload);
            Cache::forget("patient:tasks:{$task->user_id}");
            return response()->json($task->fresh());
        }

        // الأخصائي صاحب الجلسة يمكنه التعديل
        $appointment = $task->appointment;
        if (!$appointment || $appointment->specialist_id !== $user->id) {
            abort(403);
        }
        $data = $this->validatePayload($request, partial: true, requireAppointment: false);
        if (isset($data['status']) && $data['status'] === 'completed' && !$task->completed_at) {
            $data['completed_at'] = now();
        }
        if (isset($data['status']) && $data['status'] === 'pending') {
            $data['completed_at'] = null;
            $data['completion_note'] = null;
        }

        $task->update($data);
        Cache::forget("patient:tasks:{$task->user_id}");

        return response()->json($task->fresh());
    }

    private function validatePayload(Request $request, bool $partial = false, bool $requireAppointment = false): array
    {
        $rules = [
            'appointment_id' => ($requireAppointment ? 'required' : 'nullable') . '|integer|exists:appointments,id',
            'title' => $partial ? 'sometimes|string|max:255' : 'required|string|max:255',
            'description' => 'nullable|string',
            'due_at' => 'nullable|date',
            'reminder_at' => 'nullable|date',
            'status' => ['nullable', Rule::in(['pending','completed','overdue'])],
            'completion_note' => 'nullable|string',
            'meta' => 'nullable|array',
        ];

        $data = $request->validate($rules);

        if (!$partial && !isset($data['status'])) {
            $data['status'] = 'pending';
        }

        return $data;
    }
}

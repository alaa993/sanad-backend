<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use App\Support\OrganizationResolver;
use App\Services\AccountDeletionService;
use App\Services\SmsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

/**
 * Mobile auth API: register, login, OTP, security question, /me, account deletion.
 * Patients may register without email (placeholder @sanad.local); clients use users.role as source of truth.
 */
class AuthController extends Controller {
    private const SECURITY_QUESTION = 'What is your favorite color?';

    /**
     * Create a user and Sanctum token. Empty email/phone are normalized to null so unique/email rules do not fail on "".
     */
    public function register(Request $req) {
        $deviceName = $req->input('device_name')
            ?? $req->header('X-Device-Id')
            ?? $req->userAgent()
            ?? 'mobile';
        $role = $req->input('role', 'patient');
        $isPatient = $role === 'patient';

        // حقول اختيارية فارغة يجب أن تصبح null حتى لا تفشل قواعد unique/email على "".
        $sanitized = $req->all();
        foreach (['email', 'phone'] as $optional) {
            if (!array_key_exists($optional, $sanitized)) {
                continue;
            }
            $value = is_string($sanitized[$optional]) ? trim($sanitized[$optional]) : $sanitized[$optional];
            $sanitized[$optional] = ($value === '' || $value === null) ? null : $value;
        }
        if (isset($sanitized['name']) && is_string($sanitized['name'])) {
            $sanitized['name'] = trim($sanitized['name']);
        }
        $req->merge($sanitized);

        $data = $req->validate([
            'name'      => 'required|string|min:2|max:120',
            'email'     => ($isPatient ? 'nullable' : 'required').'|email|max:190|unique:users,email',
            'password'  => 'required|string|min:6|max:120',
            'phone'     => 'nullable|string|max:20|unique:users,phone',
            'locale'    => 'nullable|string|max:5',
            'timezone'  => 'nullable|string|max:64',
            'role'      => 'nullable|string|in:patient,specialist,organization,admin'
        ], [
            'password.min' => 'Password must be at least 6 characters (lowercase letters are allowed).',
            'password.required' => 'Password is required.',
            'name.min' => 'Name must be at least 2 characters.',
            'email.unique' => 'This email is already registered.',
            'phone.unique' => 'This phone number is already registered.',
        ]);

        // السماح للمريض بالتسجيل بدون بريد عبر إنشاء بريد وهمي فريد
        if ($isPatient && empty($data['email'])) {
            $data['email'] = 'patient_' . Str::uuid() . '@sanad.local';
        }

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'] ?? null,
            'password' => Hash::make($data['password']),
            'phone'    => $isPatient ? null : ($data['phone'] ?? null),
            'locale'   => $data['locale'] ?? null,
            'timezone' => $data['timezone'] ?? null,
            'role'     => $role,
        ]);
        if ($role === 'organization') {
            OrganizationResolver::provisionForUser($user);
        }
        $token = $user->createToken($deviceName)->plainTextToken;
        $payload = $this->sessionPayload($user);

        // Spatie role sync can wait — clients already use users.role.
        dispatch(function () use ($user, $role) {
            try {
                if (method_exists($user, 'assignRole') && !$user->hasRole($role)) {
                    $user->assignRole($role);
                }
            } catch (\Throwable $e) {
                // ignore: column role is source of truth for mobile clients
            }
        })->afterResponse();

        return response()->json([
            'status' => 'success',
            'message' => 'Registered successfully',
            'token' => $token,
            'user'  => $payload,
        ], 201);
    }

    public function login(Request $req) {
        $deviceName = $req->input('device_name')
            ?? $req->header('X-Device-Id')
            ?? $req->userAgent()
            ?? 'mobile';
        $data = $req->validate([ 'username' => 'required|string', 'password' => 'required|string' ]);
        $user = $this->findUserByIdentifier($data['username']);
        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => ['Invalid credentials.']]);
        }
        $newToken = $user->createToken($deviceName);
        $token = $newToken->plainTextToken;
        $payload = $this->sessionPayload($user);

        // Revoke prior tokens for this device after the response is sent.
        $keepId = $newToken->accessToken->id ?? null;
        dispatch(function () use ($user, $deviceName, $keepId) {
            $q = $user->tokens()->where('name', $deviceName);
            if ($keepId) {
                $q->where('id', '!=', $keepId);
            }
            $q->delete();
        })->afterResponse();

        return response()->json([
            'status' => 'success',
            'message' => 'Login successful',
            'token' => $token,
            'user'  => $payload,
        ]);
    }

    public function phoneLogin(Request $req)
    {
        $data = $req->validate([
            'phone' => 'required|string|max:20',
            'code' => 'required|string|max:8',
            'device_name' => 'nullable|string|max:120',
        ]);
        $deviceName = $data['device_name']
            ?? $req->header('X-Device-Id')
            ?? $req->userAgent()
            ?? 'mobile';
        $cacheKey = 'phone_otp:' . preg_replace('/\D+/', '', $data['phone']);
        $expected = Cache::get($cacheKey);
        if (!$expected && config('app.debug')) {
            $expected = '000000';
        }
        if ((string) $data['code'] !== (string) $expected) {
            throw ValidationException::withMessages(['code' => ['Invalid verification code.']]);
        }
        $user = User::where('phone', $data['phone'])->first();
        if (!$user) {
            $user = User::create([
                'name' => 'Patient ' . substr($data['phone'], -4),
                'email' => 'phone_' . preg_replace('/\D+/', '', $data['phone']) . '@sanad.local',
                'password' => Hash::make(Str::random(16)),
                'phone' => $data['phone'],
                'role' => 'patient',
            ]);
        }
        $user->tokens()->where('name', $deviceName)->delete();
        $token = $user->createToken($deviceName)->plainTextToken;
        Cache::forget($cacheKey);
        return response()->json([
            'status' => 'success',
            'message' => 'Phone login successful',
            'token' => $token,
            'user' => $this->payload($user),
        ]);
    }

    public function phoneRequestOtp(Request $req, SmsService $sms)
    {
        $data = $req->validate(['phone' => 'required|string|max:20']);
        $code = (string) random_int(100000, 999999);
        $cacheKey = 'phone_otp:' . preg_replace('/\D+/', '', $data['phone']);
        $ttl = (int) config('sms.otp.ttl_minutes', 10);
        Cache::put($cacheKey, $code, now()->addMinutes($ttl));
        $sent = $sms->sendOtp($data['phone'], $code, $req->header('Accept-Language'));
        return response()->json([
            'sent' => $sent,
            'debug_code' => config('app.debug') ? $code : null,
        ]);
    }

    public function logout(Request $req) {
        $u = $req->user();
        if ($u) {
            Cache::forget("auth:me:{$u->id}");
            $token = $u->currentAccessToken();
            if ($token) {
                $token->delete();
            }
        }
        return response()->json(['ok'=>true]);
    }

    public function deleteAccount(Request $req, AccountDeletionService $deletion)
    {
        $data = $req->validate([
            'password' => 'required|string',
            'confirm' => 'required|accepted',
        ]);

        $user = $req->user();
        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['Invalid password.'],
            ]);
        }

        try {
            $deletion->delete($user);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => 'Account cannot be deleted via this endpoint.'], 403);
        }

        return response()->json(['deleted' => true, 'message' => 'Account deleted successfully.']);
    }

    public function me(Request $req) {
        $u = $req->user();
        $cacheKey = "auth:me:{$u->id}";
        $payload = Cache::remember($cacheKey, 180, function () use ($u) {
            return $this->payload($u);
        });
        return response()->json($payload);
    }

    private function approvalStatus(User $user): ?string
    {
        $cacheKey = "auth:approval:{$user->id}:{$user->role}";
        return Cache::remember($cacheKey, 30, function () use ($user) {
        if ($user->role === 'specialist') {
            return DB::table('specialist_profiles')->where('user_id', $user->id)->value('status') ?? 'pending';
        }
        if ($user->role === 'organization') {
            return $this->organizationStatus($user) ?? 'pending';
        }
        return 'approved';
        });
    }

    private function organizationStatus(User $user): ?string
    {
        if ($user->role !== 'organization') return null;
        $cacheKey = "auth:orgstatus:{$user->id}";
        return Cache::remember($cacheKey, 30, function () use ($user) {
            $orgId = OrganizationResolver::resolveOrgId($user);
            if (!$orgId) {
                return null;
            }
            return DB::table('organizations')->where('id', $orgId)->value('status') ?? 'pending';
        });
    }

    private function findUserByIdentifier(string $username): ?User
    {
        $username = trim($username);
        if ($username === '') {
            return null;
        }
        if (str_contains($username, '@')) {
            return User::where('email', $username)->first();
        }
        return User::where('phone', $username)->first()
            ?? User::where('email', $username)->first()
            ?? User::where('name', $username)->first();
    }

    private function payload(User $user): array
    {
        return $this->publicPayload($user);
    }

    /** Lean user blob for login/register — avoids Spatie and heavy admin counts. */
    private function sessionPayload(User $user): array
    {
        $role = $user->role ?: 'patient';
        $payload = [
            'id' => $user->id,
            'name' => $user->name,
            'email'=> $user->publicEmail(),
            'phone'=> $user->publicPhone(),
            'locale' => $user->locale,
            'gender' => $user->gender,
            'role' => $role,
            'approval_status' => in_array($role, ['patient', 'admin'], true)
                ? 'approved'
                : $this->approvalStatus($user),
            'organization_status' => $role === 'organization' ? $this->organizationStatus($user) : null,
            'rejection_reason' => $role === 'specialist' ? $this->specialistReason($user) : null,
            'org_rejection_reason' => $role === 'organization' ? $this->orgReason($user) : null,
            'org_profile' => $role === 'organization' ? $this->orgProfile($user) : null,
            'admin_profile' => null,
        ];
        Cache::put("auth:me:{$user->id}", $payload, 180);
        return $payload;
    }

    public function publicPayload(User $user): array
    {
        $role = $user->role ?: 'patient';
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email'=> $user->publicEmail(),
            'phone'=> $user->publicPhone(),
            'locale' => $user->locale,
            'gender' => $user->gender,
            'role' => $role,
            'approval_status' => $this->approvalStatus($user),
            'organization_status' => $this->organizationStatus($user),
            'rejection_reason' => $role === 'specialist' ? $this->specialistReason($user) : null,
            'org_rejection_reason' => $role === 'organization' ? $this->orgReason($user) : null,
            'org_profile' => $role === 'organization' ? $this->orgProfile($user) : null,
            'admin_profile' => $role === 'admin' ? $this->adminProfile() : null,
        ];
    }

    private function orgProfile(User $user): ?array
    {
        $cacheKey = "auth:orgprofile:{$user->id}";
        return Cache::remember($cacheKey, 30, function () use ($user) {
        $orgId = OrganizationResolver::resolveOrgId($user);
        if (!$orgId) {
            return null;
        }
        $org = DB::table('organizations')->where('id', $orgId)->first(['id','name','status','about','review_notes']);
        $specialists = DB::table('organization_user')->where('organization_id', $orgId)->where('role','specialist')->count();
        $members = DB::table('organization_user')->where('organization_id', $orgId)->count();
        $beneficiaries = DB::table('organization_beneficiaries')->where('organization_id', $orgId)->count();
        $wallet = DB::table('wallets')->where(['owner_type'=>'user','owner_id'=>$user->id])->value('points') ?? 0;
        return [
            'id' => $org->id ?? $orgId,
            'name' => $org->name ?? $user->name,
            'status' => $org->status ?? 'pending',
            'review_notes' => $org->review_notes ?? null,
            'members' => $members,
            'specialists' => $specialists,
            'beneficiaries' => $beneficiaries,
            'wallet_points' => (int) $wallet,
            'about' => $org->about ?? null,
        ];
        });
    }

    private function adminProfile(): array
    {
        return Cache::remember('auth:adminprofile', 30, function () {
        $pendingSpecs = DB::table('specialist_profiles')->where('status','pending')->count();
        $pendingOrgs = DB::table('organizations')->where('status','pending')->count();
        $totalUsers = DB::table('users')->count();
        $totalSessions = DB::table('appointments')->count();
        return [
            'pending_specialists' => $pendingSpecs,
            'pending_organizations' => $pendingOrgs,
            'total_users' => $totalUsers,
            'total_sessions' => $totalSessions,
        ];
        });
    }

    private function specialistReason(User $user): ?string
    {
        $cacheKey = "auth:specreason:{$user->id}";
        return Cache::remember($cacheKey, 30, function () use ($user) {
            return DB::table('specialist_profiles')->where('user_id', $user->id)->value('verification_notes');
        });
    }

    private function orgReason(User $user): ?string
    {
        $orgId = OrganizationResolver::resolveOrgId($user);
        if (!$orgId) return null;
        $cacheKey = "auth:orgreason:{$orgId}";
        return Cache::remember($cacheKey, 30, function () use ($orgId) {
            return DB::table('organizations')->where('id', $orgId)->value('review_notes');
        });
    }

    public function saveSecurityAnswer(Request $req)
    {
        $data = $req->validate([
            'username' => 'required|string',
            'security_answer' => 'required|string|min:1',
        ]);
        $user = $this->findUserByUsername($data['username']);
        if (!$user) {
            return response()->json(['ok' => false, 'message' => 'user_not_found'], 404);
        }
        $user->security_question = self::SECURITY_QUESTION;
        $user->security_answer_hash = Hash::make($data['security_answer']);
        $user->save();
        return response()->json(['ok' => true]);
    }

    public function forgotLookup(Request $req)
    {
        $data = $req->validate(['username' => 'required|string']);
        $user = $this->findUserByUsername($data['username']);
        if (!$user) {
            return response()->json(['exists' => false, 'message' => 'user_not_found'], 404);
        }
        return response()->json([
            'exists' => true,
            'name' => $user->name,
            'account_hint' => $this->accountHint($user),
            'security_question' => $user->security_question ?: self::SECURITY_QUESTION,
            'has_security_answer' => !empty($user->security_answer_hash),
        ]);
    }

    public function resetPasswordWithAnswer(Request $req)
    {
        $data = $req->validate([
            'username' => 'required|string',
            'security_answer' => 'required|string|min:1',
            'new_password' => 'required|string|min:6|confirmed',
        ]);
        $user = $this->findUserByUsername($data['username']);
        if (!$user) {
            return response()->json(['ok' => false, 'message' => 'user_not_found'], 404);
        }
        if (empty($user->security_answer_hash)) {
            return response()->json(['ok' => false, 'message' => 'security_answer_not_set'], 422);
        }
        if (!Hash::check($data['security_answer'], $user->security_answer_hash)) {
            return response()->json(['ok' => false, 'message' => 'invalid_security_answer'], 422);
        }
        $user->password = Hash::make($data['new_password']);
        $user->save();
        return response()->json(['ok' => true]);
    }

    private function accountHint(User $user): string
    {
        $email = $user->publicEmail();
        if ($email && !str_contains($email, '@sanad.local')) {
            $parts = explode('@', $email);
            $local = $parts[0];
            if (strlen($local) <= 2) {
                return $email;
            }
            return substr($local, 0, 1) . str_repeat('*', max(1, strlen($local) - 2)) . substr($local, -1) . '@' . ($parts[1] ?? '');
        }
        $phone = $user->publicPhone();
        if ($phone && strlen($phone) >= 4) {
            return str_repeat('*', max(0, strlen($phone) - 4)) . substr($phone, -4);
        }
        return $user->name ?? '';
    }

    private function findUserByUsername(string $username): ?User
    {
        return User::where('email', $username)
            ->orWhere('phone', $username)
            ->orWhere('name', $username)
            ->first();
    }
}

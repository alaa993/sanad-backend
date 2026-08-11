<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PatientIntake;
use App\Models\VentPost;
use App\Models\VentReaction;
use App\Models\VentReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class VentController extends Controller
{
    private function ensurePatient(Request $request): void
    {
        if (($request->user()->role ?? null) !== 'patient') {
            abort(403, 'Only patients can use vent');
        }
    }

    public function index(Request $request)
    {
        $this->ensurePatient($request);
        $userId = $request->user()->id;
        $payload = Cache::remember('vent:latest', 20, function () use ($userId) {
            $posts = VentPost::whereNull('hidden_at')
                ->withCount([
                    'reactions as empathy_count' => fn ($q) => $q->where('type', 'empathy'),
                    'reactions as support_count' => fn ($q) => $q->where('type', 'support'),
                ])
                ->latest()
                ->limit(100)
                ->get();

            $userReactions = VentReaction::where('user_id', $userId)
                ->whereIn('vent_post_id', $posts->pluck('id'))
                ->get()
                ->groupBy('vent_post_id');

            return [
                'data' => $posts->map(fn ($p) => $this->transform($p, $userReactions->get($p->id)))->values(),
            ];
        });

        return response()->json($payload);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'patient') {
            abort(403, 'Only patients can post vent entries');
        }
        $data = $request->validate([
            'body' => 'required|string|max:4000',
        ]);
        $alias = 'صديق مجهول #' . str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT);
        $post = VentPost::create([
            'user_id' => $user->id,
            'alias'   => $alias,
            'body'    => $data['body'],
        ]);
        Cache::forget('vent:latest');

        $intake = PatientIntake::firstOrCreate(['user_id' => $user->id]);
        if (!in_array($intake->onboarding_step, ['vent_done', 'ready'], true)) {
            $intake->onboarding_step = 'vent_done';
            $intake->save();
        }
        Cache::forget("dash:{$user->id}:patient");
        Cache::forget("vent:exists:{$user->id}");

        return response()->json($this->transform($post, collect()), 201);
    }

    public function react(Request $request, $id)
    {
        $this->ensurePatient($request);
        $data = $request->validate([
            'type' => 'required|string|in:empathy,support',
        ]);
        $post = VentPost::whereNull('hidden_at')->findOrFail($id);
        $existing = VentReaction::where([
            'vent_post_id' => $post->id,
            'user_id' => $request->user()->id,
            'type' => $data['type'],
        ])->first();

        if ($existing) {
            $existing->delete();
            $active = false;
        } else {
            VentReaction::create([
                'vent_post_id' => $post->id,
                'user_id' => $request->user()->id,
                'type' => $data['type'],
            ]);
            $active = true;
        }

        Cache::forget('vent:latest');
        $count = VentReaction::where('vent_post_id', $post->id)->where('type', $data['type'])->count();

        return response()->json([
            'type' => $data['type'],
            'active' => $active,
            'count' => $count,
        ]);
    }

    public function report(Request $request, $id)
    {
        $this->ensurePatient($request);
        $data = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);
        $post = VentPost::whereNull('hidden_at')->findOrFail($id);
        $existing = VentReport::where([
            'vent_post_id' => $post->id,
            'user_id' => $request->user()->id,
            'status' => 'open',
        ])->first();
        if ($existing) {
            return response()->json(['reported' => true, 'message' => 'Already reported'], 200);
        }

        VentReport::create([
            'vent_post_id' => $post->id,
            'user_id' => $request->user()->id,
            'reason' => $data['reason'] ?? null,
            'status' => 'open',
        ]);

        return response()->json(['reported' => true], 201);
    }

    public function chat(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'patient') {
            abort(403, 'Only patients can use vent bot');
        }
        $data = $request->validate([
            'message' => 'required|string|max:1000',
            'mood'    => 'nullable|string|max:50',
            'stage'   => 'nullable|string|in:intro,clarify,plan,wrap',
        ]);
        $mood = $data['mood'] ?? 'عام';
        $stage = $data['stage'] ?? 'intro';

        $tipsByMood = [
            'قلق' => [
                'جرّب تمرين تنفس 4-7-8 لتهدئة الجسم.',
                'دوّن أكثر 3 أفكار تقلقك واكتب جانباً ما يمكن التحكم به فيها.',
            ],
            'حزن' => [
                'اختر نشاطاً قصيراً يمنحك شعوراً بالإنجاز (ترتيب ركن صغير، مشي قصير).',
                'تواصل مع شخص داعم ولو برسالة قصيرة.',
            ],
            'غضب' => [
                'خذ استراحة قصيرة بعيداً عن الموقف، ثم اكتب ما يزعجك دون إرسال.',
                'حرّك جسمك: تمارين خفيفة أو مشي سريع لتفريغ التوتر.',
            ],
        ];

        $flow = [
            'intro' => [
                'reply' => 'أنا هنا أسمعك. خذ نفساً بطيئاً وأخبرني بشكل عام: ما الذي يزعجك الآن؟',
                'prompt' => 'اذكر الموقف بإيجاز أو الشعور الأقوى لديك.',
                'next' => 'clarify',
            ],
            'clarify' => [
                'reply' => 'دعنا نوضّح أكثر. ما الأفكار أو اللحظات التي زادت هذا الشعور؟',
                'prompt' => 'اكتب المواقف أو الأفكار المؤذية، وما الذي تتمنى تغييره.',
                'next' => 'plan',
            ],
            'plan' => [
                'reply' => 'لنضع خطوات صغيرة فورية. اختر خطوة أو اثنتين يمكنك تنفيذها خلال 20 دقيقة.',
                'prompt' => 'ما الخطوة الأسهل الآن؟ تنفس، تدوين، تواصل مع شخص داعم، أو استراحة قصيرة؟',
                'next' => 'wrap',
            ],
            'wrap' => [
                'reply' => 'أحسنت المشاركة. تذكر أن فضفضة ليست علاجاً، لكنها مساحة دعم آمنة.',
                'prompt' => 'إذا أردت، اكتب ما ستفعله خلال الساعة القادمة أو كلمة تشجع بها نفسك.',
                'next' => null,
            ],
        ];

        $current = $flow[$stage] ?? $flow['intro'];
        $tips = $tipsByMood[$mood] ?? [
            'قسّم المشكلة إلى أجزاء صغيرة وتعامل مع خطوة واحدة في كل مرة.',
            'امشِ لدقائق أو مارس تنفساً بطيئاً لتخفيف التوتر اللحظي.',
        ];

        return response()->json([
            'reply'      => $current['reply'],
            'sent'       => $data['message'],
            'tips'       => $tips,
            'prompt'     => $current['prompt'],
            'mood'       => $mood,
            'stage'      => $stage,
            'next_stage' => $current['next'],
            'next_prompt'=> $current['next'] ? ($flow[$current['next']]['prompt'] ?? null) : null,
        ]);
    }

    private function transform(VentPost $post, $userReactions = null): array
    {
        $reactions = $userReactions ?? collect();
        $types = $reactions->pluck('type')->flip();

        return [
            'id'            => $post->id,
            'alias'         => $post->alias,
            'body'          => $post->body,
            'created_at'    => optional($post->created_at)->toIso8601String(),
            'empathy_count' => (int) ($post->empathy_count ?? 0),
            'support_count' => (int) ($post->support_count ?? 0),
            'user_empathy'  => $types->has('empathy'),
            'user_support'  => $types->has('support'),
        ];
    }
}

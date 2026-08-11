<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\Realtime;
use App\Models\{Chat, ChatParticipant, Message, GroupSession};
use App\Services\PushNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/**
 * 1:1 and group chat REST: thread list, create, message history, send.
 * Send persists Message, updates last_message, pushes FCM, and emits chat:message via Realtime.
 */
class ChatController extends Controller {
    public function index(Request $req){
        $user = $req->user();
        $cacheKey = "chat:list:{$user->id}";
        $payload = Cache::remember($cacheKey, 10, function () use ($user) {
            $chats = Chat::query()
                ->whereHas('participants', fn($q)=>$q->where('user_id',$user->id))
                ->with(['participants.user:id,name'])
                ->orderByDesc(DB::raw('COALESCE(last_message_at, updated_at)'))
                ->get()
                ->map(function($c){
                    return [
                        'id'=>$c->id,
                        'subject'=>$c->subject,
                        'last_message'=>$c->last_message,
                        'updated_at'=>optional($c->last_message_at ?? $c->updated_at)->toIso8601String(),
                        'participants'=>$c->participants->map(fn($p)=>[
                            'id'=>$p->user->id ?? null, 'name'=>$p->user->name ?? null, 'role'=>$p->role
                        ])->values(),
                        'unread_count'=>0,
                    ];
                })->values();
            return ['data'=>$chats];
        });
        return response()->json($payload);
    }

    public function store(Request $req){
        $data = $req->validate([ 'participant_ids'=>'required|array|min:1', 'subject'=>'nullable|string|max:255' ]);
        $user = $req->user();
        $chat = Chat::create(['subject'=>$data['subject'] ?? null]);
        $ids = array_unique(array_map('intval', $data['participant_ids']));
        $participants = array_values(array_unique(array_merge([$user->id], $ids)));
        foreach($participants as $uid){
            ChatParticipant::create([
                'chat_id'=>$chat->id, 'user_id'=>$uid,
                'role'=>$uid===$user->id ? 'user':'specialist', 'joined_at'=>now()
            ]);
        }
        Cache::forget("chat:list:{$user->id}");
        return response()->json(['chat_id'=>$chat->id], 201);
    }

    public function messages(Request $req, Chat $chat){
        $this->authorizeView($req->user(), $chat->id);
        $chat->loadMissing('participants');
        $since = $req->query('since');
        $q = Message::where('chat_id',$chat->id)->with('sender:id,name');
        if ($since) $q->where('created_at','>',$since);
        if ($since) {
            $msgs = $q->orderBy('id')->limit(200)->get()->map(function($m) use ($chat){
                $senderRole = optional($chat->participants->firstWhere('user_id', $m->sender_id))->role;
                return [
                    'id'=>$m->id, 'chat_id'=>$m->chat_id,
                    'sender'=>['id'=>$m->sender->id ?? null,'name'=>$m->sender->name ?? null,'role'=>$senderRole],
                    'type'=>$m->type, 'body'=>$m->body,
                    'created_at'=>optional($m->created_at)->toIso8601String(),
                ];
            })->values();
            return response()->json(['data'=>$msgs]);
        }
        $cacheKey = "chat:messages:{$chat->id}";
        $payload = Cache::remember($cacheKey, 10, function () use ($q, $chat) {
            $msgs = $q->orderBy('id')->limit(200)->get()->map(function($m) use ($chat){
                $senderRole = optional($chat->participants->firstWhere('user_id', $m->sender_id))->role;
                return [
                    'id'=>$m->id, 'chat_id'=>$m->chat_id,
                    'sender'=>['id'=>$m->sender->id ?? null,'name'=>$m->sender->name ?? null,'role'=>$senderRole],
                    'type'=>$m->type, 'body'=>$m->body,
                    'created_at'=>optional($m->created_at)->toIso8601String(),
                ];
            })->values();
            return ['data'=>$msgs];
        });
        return response()->json($payload);
    }

    /**
     * Persist a message, block patient sends after appointment expiry, emit realtime + push to other participants.
     */
    public function send(Request $req, Chat $chat){
        $this->authorizeView($req->user(), $chat->id);
        $data = $req->validate([ 'type'=>'required|in:text,image', 'body'=>'required|string' ]);

        // حماية زمن الجلسة: إذا كان الشات مرتبطاً بموعد وانتهى وقته أو أُغلق، امنع الإرسال
        $appointment = \App\Models\Appointment::where('chat_id', $chat->id)->first();
        if ($appointment) {
            $now = now();
            $endsAt = $appointment->ends_at ? \Carbon\Carbon::parse($appointment->ends_at) : null;
            $closedAt = $appointment->closed_at ? \Carbon\Carbon::parse($appointment->closed_at) : null;
            $expired = ($endsAt && $now->greaterThan($endsAt)) || $closedAt;
            $isPatient = $appointment->patient_id === $req->user()->id;
            if ($expired && $isPatient) {
                return response()->json(['message' => 'session_time_expired'], 403);
            }
        }

        // أوقف الرسائل في الجلسات الجماعية بعد انتهائها للجميع
        $group = GroupSession::where('chat_id', $chat->id)->first();
        if ($group) {
            $now = now();
            $endAt = $group->end_at ? \Carbon\Carbon::parse($group->end_at) : null;
            if ($endAt && $now->greaterThan($endAt)) {
                return response()->json(['message' => 'group_session_ended'], 403);
            }
        }

        $msg = new Message();
        $msg->chat_id = $chat->id;
        $msg->sender_id = $req->user()->id;
        $msg->type = $data['type'];
        $msg->body = $data['body'];
        $msg->created_at = now();
        $msg->save();

        $chat->last_message = $msg->body; $chat->last_message_at = $msg->created_at; $chat->save();
        Cache::forget("chat:messages:{$chat->id}");
        Cache::forget("chat:list:{$req->user()->id}");

        $senderRole = optional($chat->participants->firstWhere('user_id',$req->user()->id))->role;
        $messagePayload = [
            'id'=>$msg->id,
            'chat_id'=>$msg->chat_id,
            'sender'=>['id'=>$req->user()->id,'name'=>$req->user()->name,'role'=>$senderRole],
            'type'=>$msg->type,
            'body'=>$msg->body,
            'created_at'=>$msg->created_at->toIso8601String()
        ];
        Realtime::emit('chat:message', [
            'room'=>'chat_'.$chat->id,
            'from'=>$req->user()->id,
            'content'=>$msg->body,
            'type'=>$msg->type,
            'id'=>$msg->id,
            'created_at'=>$msg->created_at->getTimestamp()*1000,
            'meta'=>[
                'chatId'=>$chat->id,
                'message'=>$messagePayload
            ]
        ], true);

        $chat->loadMissing('participants');
        $senderName = $req->user()->name ?? __('New message');
        $preview = mb_strlen($msg->body) > 120 ? mb_substr($msg->body, 0, 120) . '…' : $msg->body;
        foreach ($chat->participants as $participant) {
            if ((int) $participant->user_id === (int) $req->user()->id) {
                continue;
            }
            app(PushNotificationService::class)->notifyUser(
                (int) $participant->user_id,
                $senderName,
                $preview,
                ['type' => 'chat', 'chat_id' => (string) $chat->id]
            );
        }

        return response()->json([
            'id'=>$msg->id,'chat_id'=>$msg->chat_id,
            'sender'=>['id'=>$req->user()->id,'name'=>$req->user()->name,'role'=>optional($chat->participants->firstWhere('user_id',$req->user()->id))->role],
            'type'=>$msg->type,'body'=>$msg->body,'created_at'=>$msg->created_at->toIso8601String()
        ], 201);
    }

    private function authorizeView($user, $chatId){
        if (ChatParticipant::where('chat_id',$chatId)->where('user_id',$user->id)->exists()) {
            return;
        }
        // Fallback: إذا كان الشات مرتبطًا بموعد والجهاز مستخدم ضمن الموعد، أضفه تلقائيًا
        $appt = \App\Models\Appointment::where('chat_id', $chatId)->first();
        if ($appt && in_array($user->id, array_filter([$appt->patient_id, $appt->specialist_id, $appt->organization_id]), true)) {
            $role = 'user';
            if ($appt->specialist_id === $user->id) {
                $role = 'specialist';
            } elseif ($appt->organization_id === $user->id) {
                $role = 'support';
            }
            ChatParticipant::firstOrCreate(
                ['chat_id' => $chatId, 'user_id' => $user->id],
                ['role' => $role, 'joined_at' => now()]
            );
            return;
        }
        abort(403, 'Not in chat');
    }
}

<?php
namespace App\Http\Controllers\Api\V1\Specialist;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\SpecialistProfile;
class SpecialistProfileController extends Controller {
  public function show(Request $r){
    $sp = SpecialistProfile::query()->where('user_id', $r->user()->id)->first();
    if (!$sp) {
      $sp = SpecialistProfile::create(['user_id' => $r->user()->id]);
    }
    $sp->loadMissing('user:id,name,email,avatar');
    $data = $sp->toArray();
    if (empty($data['languages'])) {
      $data['languages'] = [];
    }
    $data['name'] = $sp->user->name ?? null;
    $data['email'] = $sp->user->email ?? null;
    $data['avatar'] = $sp->user->avatar ?? null;
    $data['requires_avatar'] = empty($sp->user->avatar);
    return response()->json($data);
  }
  public function update(Request $r){
    $rules = [
      'name'=>'nullable|string|min:2|max:120',
      'specialty'=>'nullable|string',
      'languages'=>'nullable|array',
      'languages.*'=>'nullable|string|max:30',
      'years_exp'=>'nullable|integer|min:0',
      'accepting_new'=>'nullable|boolean',
      'bio'=>'nullable|array',
      'rate_cents'=>'nullable|integer|min:0',
      'currency'=>'nullable|string|size:3',
      'avatar'=>['nullable','image','max:5120'],
    ];
    if (empty($r->user()->avatar)) {
      $rules['avatar'][] = 'required';
    }
    $data=$r->validate($rules);
    if (!empty($data['name'])) {
      $r->user()->update(['name' => $data['name']]);
      unset($data['name']);
    }
    $sp = SpecialistProfile::firstOrCreate(['user_id'=>$r->user()->id],[]);
    $sp->update($data);
    if ($r->hasFile('avatar')) {
      $path = $r->file('avatar')->store('avatars', 'public');
      $r->user()->avatar = Storage::disk('public')->url($path);
      $r->user()->save();
    }
    return response()->json(['ok'=>true, 'avatar'=>$r->user()->avatar]);
  }
  public function resubmit(Request $r){
    if ($r->user()->role !== 'specialist') {
      abort(403);
    }
    $sp = SpecialistProfile::firstOrCreate(['user_id'=>$r->user()->id],[]);
    $sp->status = 'pending';
    $sp->verification_notes = null;
    $sp->save();
    return response()->json(['ok'=>true,'status'=>'pending']);
  }

  public function uploadAvatar(Request $r){
    $data = $r->validate([
      'avatar' => ['required','image','max:5120'],
    ]);
    $path = $r->file('avatar')->store('avatars', 'public');
    $r->user()->avatar = Storage::disk('public')->url($path);
    $r->user()->save();
    return response()->json(['url' => $r->user()->avatar]);
  }
}

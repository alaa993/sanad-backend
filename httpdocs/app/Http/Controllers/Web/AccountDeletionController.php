<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccountDeletionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AccountDeletionController extends Controller
{
    public function show()
    {
        return view('pages.delete-account');
    }

    public function destroy(Request $request, AccountDeletionService $deletion)
    {
        $data = $request->validate([
            'email' => 'required|string|max:255',
            'password' => 'required|string',
            'confirm' => 'accepted',
        ], [
            'confirm.accepted' => 'يجب تأكيد رغبتك في حذف الحساب بشكل نهائي.',
        ]);

        $email = trim($data['email']);
        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة.',
            ]);
        }

        if (strcasecmp($user->role ?? '', 'admin') === 0) {
            throw ValidationException::withMessages([
                'email' => 'لا يمكن حذف حسابات الإدارة من هذه الصفحة. تواصل مع الدعم الفني.',
            ]);
        }

        try {
            $deletion->delete($user);
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages(['email' => 'تعذّر إتمام الحذف. تواصل مع الدعم.']);
        }

        return redirect()
            ->route('delete-account')
            ->with('deleted', true);
    }
}

<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PageController extends Controller
{
    public function home()
    {
        return view('pages.home');
    }

    public function privacy()
    {
        return view('pages.privacy');
    }

    public function terms()
    {
        return view('pages.terms');
    }

    public function contact()
    {
        return view('pages.contact');
    }

    public function submitContact(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:190',
            'message' => 'required|string|max:5000',
        ]);

        $to = config('sanad.support_email', 'support@sanad.app');
        $body = "رسالة تواصل من الموقع\n"
            . "الاسم: {$data['name']}\n"
            . "البريد: {$data['email']}\n\n"
            . $data['message'];

        try {
            Mail::raw($body, function ($message) use ($to, $data) {
                $message->to($to)
                    ->replyTo($data['email'], $data['name'])
                    ->subject('Sanad — رسالة تواصل: ' . $data['name']);
            });
        } catch (\Throwable $e) {
            Log::warning('contact_form_failed', ['error' => $e->getMessage()]);
            return back()->withInput()->with('error', 'تعذر إرسال الرسالة حالياً. راسلنا مباشرة على البريد.');
        }

        return back()->with('success', 'شكراً لك — استلمنا رسالتك وسنرد قريباً.');
    }
}

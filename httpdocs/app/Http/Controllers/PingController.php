<?php

namespace App\Http\Controllers;

class PingController extends Controller
{
    public function ping()
    {
        return $this->ok([
            'app'     => 'Sanad',
            'version' => '1.0.0',
            'color'   => '#2F55A5',
            'time'    => now(),
        ]);
    }

    public function bootstrap()
    {
        $base = rtrim(config('app.url'), '/');

        return $this->ok([
            'brand'   => ['name' => config('sanad.company_name', 'Sanad'), 'primary' => '#2F55A5', 'accent' => '#ED228B'],
            'locales' => ['ar', 'en', 'tr'],
            'links'   => [
                'website'        => $base,
                'privacy'        => $base . config('sanad.urls.privacy'),
                'terms'          => $base . config('sanad.urls.terms'),
                'contact'        => $base . config('sanad.urls.contact'),
                'delete_account' => $base . config('sanad.urls.delete_account'),
                'app_store'      => config('sanad.app_store_url'),
                'play_store'     => config('sanad.play_store_url'),
            ],
            'support_email' => config('sanad.support_email'),
        ]);
    }
}

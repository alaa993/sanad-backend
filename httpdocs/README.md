# Sanad — Phase 7 (Chat & Support) — Realtime Ready (Polling by default)
Generated: 2025-11-04

This package targets **Laravel 11 + Android (Java MVVM) + iOS (SwiftUI)**.
**Recommended option** for most deployments: *Polling Realtime Ready* now, then enable WebSockets later when infra is ready.

## Why this choice?
- Works immediately with your current stack (no WS server required).
- Clean upgrade path: keep APIs/events as-is; just plug Laravel Echo + WebSocket server later.

## Laravel setup (quick)
1) Copy `laravel/` into your project.
2) In `routes/api.php`: `require __DIR__.'/api_chat.php';`
3) Merge `routes/channels_chat.php` into your `routes/channels.php`.
4) Run: `php artisan migrate`.

### Optional: Enable WebSockets later
- `composer require beyondcode/laravel-websockets`
- Configure broadcasting + WebSockets, then run `php artisan websockets:serve`.
- Mobile apps already structured to switch from polling to WS with minimal changes.

## Android setup
- Copy `android/app/src/main/java/com/sanad/app/feature/chat/*` and `android/app/src/main/res/layout/*`.
- Add `ChatListFragment` & `ChatRoomFragment` to your nav graph and link from Home.
- Uses existing `ApiClient` with Sanctum token.

## iOS setup
- Copy `ios/Sanad/Features/Chat/*` into your target.
- Set API base URL + token provider.

## Endpoints
GET  /api/v1/chats
GET  /api/v1/chats/{id}/messages?since=ISO8601
POST /api/v1/chats/{id}/messages   ({ type:'text', body:'...' })
POST /api/v1/chats                    ({ participant_ids:[...], subject? })

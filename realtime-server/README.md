## Sanad Realtime Server

This folder contains the standalone Node.js + Socket.IO gateway that powers every realtime feature in Sanad (private/group chat, community feed, WebRTC signaling, notifications, role dashboards, session rooms).

### Requirements
- Node.js 18+ (Plesk Node extension or system installation)
- Optional Redis (recommended for horizontal scaling) – set `REDIS_URL` or `REDIS_HOST`.
- Reverse proxy (NGINX) mapping `/socket/` to the Node server on port `3000`.

### Installation & Run
```bash
cd realtime-server
npm install
# development
npm run dev
# production
PORT=3000 SOCKET_PATH=/socket/ SOCKET_ALLOWED_ORIGINS=https://domain.com npm run start
```

### Environment Variables
| Variable | Description | Default |
|----------|-------------|---------|
| `PORT` | Node server port | `3000` |
| `SOCKET_PATH` | Public Socket.IO path | `/socket/` |
| `SOCKET_ALLOWED_ORIGINS` | CSV of allowed origins for CORS | `*` |
| `REDIS_URL` / `REDIS_HOST` | Enable Redis adapter for multi-instance deployments | _disabled_ |

### NGINX Reverse Proxy (Plesk > Apache & Nginx > Additional directives)
```nginx
location /socket/ {
    proxy_pass http://127.0.0.1:3000/socket/;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_cache_bypass $http_upgrade;
}
```

### Room Naming Conventions
| Context | Room format |
|---------|-------------|
| Private chat | `private_chat_{minUserId}_{maxUserId}` |
| Group chat | `group_{groupId}` |
| User-specific notifications | `user_{userId}` |
| Role broadcast | `role_{role}` (patient, specialist, org, admin) |
| Community post | `post_{postId}` |
| Session room | `session_{sessionId}` |

### Event Catalogue

| Event | Direction | Payload |
|-------|-----------|---------|
| `socket:ready` | server → client | `{ socketId, userId, role }` |
| `join` / `leave` | bidirectional | `{ room }` |
| `chat:typing` | client → room | `{ to, room?, text? }` |
| `chat:message` | client ↔ room | `{ id?, to, room?, type, content, attachments, meta }` |
| `chat:delivered`, `chat:seen` | client ↔ room | `{ messageId, to, room? }` |
| `group:join`, `group:leave` | client ↔ room | `{ groupId }` |
| `group:message` | client ↔ room | `{ groupId, content, attachments, meta }` |
| `presence:status` | client ↔ server | `{ status }` |
| `video:offer`, `video:answer`, `video:ice` | client ↔ room | `{ sessionId?, room?, sdp/candidate }` |
| `call:start`, `call:end` | client ↔ room | `{ sessionId?, room?, meta }` |
| `community:post` | client ↔ global | `{ id, title, body, media }` |
| `community:comment` | client ↔ `post_{id}` | `{ postId, comment }` |
| `community:like` | client ↔ `post_{id}` | `{ postId, liked }` |
| `notify:event` | client ↔ `user_{id}` | `{ targetUserId, type, data }` |
| `session:join`, `session:leave` | client ↔ `session_{id}` | `{ sessionId }` |
| `session:status` | client ↔ `session_{id}` | `{ sessionId, status, meta }` |
| `role:event` | client ↔ `role_{name}` | `{ role, type, data }` |
| `presence:update` | server → all | `{ userId, online, lastActive, roles }` |

All payloads automatically include auditing fields (`from`, `at`) when broadcast from the server.

### Android (Java) Integration
```java
import io.socket.client.IO;
import io.socket.client.Socket;
import io.socket.emitter.Emitter;

public class RealtimeClient {
    private Socket socket;

    public void connect(String token, String userId, String role) {
        try {
            IO.Options opts = new IO.Options();
            opts.path = "/socket/";
            opts.forceNew = true;
            opts.reconnection = true;
            opts.query = "userId=" + userId + "&role=" + role + "&token=" + token;
            socket = IO.socket("https://domain.com", opts);
        } catch (URISyntaxException e) {
            throw new RuntimeException(e);
        }
        socket.on(Socket.EVENT_CONNECT, args -> Log.d("Realtime", "connected"));
        socket.on("chat:message", args -> handleChatMessage((JSONObject) args[0]));
        socket.on("community:post", args -> handlePost((JSONObject) args[0]));
        socket.on("video:offer", args -> signalingClient.onOffer((JSONObject) args[0]));
        socket.connect();
    }

    public void joinPrivateRoom(String otherUserId) {
        JSONObject payload = new JSONObject();
        payload.put("room", "private_chat_" + sortIds(userId, otherUserId));
        socket.emit("join", payload);
    }

    public void sendChatMessage(String room, JSONObject message) {
        message.put("room", room);
        socket.emit("chat:message", message, (Ack) args -> Log.d("Realtime", "sent"));
    }
}
```

### Laravel Integration Notes
- Laravel controllers keep saving messages/posts/comments to MySQL.
- After persisting, make an HTTP call (or use socket client) to the Node server to emit the proper event. A lightweight approach is to call `/socket/notify` endpoint or use `socket.io-client` Node module inside an Artisan command.
- Use Laravel queues to push heavy notification jobs; once processed, trigger `notify:event` to `user_{id}`.

### Production Checklist
1. `npm install --production`
2. `pm2 start server.js --name sanad-realtime`
3. Confirm `https://domain.com/socket/?EIO=4&transport=polling` upgrades successfully.
4. Monitor logs `pm2 logs sanad-realtime`.

/**
 * Sanad Realtime Server
 * ---------------------
 * Handles realtime transport (chat, community feed, WebRTC signaling, notifications).
 * Laravel remains REST-only. All realtime events flow through this Socket.IO gateway.
 */

const express = require('express');
const http = require('http');
const cors = require('cors');
const { Server } = require('socket.io');
const { createAdapter } = require('@socket.io/redis-adapter');
const { createClient } = require('redis');

// --- Environment ------------------------------------------------------------
const PORT = Number(process.env.PORT || 3000);
const HOST = process.env.HOST || '0.0.0.0';
const SOCKET_PATH = process.env.SOCKET_PATH || '/socket/';
const AUTH_TOKEN = process.env.REALTIME_SOCKET_TOKEN || process.env.SOCKET_AUTH_TOKEN || '';
const ALLOWED_ORIGINS = (process.env.SOCKET_ALLOWED_ORIGINS || '')
  .split(',')
  .map((o) => o.trim())
  .filter(Boolean);
const USE_REDIS = Boolean(process.env.REDIS_URL || process.env.REDIS_HOST);
const PRESENCE_THROTTLE_MS = Number(process.env.PRESENCE_THROTTLE_MS || 500);
const COMMUNITY_GLOBAL_EVENTS = process.env.COMMUNITY_GLOBAL_EVENTS !== 'false';

// --- Express Bootstrapping --------------------------------------------------
const app = express();
app.use(cors({ origin: ALLOWED_ORIGINS.length ? ALLOWED_ORIGINS : undefined }));
app.use(express.json({ limit: '1mb' }));
app.get('/health', (_, res) => res.json({
  status: 'ok',
  uptime: process.uptime(),
  path: SOCKET_PATH,
  connectExample: `${SOCKET_PATH}?EIO=4&transport=polling`,
}));

app.get('/realtime/info', (_, res) => res.json({
  ok: true,
  service: 'sanad-realtime',
  socketPath: SOCKET_PATH,
  hint: 'Use a Socket.IO client. Do not open /socket/ in a browser without ?EIO=4&transport=...',
  pollingExample: `${SOCKET_PATH}?EIO=4&transport=polling`,
}));

function pickHandshakeValue(auth = {}, query = {}, key) {
  const raw = auth[key] ?? query[key];
  if (raw == null) return '';
  if (Array.isArray(raw)) return String(raw[0] ?? '').trim();
  return String(raw).trim();
}

/** Read token/userId/role from Socket.IO auth object or query (mobile clients use both). */
function handshakeContext(socket) {
  const auth = socket.handshake.auth || {};
  const query = socket.handshake.query || {};
  return {
    token: pickHandshakeValue(auth, query, 'token'),
    userId: pickHandshakeValue(auth, query, 'userId'),
    role: pickHandshakeValue(auth, query, 'role') || 'guest',
    auth,
    query,
  };
}

/** Prefer explicit payload.room; otherwise derive chat_{id} from chatId/chat_id/meta. */
function resolveRoom(payload = {}) {
  if (payload.room) return payload.room;
  if (payload.meta?.chatId) return `chat_${payload.meta.chatId}`;
  if (payload.chat_id) return `chat_${payload.chat_id}`;
  if (payload.chatId) return `chat_${payload.chatId}`;
  return null;
}

/**
 * Laravel → clients fan-out. Routes by event name into chat_/session_/community_/user_ rooms
 * (or global emit for community when COMMUNITY_GLOBAL_EVENTS is on).
 */
function broadcastServerEvent(event, payload = {}) {
  const timestamp = Date.now();
  const enriched = { ...payload, at: timestamp };

  switch (event) {
    case 'chat:message': {
      const room = resolveRoom(payload);
      if (!room) {
        console.warn('[Realtime] chat:message missing room', payload);
        return false;
      }
      const message = {
        id: payload.id || `${room}_${timestamp}`,
        room,
        from: payload.from || 'system',
        to: payload.to,
        type: payload.type || 'text',
        content: payload.content ?? payload.body,
        attachments: payload.attachments || [],
        createdAt: payload.created_at || timestamp,
        meta: payload.meta || {},
      };
      io.to(room).emit('chat:message', message);
      return true;
    }
    case 'community:post':
    case 'community:comment':
    case 'community:like': {
      if (COMMUNITY_GLOBAL_EVENTS) {
        io.emit(event, enriched);
      } else if (payload.postId) {
        io.to(`post_${payload.postId}`).emit(event, enriched);
      } else if (payload.communityId) {
        io.to(`community_${payload.communityId}`).emit(event, enriched);
      } else {
        io.emit(event, enriched);
      }
      return true;
    }
    case 'session:status':
    case 'session:presence': {
      if (payload.sessionId) {
        io.to(`session_${payload.sessionId}`).emit(event, enriched);
      } else if (payload.room) {
        io.to(payload.room).emit(event, enriched);
      } else {
        io.emit(event, enriched);
      }
      // Also fan-out to participant user rooms so lists refresh without joining the session room.
      const userIds = [
        payload.patientId,
        payload.specialistId,
        payload.patient_id,
        payload.specialist_id,
        payload.meta?.patientId,
        payload.meta?.specialistId,
        payload.meta?.patient_id,
        payload.meta?.specialist_id,
      ].filter((id) => id != null && String(id).length > 0);
      for (const uid of new Set(userIds.map((id) => String(id)))) {
        io.to(`user_${uid}`).emit(event, enriched);
        io.to(`user_${uid}`).emit('notify:event', {
          type: 'session:status',
          data: {
            sessionId: payload.sessionId,
            status: payload.status,
            ...(payload.meta || {}),
          },
          at: Date.now(),
        });
      }
      return true;
    }
    case 'library:updated': {
      io.emit(event, enriched);
      return true;
    }
    default: {
      if (payload.room) {
        io.to(payload.room).emit(event, enriched);
      } else if (payload.targetUserId) {
        io.to(`user_${payload.targetUserId}`).emit(event, enriched);
      } else {
        io.emit(event, enriched);
      }
      return true;
    }
  }
}

app.post('/internal/emit', (req, res) => {
  const headerToken = req.headers['x-realtime-token'] || req.headers['authorization'];
  const token = String(headerToken || '')
    .replace(/^Bearer\s+/i, '')
    .trim();
  if (AUTH_TOKEN && token !== AUTH_TOKEN) {
    return res.status(403).json({ ok: false, error: 'forbidden' });
  }
  const { event, payload } = req.body || {};
  if (!event) {
    return res.status(422).json({ ok: false, error: 'event_required' });
  }
  const ok = broadcastServerEvent(String(event), payload || {});
  return res.json({ ok: Boolean(ok) });
});

const server = http.createServer(app);

// --- Socket.IO --------------------------------------------------------------
const io = new Server(server, {
  path: SOCKET_PATH,
  cors: {
    origin: ALLOWED_ORIGINS.length ? ALLOWED_ORIGINS : true,
    credentials: true,
    methods: ['GET', 'POST']
  },
  allowEIO3: true,
  transports: ['polling', 'websocket'],
});

(async () => {
  if (!USE_REDIS) return;
  const url = process.env.REDIS_URL;
  const host = process.env.REDIS_HOST || '127.0.0.1';
  const port = process.env.REDIS_PORT || 6379;
  const password = process.env.REDIS_PASSWORD || undefined;

  const pubClient = createClient({
    url: url ?? undefined,
    socket: url ? undefined : { host, port },
    password
  });
  const subClient = pubClient.duplicate();
  await Promise.all([pubClient.connect(), subClient.connect()]);
  io.adapter(createAdapter(pubClient, subClient));
  console.log('[Realtime] Redis adapter enabled.');
})().catch((err) => {
  console.error('[Realtime] Failed to connect to Redis adapter', err);
});

/** Auth middleware: require userId; system sockets need REALTIME token; app tokens must be non-trivial when AUTH_TOKEN is set. */
io.use((socket, next) => {
  const { token, userId, role } = handshakeContext(socket);

  if (!userId) {
    return next(new Error('missing_user_id'));
  }

  if (userId === 'system' && role === 'system') {
    if (!AUTH_TOKEN || token === AUTH_TOKEN) {
      return next();
    }
    return next(new Error('invalid_system_token'));
  }

  if (AUTH_TOKEN && token.length < 10) {
    return next(new Error('missing_app_token'));
  }

  return next();
});

// --- Presence Tracking ------------------------------------------------------
const presence = new Map(); // userId -> { sockets:Set, roles:Set, lastActive }
const presenceEmitState = new Map(); // userId -> { lastEmitAt:number, timer:NodeJS.Timeout|null }

function registerPresence(userId, role, socketId) {
  const entry = presence.get(userId) ?? { sockets: new Set(), roles: new Set(), lastActive: Date.now() };
  entry.sockets.add(socketId);
  if (role) entry.roles.add(role);
  entry.lastActive = Date.now();
  presence.set(userId, entry);
}

function removePresence(userId, socketId) {
  const entry = presence.get(userId);
  if (!entry) return;
  entry.sockets.delete(socketId);
  entry.lastActive = Date.now();
  if (!entry.sockets.size) {
    presence.delete(userId);
    const state = presenceEmitState.get(userId);
    if (state?.timer) clearTimeout(state.timer);
    presenceEmitState.delete(userId);
  }
}

function emitPresence(userId) {
  const now = Date.now();
  const state = presenceEmitState.get(userId) ?? { lastEmitAt: 0, timer: null };
  const elapsed = now - state.lastEmitAt;

  const doEmit = () => {
    const entry = presence.get(userId);
    io.emit('presence:update', {
      userId,
      online: Boolean(entry),
      lastActive: entry?.lastActive ?? Date.now(),
      roles: entry ? [...entry.roles] : []
    });
    state.lastEmitAt = Date.now();
    state.timer = null;
    presenceEmitState.set(userId, state);
  };

  if (!PRESENCE_THROTTLE_MS || elapsed >= PRESENCE_THROTTLE_MS) {
    if (state.timer) clearTimeout(state.timer);
    doEmit();
    return;
  }

  if (!state.timer) {
    state.timer = setTimeout(doEmit, PRESENCE_THROTTLE_MS - elapsed);
    presenceEmitState.set(userId, state);
  }
}

function buildPrivateRoom(userA, userB) {
  return `private_chat_${[userA, userB].sort().join('_')}`;
}

// --- Connection Handler -----------------------------------------------------
io.on('connection', (socket) => {
  const ctx = handshakeContext(socket);
  const userId = ctx.userId || `guest_${socket.id}`;
  const role = ctx.role || 'guest';
  const auth = ctx.auth;

  console.log(`[Realtime] ${socket.id} connected as ${userId} (${role})`);
  registerPresence(userId, role, socket.id);
  emitPresence(userId);

  socket.join(`user_${userId}`);
  socket.join(`role_${role}`);

  const autoRooms = Array.isArray(auth.rooms) ? auth.rooms : [];
  autoRooms.forEach((room) => socket.join(room));

  socket.emit('socket:ready', { socketId: socket.id, userId, role });

  // Clients join chat_/session_/community_ rooms after REST loads; ack returns the rooms joined.
  socket.on('join', ({ room, rooms = [], meta = {} } = {}, ack) => {
    const joining = [];
    if (room) joining.push(room);
    joining.push(...rooms);
    joining
      .filter(Boolean)
      .forEach((r) => {
        socket.join(r);
        console.log(`[Realtime] ${userId} joined ${r}`);
      });
    ack?.({ ok: true, joined: joining, meta });
  });

  socket.on('leave', ({ room } = {}, ack) => {
    if (room) {
      socket.leave(room);
      ack?.({ ok: true });
    } else {
      ack?.({ ok: false, error: 'room required' });
    }
  });

  // Private chat -------------------------------------------------------------
  socket.on('chat:typing', (payload = {}) => {
    const room = payload.room || buildPrivateRoom(userId, payload.to);
    io.to(room).except(`user_${userId}`).emit('chat:typing', { ...payload, room, userId });
  });

  socket.on('chat:message', (payload = {}, ack) => {
    const timestamp = Date.now();
    const room = resolveRoom(payload) || buildPrivateRoom(userId, payload.to);
    const message = {
      id: payload.id || `${room}_${timestamp}`,
      room,
      from: userId,
      to: payload.to,
      type: payload.type || 'text',
      content: payload.content ?? payload.body,
      attachments: payload.attachments || [],
      createdAt: timestamp,
      meta: payload.meta || {},
    };
    io.to(room).except(`user_${userId}`).emit('chat:message', message);
    ack?.({ ok: true, message });
  });

  socket.on('chat:delivered', (payload = {}) => {
    const room = payload.room || buildPrivateRoom(userId, payload.to);
    io.to(room).emit('chat:delivered', { ...payload, room, by: userId, at: Date.now() });
  });

  socket.on('chat:seen', (payload = {}) => {
    const room = payload.room || buildPrivateRoom(userId, payload.to);
    io.to(room).emit('chat:seen', { ...payload, room, by: userId, at: Date.now() });
  });

  // Group chat ---------------------------------------------------------------
  socket.on('group:join', ({ groupId }) => {
    if (!groupId) return;
    const room = `group_${groupId}`;
    socket.join(room);
    const count = io.sockets.adapter.rooms.get(room)?.size ?? 0;
    io.to(room).emit('group:presence', { groupId, userId, action: 'join', count, at: Date.now() });
  });

  socket.on('group:leave', ({ groupId }) => {
    if (!groupId) return;
    const room = `group_${groupId}`;
    socket.leave(room);
    const count = io.sockets.adapter.rooms.get(room)?.size ?? 0;
    io.to(room).emit('group:presence', { groupId, userId, action: 'leave', count, at: Date.now() });
  });

  socket.on('group:message', ({ groupId, content, attachments = [], meta = {} } = {}) => {
    if (!groupId) return;
    const room = `group_${groupId}`;
    const payload = {
      id: `${room}_${Date.now()}`,
      groupId,
      from: userId,
      content,
      attachments,
      meta,
      createdAt: Date.now()
    };
    io.to(room).emit('group:message', payload);
  });

  socket.on('presence:status', ({ status }) => {
    io.emit('presence:status', { userId, status, at: Date.now() });
  });

  // WebRTC Signaling ---------------------------------------------------------
  ['video:offer', 'video:answer', 'video:ice', 'call:start', 'call:end'].forEach((event) => {
    socket.on(event, (payload = {}) => {
      const room = payload.room || (payload.sessionId ? `session_${payload.sessionId}` : undefined);
      if (!room) return;
      io.to(room).emit(event, { ...payload, from: userId, at: Date.now() });
    });
  });

  // Community feed -----------------------------------------------------------
  socket.on('community:post', (payload = {}) => {
    const eventPayload = { ...payload, authorId: userId, at: Date.now() };
    if (COMMUNITY_GLOBAL_EVENTS) {
      io.emit('community:post', eventPayload);
    } else if (payload.id) {
      io.to(`post_${payload.id}`).emit('community:post', eventPayload);
    }
  });

  socket.on('community:comment', (payload = {}) => {
    const eventPayload = { ...payload, authorId: userId, at: Date.now() };
    if (COMMUNITY_GLOBAL_EVENTS) {
      io.emit('community:comment', eventPayload);
    } else if (payload.postId) {
      io.to(`post_${payload.postId}`).emit('community:comment', eventPayload);
    }
  });

  socket.on('community:like', (payload = {}) => {
    const eventPayload = { ...payload, userId, at: Date.now() };
    if (COMMUNITY_GLOBAL_EVENTS) {
      io.emit('community:like', eventPayload);
    } else if (payload.postId) {
      io.to(`post_${payload.postId}`).emit('community:like', eventPayload);
    }
  });

  // Notifications ------------------------------------------------------------
  socket.on('notify:event', ({ targetUserId, type, data = {} } = {}) => {
    if (!targetUserId) return;
    io.to(`user_${targetUserId}`).emit('notify:event', { type, data, from: userId, at: Date.now() });
  });

  // Session rooms ------------------------------------------------------------
  socket.on('session:join', ({ sessionId }) => {
    if (!sessionId) return;
    const room = `session_${sessionId}`;
    socket.join(room);
    io.to(room).emit('session:presence', { sessionId, userId, action: 'join', at: Date.now() });
  });

  socket.on('session:leave', ({ sessionId }) => {
    if (!sessionId) return;
    const room = `session_${sessionId}`;
    socket.leave(room);
    io.to(room).emit('session:presence', { sessionId, userId, action: 'leave', at: Date.now() });
  });

  socket.on('session:status', ({ sessionId, status, meta = {} } = {}) => {
    if (!sessionId) return;
    io.to(`session_${sessionId}`).emit('session:status', { sessionId, status, meta, by: userId, at: Date.now() });
  });

  // Role-based broadcasts ----------------------------------------------------
  socket.on('role:event', ({ role: targetRole, type, data = {} } = {}) => {
    if (!targetRole) return;
    io.to(`role_${targetRole}`).emit('role:event', { type, data, from: userId, at: Date.now() });
  });

  // Cleanup ------------------------------------------------------------------
  socket.on('disconnect', () => {
    console.log(`[Realtime] ${socket.id} disconnected (${userId})`);
    removePresence(userId, socket.id);
    emitPresence(userId);
  });
});

server.listen(PORT, HOST, () => {
  console.log(`[Realtime] Socket.IO server listening on ${HOST}:${PORT} (path ${SOCKET_PATH}).`);
  if (AUTH_TOKEN) {
    console.log('[Realtime] Auth token enforcement enabled.');
  } else {
    console.warn('[Realtime] WARNING: REALTIME_SOCKET_TOKEN not set — system emit endpoint is open.');
  }
});

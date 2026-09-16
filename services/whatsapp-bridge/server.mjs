import express from 'express';
import cors from 'cors';
import path from 'path';
import fs from 'fs';
import QRCode from 'qrcode';
import pino from 'pino';
import makeWASocket, {
  useMultiFileAuthState,
  makeCacheableSignalKeyStore,
  Browsers,
  DisconnectReason,
  fetchLatestBaileysVersion,
  isJidStatusBroadcast,
  isJidGroup,
  isJidNewsletter,
  WAProto
} from '@whiskeysockets/baileys';

// Configuration
const PORT = 3210;
const SESSION_BASE_PATH = path.resolve(process.cwd(), '../../storage/app/whatsapp-sessions');

if (!fs.existsSync(SESSION_BASE_PATH)) {
  fs.mkdirSync(SESSION_BASE_PATH, { recursive: true });
}

const app = express();
app.use(cors());
app.use(express.json({ limit: '50mb' }));

const sessions = new Map();
const lidToPhoneMap = new Map();
const phoneToLidMap = new Map();
const messageStore = new Map();
const outboundBotMessageIds = new Set();
const logger = pino({ level: 'silent' });

// Token Authentication Middleware (Validates requests dispatched from ERP Database)
const authMiddleware = (req, res, next) => {
  const authHeader = req.headers.authorization;
  if (!authHeader || !authHeader.startsWith('Bearer ')) {
    return res.status(401).json({ status: 'unauthorized', message: 'Missing Authorization header' });
  }
  const token = authHeader.split(' ')[1];
  if (!token || token.trim().length === 0) {
    return res.status(403).json({ status: 'forbidden', message: 'Invalid or empty Authorization Token' });
  }
  next();
};

const getSessionPath = (key) => path.join(SESSION_BASE_PATH, key);

function loadLidMap(key) {
  try {
    const filePath = path.join(getSessionPath(key), 'lid-map.json');
    if (fs.existsSync(filePath)) {
      const data = JSON.parse(fs.readFileSync(filePath, 'utf-8'));
      if (data.lidToPhone) {
        for (const [k, v] of Object.entries(data.lidToPhone)) lidToPhoneMap.set(k, v);
      }
      if (data.phoneToLid) {
        for (const [k, v] of Object.entries(data.phoneToLid)) phoneToLidMap.set(k, v);
      }
    }
  } catch (e) {}
}

function saveLidMap(key) {
  try {
    const sessionPath = getSessionPath(key);
    if (!fs.existsSync(sessionPath)) fs.mkdirSync(sessionPath, { recursive: true });
    const filePath = path.join(sessionPath, 'lid-map.json');
    const lidToPhone = Object.fromEntries(lidToPhoneMap.entries());
    const phoneToLid = Object.fromEntries(phoneToLidMap.entries());
    fs.writeFileSync(filePath, JSON.stringify({ lidToPhone, phoneToLid }, null, 2));
  } catch (e) {}
}



async function initSession(key, force = false) {
  let sessionObj = sessions.get(key);
  if (!sessionObj) {
    sessionObj = {
      key,
      sock: null,
      status: 'connecting',
      qr: null,
      user: null
    };
    sessions.set(key, sessionObj);
  }

  if (!force && (sessionObj.status === 'connected' || (sessionObj.status === 'qr' && sessionObj.qr))) {
    return sessionObj;
  }

  sessionObj.status = 'connecting';

  try {
    const sessionPath = getSessionPath(key);
    if (!fs.existsSync(sessionPath)) {
      fs.mkdirSync(sessionPath, { recursive: true });
    }

    loadLidMap(key);

    const { state, saveCreds } = await useMultiFileAuthState(sessionPath);

    let version;
    try {
      const res = await fetchLatestBaileysVersion();
      version = res.version;
    } catch (e) {
      console.log(`[Baileys ${key}] Default version fallback used`);
    }

    const sockOptions = {
      logger,
      auth: {
        creds: state.creds,
        keys: makeCacheableSignalKeyStore(state.keys, logger)
      },
      browser: Browsers.macOS('Desktop'),
      shouldIgnoreJid: jid => isJidStatusBroadcast(jid) || isJidGroup(jid) || isJidNewsletter(jid),
      syncFullHistory: false,
      markOnlineOnConnect: false,
      retryRequestDelayMs: 250,
      maxMsgRetryCount: 5,
      transactionOpts: { maxCommitRetries: 10, delayBetweenTriesMs: 10 },
      getMessage: async (key) => {
        if (key && key.id && messageStore.has(key.id)) {
          const stored = messageStore.get(key.id);
          if (stored && stored.message) {
            return stored.message;
          }
        }
        return WAProto.Message.fromObject({});
      }
    };
    if (version) {
      sockOptions.version = version;
    }

    console.log(`[Baileys ${key}] Starting WASocket instance...`);
    const sock = makeWASocket(sockOptions);
    sessionObj.sock = sock;

    sock.ev.on('creds.update', saveCreds);

    sock.ev.on('contacts.upsert', (contacts) => {
      try {
        for (const c of contacts) {
          if (c.id && c.lid) {
            const phone = c.id.replace(/@.*$/, '');
            const lid = c.lid.replace(/@.*$/, '');
            lidToPhoneMap.set(lid, phone);
            lidToPhoneMap.set(c.lid, phone);
          }
        }
      } catch (e) {}
    });

    sock.ev.on('contacts.update', (updates) => {
      try {
        for (const c of updates) {
          if (c.id && c.lid) {
            const phone = c.id.replace(/@.*$/, '');
            const lid = c.lid.replace(/@.*$/, '');
            lidToPhoneMap.set(lid, phone);
            lidToPhoneMap.set(c.lid, phone);
          }
        }
      } catch (e) {}
    });

    sock.ev.on('messages.upsert', async (m) => {
      try {
        if (!m.messages || !Array.isArray(m.messages)) return;
        for (const msg of m.messages) {
          if (msg.key && msg.key.id) {
            messageStore.set(msg.key.id, msg);
          }
          const jid = msg.key.remoteJid || '';

          // STRICT FILTER: Ignore Groups, Newsletters, Channels, Status Broadcasts
          if (!jid || jid === 'status@broadcast' || jid.endsWith('@g.us') || jid.endsWith('@newsletter') || jid.includes('newsletter')) {
            continue;
          }

          console.log(`[Baileys ${key}] Incoming human chat msg.key:`, msg.key);

          if (!msg.message) continue;

          let msgObj = msg.message;
          if (msgObj.ephemeralMessage) msgObj = msgObj.ephemeralMessage.message || msgObj;
          if (msgObj.viewOnceMessage) msgObj = msgObj.viewOnceMessage.message || msgObj;
          if (msgObj.viewOnceMessageV2) msgObj = msgObj.viewOnceMessageV2.message || msgObj;
          if (msgObj.documentWithCaptionMessage) msgObj = msgObj.documentWithCaptionMessage.message || msgObj;
          if (msgObj.editedMessage) msgObj = msgObj.editedMessage.message?.protocolMessage?.editedMessage || msgObj;
          if (!msgObj) continue;

          const bodyText = msgObj.conversation ||
                           msgObj.extendedTextMessage?.text ||
                           msgObj.imageMessage?.caption ||
                           msgObj.videoMessage?.caption ||
                           msgObj.documentMessage?.caption ||
                           msgObj.buttonsResponseMessage?.selectedButtonId ||
                           msgObj.listResponseMessage?.singleSelectReply?.selectedRowId ||
                           msgObj.templateButtonReplyMessage?.selectedId ||
                           '';

          // Save LID to Phone mapping and Phone to LID mapping
          if (msg.key.remoteJid) {
            const rawJid = msg.key.remoteJid;
            const lidId = rawJid.replace(/@.*$/, '');
            if (msg.key.senderPn) {
              const phoneNum = msg.key.senderPn.replace(/@.*$/, '').replace(/\D/g, '');
              lidToPhoneMap.set(lidId, phoneNum);
              lidToPhoneMap.set(rawJid, phoneNum);
              phoneToLidMap.set(phoneNum, rawJid);
              phoneToLidMap.set(phoneNum + '@s.whatsapp.net', rawJid);
            }
          }

          // Extract Real Phone Number JID (senderPn > map > remoteJidAlt > remoteJid)
          let senderJid = msg.key.senderPn || msg.key.remoteJidAlt || jid;
          let rawJidId = jid.replace(/@.*$/, '');

          if (lidToPhoneMap.has(rawJidId)) {
            senderJid = lidToPhoneMap.get(rawJidId) + '@s.whatsapp.net';
          } else if (lidToPhoneMap.has(jid)) {
            senderJid = lidToPhoneMap.get(jid) + '@s.whatsapp.net';
          }

          let senderNumber = senderJid.replace(/@.*$/, '');
          if (senderJid.endsWith('@lid') && !lidToPhoneMap.has(rawJidId)) {
            senderNumber = senderJid; // Keep full @lid JID if no PN found
          }

          if (msg.key.remoteJid && senderNumber) {
            phoneToLidMap.set(senderNumber, msg.key.remoteJid);
          }
          saveLidMap(key);

          const isDocument = !!msgObj.documentMessage;
          const isImage = !!msgObj.imageMessage;
          const isVideo = !!msgObj.videoMessage;

          const isSentByBotApi = outboundBotMessageIds.has(msg.key.id);
          const direction = isSentByBotApi ? 'outbound' : 'inbound';

          const payload = {
            session_key: key,
            sender_number: senderNumber,
            sender_name: msg.pushName || senderNumber,
            message_body: bodyText || (isDocument ? '[Document Attached]' : (isImage ? '[Photo]' : (isVideo ? '[Video]' : '[WhatsApp Message]'))),
            message_id: msg.key.id,
            direction: direction,
            message_type: isDocument ? 'document' : (isImage ? 'image' : 'text')
          };

          console.log(`[Baileys ${key}] Human message (${payload.direction}) from ${senderNumber}: "${bodyText || payload.message_body}"`);

          try {
            const resp = await fetch('http://127.0.0.1:8000/crm/whatsapp/webhook', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify(payload)
            });
            const resData = await resp.json();
            console.log(`[Baileys ${key}] Webhook response:`, resData);
          } catch (err) {
            console.error(`[Baileys ${key}] Webhook POST error:`, err.message);
          }
        }
      } catch (err) {
        console.error(`[Baileys ${key}] Error processing messages.upsert:`, err);
      }
    });

    sock.ev.on('connection.update', async (update) => {
      const { connection, lastDisconnect, qr } = update;
      if (connection || qr) {
        console.log(`[Baileys ${key}] connection.update ->`, { connection, hasQr: !!qr });
      }

      if (qr) {
        try {
          sessionObj.qr = await QRCode.toDataURL(qr);
          sessionObj.status = 'qr';
          console.log(`[Baileys ${key}] ✓ QR Code successfully generated as DataURL!`);
        } catch (err) {
          console.error('Failed to generate QR code data URL:', err);
        }
      }

      if (connection === 'close') {
        const statusCode = (lastDisconnect?.error)?.output?.statusCode;
        const isLoggedOut = statusCode === DisconnectReason.loggedOut && sessionObj.user !== null;
        sessionObj.qr = null;

        if (!isLoggedOut) {
          sessionObj.status = 'connecting';
          console.log(`[Baileys ${key}] Connection closed (code ${statusCode}). Re-initializing Baileys socket...`);
          setTimeout(() => {
            initSession(key, true);
          }, 3000);
        } else {
          sessionObj.status = 'disconnected';
          sessionObj.user = null;
          sessions.delete(key);
          if (fs.existsSync(sessionPath)) {
            fs.rmSync(sessionPath, { recursive: true, force: true });
          }
          console.log(`[Baileys ${key}] Session explicitly logged out by user.`);
        }
      } else if (connection === 'open') {
        sessionObj.status = 'connected';
        sessionObj.qr = null;
        sessionObj.user = sock.user;
        if (sock.user && sock.user.id && sock.user.lid) {
          const botPhone = sock.user.id.split(':')[0].replace(/\D/g, '');
          const botLid = sock.user.lid.split(':')[0].replace(/@.*$/, '');
          lidToPhoneMap.set(botLid, botPhone);
          lidToPhoneMap.set(`${botLid}@lid`, botPhone);
          phoneToLidMap.set(botPhone, `${botLid}@lid`);
          saveLidMap(key);
          console.log(`[Baileys ${key}] Mapped bot LID ${botLid} -> Phone ${botPhone}`);
        }
        console.log(`[Baileys ${key}] ✓ Connected successfully! User:`, sock.user);
      }
    });
  } catch (e) {
    console.error('Baileys Socket Init Error:', e);
    sessionObj.status = 'disconnected';
  }

  return sessionObj;
}

// Healthcheck Endpoint
app.get('/health', (req, res) => {
  res.json({ status: 'ok', time: new Date().toISOString() });
});

// Protected API Routes
app.use(authMiddleware);

app.get('/sessions/:key', async (req, res) => {
  const { key } = req.params;
  let session = sessions.get(key);

  if (!session) {
    session = await initSession(key);
  }

  return res.json({
    status: session.status,
    qr: session.qr,
    user: session.user
  });
});

app.post('/sessions/:key/connect', async (req, res) => {
  const { key } = req.params;
  let session = await initSession(key, true);
  return res.json({
    status: session.status,
    qr: session.qr,
    user: session.user
  });
});

app.delete('/sessions/:key', async (req, res) => {
  const { key } = req.params;
  const session = sessions.get(key);
  if (session && session.sock) {
    try {
      await session.sock.logout();
    } catch (e) { }
    sessions.delete(key);
  }
  const sessionPath = getSessionPath(key);
  if (fs.existsSync(sessionPath)) {
    fs.rmSync(sessionPath, { recursive: true, force: true });
  }
  return res.json({ status: 'disconnected', message: 'Session disconnected and cleared.' });
});

app.post('/sessions/:key/send-message', async (req, res) => {
  const { key } = req.params;
  const { number, message, message_id } = req.body;

  if (!number || !message) {
    return res.status(400).json({ status: 'error', message: 'Missing number or message text.' });
  }

  let session = sessions.get(key);
  if (!session) {
    session = await initSession(key);
  }

  if (session.status !== 'connected') {
    return res.status(400).json({ status: 'disconnected', message: 'WhatsApp session is not connected. Please scan QR code first.' });
  }

  try {
    let rawInput = String(number).trim();
    let cleanNum = rawInput.replace(/\D/g, '');

    if (cleanNum.startsWith('0')) {
      cleanNum = cleanNum.replace(/^0+/, '');
    }
    if (cleanNum.length === 10) {
      cleanNum = '91' + cleanNum;
    }

    let targetJid;
    if (rawInput.includes('@lid') || rawInput.includes('@s.whatsapp.net')) {
      targetJid = rawInput;
    } else if (phoneToLidMap.has(rawInput)) {
      targetJid = phoneToLidMap.get(rawInput);
    } else if (phoneToLidMap.has(cleanNum)) {
      targetJid = phoneToLidMap.get(cleanNum);
    } else if (lidToPhoneMap.has(rawInput)) {
      targetJid = `${lidToPhoneMap.get(rawInput)}@s.whatsapp.net`;
    } else if (lidToPhoneMap.has(cleanNum)) {
      targetJid = `${lidToPhoneMap.get(cleanNum)}@s.whatsapp.net`;
    }

    if (!targetJid && session.sock && session.sock.onWhatsApp && cleanNum.length >= 10) {
      try {
        const results = await session.sock.onWhatsApp(cleanNum);
        console.log(`[Baileys ${key}] onWhatsApp lookup for ${cleanNum}:`, results);
        if (results && results.length > 0 && results[0].exists) {
          targetJid = results[0].jid;
        }
      } catch (err) {
        console.warn(`[Baileys ${key}] onWhatsApp lookup warning:`, err.message);
      }
    }

    if (!targetJid) {
      targetJid = `${cleanNum}@s.whatsapp.net`;
    }

    console.log(`[Baileys ${key}] Sending message to ${targetJid}: "${message.substring(0, 30)}..."`);
    
    let sendOptions = { text: message };
    if (message_id && messageStore.has(message_id)) {
      const rawMsg = messageStore.get(message_id);
      if (rawMsg && rawMsg.key && rawMsg.message) {
        sendOptions.quoted = {
          key: {
            remoteJid: rawMsg.key.remoteJid,
            fromMe: rawMsg.key.fromMe,
            id: rawMsg.key.id
          },
          message: rawMsg.message
        };
      }
    }

    const sentMsg = await session.sock.sendMessage(targetJid, sendOptions);
    if (sentMsg && sentMsg.key && sentMsg.key.id) {
      outboundBotMessageIds.add(sentMsg.key.id);
      messageStore.set(sentMsg.key.id, sentMsg);
    }
    console.log(`[Baileys ${key}] ✓ Message sent successfully! ID:`, sentMsg?.key?.id);

    return res.json({
      status: 'success',
      message: `WhatsApp message successfully sent to ${targetJid}`,
      messageId: sentMsg?.key?.id
    });
  } catch (err) {
    console.error(`[Baileys ${key}] WhatsApp send message error:`, err);
    return res.status(500).json({ status: 'error', message: 'Failed to send WhatsApp message: ' + err.message });
  }
});

app.post('/sessions/:key/send-document', async (req, res) => {
  const { key } = req.params;
  const { number, filename, mimetype, caption, document } = req.body;

  if (!number || !document) {
    return res.status(400).json({ status: 'error', message: 'Missing number or document content.' });
  }

  let session = sessions.get(key);
  if (!session) {
    session = await initSession(key);
  }

  if (session.status !== 'connected') {
    return res.status(400).json({ status: 'disconnected', message: 'WhatsApp session is not connected. Please scan QR code first.' });
  }

  let cleanNum = String(number).replace(/\D/g, '');
  if (cleanNum.startsWith('0')) {
    cleanNum = cleanNum.replace(/^0+/, '');
  }
  if (cleanNum.length === 10) {
    cleanNum = '91' + cleanNum;
  }

  try {
    let targetJid;
    let rawInput = String(number).trim();
    if (rawInput.includes('@lid') || rawInput.includes('@s.whatsapp.net')) {
      targetJid = rawInput;
    } else if (phoneToLidMap.has(rawInput)) {
      targetJid = phoneToLidMap.get(rawInput);
    } else if (phoneToLidMap.has(cleanNum)) {
      targetJid = phoneToLidMap.get(cleanNum);
    }

    if (!targetJid && session.sock && session.sock.onWhatsApp) {
      const results = await session.sock.onWhatsApp(cleanNum);
      console.log(`[Baileys ${key}] onWhatsApp lookup for document ${cleanNum}:`, results);
      if (results && results.length > 0 && results[0].exists) {
        targetJid = results[0].jid;
      }
    }

    if (!targetJid) {
      targetJid = `${cleanNum}@s.whatsapp.net`;
    }

    const fileBuffer = Buffer.from(document, 'base64');
    console.log(`[Baileys ${key}] Sending document (${fileBuffer.length} bytes) to ${targetJid}...`);
    const sentMsg = await session.sock.sendMessage(targetJid, {
      document: fileBuffer,
      mimetype: mimetype || 'application/pdf',
      fileName: filename || 'Quotation.pdf',
      caption: caption || ''
    });
    if (sentMsg && sentMsg.key && sentMsg.key.id) {
      outboundBotMessageIds.add(sentMsg.key.id);
      messageStore.set(sentMsg.key.id, sentMsg);
    }
    console.log(`[Baileys ${key}] ✓ Document sent successfully! ID:`, sentMsg?.key?.id);

    return res.json({
      status: 'success',
      message: `Document successfully sent to WhatsApp +${cleanNum}`,
      messageId: sentMsg?.key?.id
    });
  } catch (err) {
    console.error(`[Baileys ${key}] WhatsApp send document error:`, err);
    return res.status(500).json({ status: 'error', message: 'Failed to send WhatsApp document: ' + err.message });
  }
});

app.listen(PORT, '127.0.0.1', () => {
  console.log(`[WhatsApp Bridge] Running on http://127.0.0.1:${PORT}`);
});

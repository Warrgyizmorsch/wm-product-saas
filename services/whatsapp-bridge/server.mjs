import express from 'express';
import cors from 'cors';
import path from 'path';
import fs from 'fs';
import QRCode from 'qrcode';
import pino from 'pino';
import makeWASocket, {
  useMultiFileAuthState,
  DisconnectReason,
  fetchLatestBaileysVersion
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
      auth: state,
      browser: ['Warrgyizmorsch ERP', 'Chrome', '1.0.0']
    };
    if (version) {
      sockOptions.version = version;
    }

    console.log(`[Baileys ${key}] Starting WASocket instance...`);
    const sock = makeWASocket(sockOptions);
    sessionObj.sock = sock;

    sock.ev.on('creds.update', saveCreds);

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
  const { number, message } = req.body;

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

  let cleanNum = String(number).replace(/\D/g, '');
  if (cleanNum.startsWith('0')) {
    cleanNum = cleanNum.replace(/^0+/, '');
  }
  if (cleanNum.length === 10) {
    cleanNum = '91' + cleanNum;
  }

  try {
    let targetJid = `${cleanNum}@s.whatsapp.net`;
    if (session.sock && session.sock.onWhatsApp) {
      const results = await session.sock.onWhatsApp(cleanNum);
      console.log(`[Baileys ${key}] onWhatsApp lookup for ${cleanNum}:`, results);
      if (results && results.length > 0 && results[0].exists) {
        targetJid = results[0].jid;
      } else {
        return res.status(404).json({ status: 'error', message: `Mobile number +${cleanNum} is not registered on WhatsApp.` });
      }
    }

    console.log(`[Baileys ${key}] Sending message to ${targetJid}: "${message.substring(0, 30)}..."`);
    const sentMsg = await session.sock.sendMessage(targetJid, { text: message });
    console.log(`[Baileys ${key}] ✓ Message sent successfully! ID:`, sentMsg?.key?.id);

    return res.json({
      status: 'success',
      message: `WhatsApp message successfully sent to +${cleanNum}`,
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
    let targetJid = `${cleanNum}@s.whatsapp.net`;
    if (session.sock && session.sock.onWhatsApp) {
      const results = await session.sock.onWhatsApp(cleanNum);
      console.log(`[Baileys ${key}] onWhatsApp lookup for document ${cleanNum}:`, results);
      if (results && results.length > 0 && results[0].exists) {
        targetJid = results[0].jid;
      } else {
        return res.status(404).json({ status: 'error', message: `Mobile number +${cleanNum} is not registered on WhatsApp.` });
      }
    }

    const fileBuffer = Buffer.from(document, 'base64');
    console.log(`[Baileys ${key}] Sending document (${fileBuffer.length} bytes) to ${targetJid}...`);
    const sentMsg = await session.sock.sendMessage(targetJid, {
      document: fileBuffer,
      mimetype: mimetype || 'application/pdf',
      fileName: filename || 'Quotation.pdf',
      caption: caption || ''
    });
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

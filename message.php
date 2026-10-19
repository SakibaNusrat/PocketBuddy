<?php
session_start();

$isAjax = isset($_GET['ajax']) || isset($_POST['ajax']);

/* =====================================================
   1. LOGIN + DATABASE
   ===================================================== */
if (!isset($_SESSION['email'])) {
    if ($isAjax) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Not logged in']);
        exit();
    }
    header("Location: login.php");
    exit();
}

$db = mysqli_connect('localhost', 'root', '', 'pocketbuddy');
if (!$db) {
    die("Database connection failed: " . mysqli_connect_error());
}
$db->set_charset('utf8mb4');

$stmt = $db->prepare("SELECT userID FROM users WHERE Email = ?");
$stmt->bind_param("s", $_SESSION['email']);
$stmt->execute();
$me = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$me) {
    session_destroy();
    header("Location: login.php");
    exit();
}
$myID = (int)$me['userID'];

/* =====================================================
   2. ONE-TIME SETUP (adds columns if missing)
      users.isAdmin  -> marks admin accounts
      Messages.isRead -> unread badges
   ===================================================== */
try {
    $c = $db->query("SHOW COLUMNS FROM users LIKE 'isAdmin'");
    if ($c && $c->num_rows === 0) {
        $db->query("ALTER TABLE users ADD COLUMN isAdmin TINYINT(1) NOT NULL DEFAULT 0");
    }
    $c = $db->query("SHOW COLUMNS FROM Messages LIKE 'isRead'");
    if ($c && $c->num_rows === 0) {
        $db->query("ALTER TABLE Messages ADD COLUMN isRead TINYINT(1) NOT NULL DEFAULT 0");
        $db->query("UPDATE Messages SET isRead = 1");
    }
} catch (mysqli_sql_exception $e) {
    // ignore: page still loads
}

/* =====================================================
   3. WHO IS ADMIN?
      Make an admin in phpMyAdmin with:
      UPDATE users SET isAdmin = 1 WHERE Email = 'your-admin@email.com';
   ===================================================== */
$adminIds = [];
$res = $db->query("SELECT userID FROM users WHERE isAdmin = 1 ORDER BY userID ASC");
while ($r = $res->fetch_assoc()) $adminIds[] = (int)$r['userID'];

$isAdmin   = in_array($myID, $adminIds, true);
$primaryID = $adminIds ? $adminIds[0] : 0;           // regular users write to this admin
$A         = $adminIds ? implode(',', $adminIds) : '0'; // safe: only integers

/* =====================================================
   4. HELPERS
   ===================================================== */
function jsonOut($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

function isRegularUser($db, $id, $adminIds) {
    if ($id <= 0 || in_array($id, $adminIds, true)) return false;
    $stmt = $db->prepare("SELECT 1 FROM users WHERE userID = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $ok = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $ok;
}

/*
 * The inbox is SHARED between all admins: a user's thread contains every
 * message between that user and ANY admin.
 */
function getContacts($db, $myID, $isAdmin, $adminIds, $A) {
    if ($isAdmin) {
        $sql = "SELECT u.userID, CONCAT(u.first_Name, ' ', u.last_Name) AS fullName,
                  (SELECT m.messageText FROM Messages m
                    WHERE (m.userID = u.userID AND m.receiverID IN ($A)) OR (m.userID IN ($A) AND m.receiverID = u.userID)
                    ORDER BY m.messageID DESC LIMIT 1) AS lastText,
                  (SELECT m.`timestamp` FROM Messages m
                    WHERE (m.userID = u.userID AND m.receiverID IN ($A)) OR (m.userID IN ($A) AND m.receiverID = u.userID)
                    ORDER BY m.messageID DESC LIMIT 1) AS lastTime,
                  (SELECT COUNT(*) FROM Messages m
                    WHERE m.userID = u.userID AND m.receiverID IN ($A) AND m.isRead = 0) AS unread
                FROM users u
                WHERE u.userID NOT IN ($A)
                ORDER BY lastTime IS NULL, lastTime DESC, fullName ASC";
        $rows = $db->query($sql)->fetch_all(MYSQLI_ASSOC);
        foreach ($rows as &$r) { $r['userID'] = (int)$r['userID']; $r['unread'] = (int)$r['unread']; }
        return $rows;
    }

    // regular user: a single "Support" conversation
    if (!$adminIds) return [];
    $stmt = $db->prepare("SELECT messageText, `timestamp` AS ts FROM Messages
                          WHERE (userID = ? AND receiverID IN ($A)) OR (userID IN ($A) AND receiverID = ?)
                          ORDER BY messageID DESC LIMIT 1");
    $stmt->bind_param("ii", $myID, $myID);
    $stmt->execute();
    $last = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmt = $db->prepare("SELECT COUNT(*) AS n FROM Messages WHERE userID IN ($A) AND receiverID = ? AND isRead = 0");
    $stmt->bind_param("i", $myID);
    $stmt->execute();
    $unread = (int)$stmt->get_result()->fetch_assoc()['n'];
    $stmt->close();

    return [[
        'userID'   => $adminIds[0],
        'fullName' => 'PocketBuddy Support',
        'lastText' => $last['messageText'] ?? null,
        'lastTime' => $last['ts'] ?? null,
        'unread'   => $unread,
    ]];
}

/* =====================================================
   5. AJAX ENDPOINTS
   ===================================================== */
if ($isAjax) {
    $action = $_GET['ajax'] ?? $_POST['ajax'];

    // ---- send ----
    if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $text = trim($_POST['message_text'] ?? '');
        if ($text === '') jsonOut(['error' => 'Message is empty.']);
        if (mb_strlen($text) > 2000) jsonOut(['error' => 'Message is too long (max 2000 characters).']);

        if ($isAdmin) {
            $to = (int)($_POST['receiver_id'] ?? 0);
            if (!isRegularUser($db, $to, $adminIds)) jsonOut(['error' => 'Invalid user.']);
        } else {
            if (!$primaryID) jsonOut(['error' => 'Support is not available yet.']);
            $to = $primaryID;
        }

        $stmt = $db->prepare("INSERT INTO Messages (userID, receiverID, messageText, isRead) VALUES (?, ?, ?, 0)");
        $stmt->bind_param("iis", $myID, $to, $text);
        $stmt->execute();
        $newId = $stmt->insert_id;
        $stmt->close();
        jsonOut(['ok' => true, 'id' => $newId]);
    }

    // ---- poll ----
    if ($action === 'poll') {
        $after = (int)($_GET['after'] ?? 0);
        $msgs  = [];
        $peer  = 0;

        if ($isAdmin) {
            $peer = (int)($_GET['with'] ?? 0);
            if (!isRegularUser($db, $peer, $adminIds)) $peer = 0;
        } else {
            $peer = $myID; // a user's thread is always "me <-> support"
            if (!$adminIds) $peer = 0;
        }

        if ($peer > 0) {
            // mark messages addressed to me / to the support team as read
            if ($isAdmin) {
                $stmt = $db->prepare("UPDATE Messages SET isRead = 1 WHERE userID = ? AND receiverID IN ($A) AND isRead = 0");
                $stmt->bind_param("i", $peer);
            } else {
                $stmt = $db->prepare("UPDATE Messages SET isRead = 1 WHERE receiverID = ? AND userID IN ($A) AND isRead = 0");
                $stmt->bind_param("i", $myID);
            }
            $stmt->execute();
            $stmt->close();

            $stmt = $db->prepare("SELECT messageID, userID, messageText, `timestamp` AS ts FROM Messages
                                  WHERE messageID > ?
                                    AND ((userID = ? AND receiverID IN ($A)) OR (userID IN ($A) AND receiverID = ?))
                                  ORDER BY messageID ASC LIMIT 200");
            $stmt->bind_param("iii", $after, $peer, $peer);
            $stmt->execute();
            $r = $stmt->get_result();
            while ($m = $r->fetch_assoc()) {
                $sender = (int)$m['userID'];
                $msgs[] = [
                    'id'   => (int)$m['messageID'],
                    'mine' => $isAdmin ? in_array($sender, $adminIds, true) : ($sender === $myID),
                    'text' => $m['messageText'],
                    'ts'   => $m['ts'],
                ];
            }
            $stmt->close();
        }
        jsonOut(['messages' => $msgs, 'contacts' => getContacts($db, $myID, $isAdmin, $adminIds, $A)]);
    }

    jsonOut(['error' => 'Unknown action']);
}

/* =====================================================
   6. PAGE DATA
   ===================================================== */
$contacts = getContacts($db, $myID, $isAdmin, $adminIds, $A);
$openWith = $isAdmin ? (int)($_GET['to'] ?? 0) : 0;
$flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PocketBuddy | <?php echo $isAdmin ? 'Support Inbox' : 'Support'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        @import url("https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,600&display=swap");

        :root {
            --rust: #A6432C; --terracotta: #C15A3E; --terracotta-light: #D97A55;
            --cream: #FBF6F3; --paper: #FDF3EF; --line: #ECE0DB; --ink: #2B2320; --ink-soft: #7A6E68;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif; background: var(--paper); color: var(--ink);
            min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px 15px;
        }

        .shell {
            width: 100%; max-width: 1000px; height: calc(100vh - 48px); max-height: 720px; min-height: 480px;
            background: #fff; border-radius: 22px; display: flex; overflow: hidden;
            box-shadow: 0 25px 60px rgba(166, 67, 44, 0.15);
        }
        .shell.single { max-width: 720px; }

        /* ===== SIDEBAR (admin only) ===== */
        .sidebar {
            width: 320px; flex-shrink: 0;
            background: linear-gradient(165deg, #241713 0%, #3A241D 60%, #52291F 100%);
            color: #fff; display: flex; flex-direction: column;
        }
        .shell.single .sidebar { display: none; }
        .side-top { padding: 24px 20px 12px; }
        .side-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
        .side-head h1 { font-family: 'Fraunces', serif; font-size: 21px; font-weight: 600; }
        .icon-btn {
            width: 32px; height: 32px; border-radius: 9px; border: none;
            background: rgba(255,255,255,0.12); color: #fff; font-size: 16px; cursor: pointer; transition: background .2s;
        }
        .icon-btn:hover { background: rgba(255,255,255,0.24); }

        .search { position: relative; }
        .search i { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); font-size: 12px; color: rgba(255,255,255,0.5); }
        .search input {
            width: 100%; padding: 10px 14px 10px 34px; border-radius: 10px;
            border: 1.5px solid rgba(255,255,255,0.14); background: rgba(255,255,255,0.08);
            color: #fff; font-size: 13px; font-family: 'Poppins', sans-serif; outline: none;
        }
        .search input::placeholder { color: rgba(255,255,255,0.45); }
        .search input:focus { border-color: var(--terracotta-light); }

        .contact-list { flex: 1; overflow-y: auto; padding: 6px 10px 14px; }
        .contact-list::-webkit-scrollbar { width: 5px; }
        .contact-list::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 10px; }
        .contact { display: flex; align-items: center; gap: 12px; padding: 11px 10px; border-radius: 12px; cursor: pointer; transition: background .15s; }
        .contact:hover { background: rgba(255,255,255,0.07); }
        .contact.active { background: rgba(255,255,255,0.14); }

        .avatar {
            width: 42px; height: 42px; border-radius: 50%; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            font-weight: 600; font-size: 14px; color: #fff; background: linear-gradient(135deg, #D97A55, #A6432C);
        }
        .c-body { flex: 1; min-width: 0; }
        .c-row { display: flex; justify-content: space-between; align-items: baseline; gap: 8px; }
        .c-name { font-size: 13.5px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .c-time { font-size: 10.5px; color: rgba(255,255,255,0.5); flex-shrink: 0; }
        .c-last { font-size: 12px; color: rgba(255,255,255,0.58); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 2px; }
        .c-row2 { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
        .badge-unread {
            min-width: 19px; height: 19px; padding: 0 6px; border-radius: 10px; background: var(--terracotta-light);
            color: #fff; font-size: 10.5px; font-weight: 600; display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .no-contacts { padding: 24px 12px; font-size: 12.5px; color: rgba(255,255,255,0.55); text-align: center; }

        /* ===== CHAT ===== */
        .chat { flex: 1; display: flex; flex-direction: column; min-width: 0; background: var(--cream); }
        .chat-head { display: flex; align-items: center; gap: 12px; padding: 14px 22px; background: #fff; border-bottom: 1px solid var(--line); }
        .chat-head .avatar { width: 40px; height: 40px; }
        .chat-head .who { font-size: 14.5px; font-weight: 600; }
        .chat-head .sub { font-size: 11.5px; color: var(--ink-soft); }
        .chat-head .spacer { flex: 1; }
        .chat-head .icon-btn { background: var(--paper); color: var(--rust); }
        .chat-head .icon-btn:hover { background: var(--line); }
        .back-btn { display: none; }
        .close-chat { display: none; }
        .shell.single .close-chat { display: block; }

        .thread { flex: 1; overflow-y: auto; padding: 20px 24px; display: flex; flex-direction: column; gap: 4px; }
        .thread::-webkit-scrollbar { width: 6px; }
        .thread::-webkit-scrollbar-thumb { background: var(--line); border-radius: 10px; }
        .day-sep { align-self: center; margin: 14px 0 8px; font-size: 11px; color: var(--ink-soft); background: #fff; border: 1px solid var(--line); padding: 3px 12px; border-radius: 20px; }
        .hint { align-self: center; margin: auto; text-align: center; font-size: 13px; color: var(--ink-soft); max-width: 280px; line-height: 1.6; }
        .hint i { display: block; font-size: 26px; color: var(--terracotta-light); margin-bottom: 10px; }

        .bubble {
            max-width: 68%; padding: 9px 14px 6px; border-radius: 16px; font-size: 13.5px; line-height: 1.5;
            white-space: pre-wrap; word-wrap: break-word; overflow-wrap: anywhere; animation: pop .18s ease;
        }
        @keyframes pop { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
        .bubble .t { display: block; font-size: 10px; margin-top: 3px; text-align: right; opacity: .65; }
        .bubble.mine { align-self: flex-end; color: #fff; border-bottom-right-radius: 4px; background: linear-gradient(135deg, #D97A55, #A6432C); }
        .bubble.theirs { align-self: flex-start; background: #fff; border: 1px solid var(--line); border-bottom-left-radius: 4px; color: var(--ink); }
        .bubble.first-of-group { margin-top: 8px; }

        .composer { display: flex; align-items: flex-end; gap: 10px; padding: 14px 20px; background: #fff; border-top: 1px solid var(--line); }
        .composer textarea {
            flex: 1; resize: none; max-height: 120px; min-height: 42px; padding: 11px 16px; border-radius: 22px;
            border: 1.5px solid var(--line); background: var(--cream); font-family: 'Poppins', sans-serif;
            font-size: 13.5px; color: var(--ink); outline: none; line-height: 1.4; transition: border-color .2s, box-shadow .2s;
        }
        .composer textarea:focus { border-color: var(--terracotta); box-shadow: 0 0 0 3px rgba(193,90,62,.1); background: #fff; }
        .send-btn {
            width: 44px; height: 44px; border-radius: 50%; border: none; cursor: pointer; color: #fff; font-size: 15px;
            background: linear-gradient(135deg, #D97A55, #A6432C); box-shadow: 0 8px 18px rgba(166,67,44,.28); transition: transform .15s;
        }
        .send-btn:hover { transform: translateY(-1px); }
        .send-btn:disabled { opacity: .5; cursor: default; transform: none; }

        .empty-state { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; color: var(--ink-soft); padding: 30px; }
        .empty-state .ring { width: 72px; height: 72px; border-radius: 22px; background: #fff; border: 1px solid var(--line); display: flex; align-items: center; justify-content: center; font-size: 28px; color: var(--terracotta-light); margin-bottom: 16px; }
        .empty-state h2 { font-family: 'Fraunces', serif; font-size: 20px; color: var(--ink); margin-bottom: 6px; }
        .empty-state p { font-size: 13px; max-width: 300px; line-height: 1.6; }
        .hidden { display: none !important; }

        .toast {
            position: fixed; bottom: 24px; right: 24px; min-width: 240px; max-width: 340px; padding: 13px 18px; border-radius: 10px;
            font-size: 14px; color: #fff; display: flex; align-items: center; box-shadow: 0 12px 28px rgba(43,35,32,.18); z-index: 1000;
            opacity: 0; visibility: hidden; transform: translateY(20px); transition: all .4s ease;
            background: linear-gradient(135deg, var(--terracotta-light), var(--rust));
        }
        .toast.show { opacity: 1; visibility: visible; transform: none; }

        @media (max-width: 760px) {
            body { padding: 0; }
            .shell { border-radius: 0; height: 100vh; max-height: none; }
            .sidebar { width: 100%; }
            .chat { display: none; width: 100%; }
            .shell.chat-open .sidebar { display: none; }
            .shell.chat-open .chat { display: flex; }
            .shell.single .chat { display: flex; }
            .shell:not(.single) .back-btn { display: block; }
            .bubble { max-width: 82%; }
        }
    </style>
</head>
<body>
<div class="shell<?php echo $isAdmin ? '' : ' single'; ?>" id="shell">

    <?php if ($isAdmin): ?>
    <aside class="sidebar">
        <div class="side-top">
            <div class="side-head">
                <h1>Support Inbox</h1>
                <button class="icon-btn" onclick="window.location.href='index.php'" title="Close">&times;</button>
            </div>
            <div class="search">
                <i class="fas fa-search"></i>
                <input type="text" id="search" placeholder="Search users...">
            </div>
        </div>
        <div class="contact-list" id="contactList"></div>
    </aside>
    <?php endif; ?>

    <section class="chat">
        <div class="empty-state<?php echo (!$isAdmin && $contacts) ? ' hidden' : ''; ?>" id="emptyState">
            <div class="ring"><i class="far fa-comments"></i></div>
            <?php if ($isAdmin): ?>
                <h2>Support inbox</h2>
                <p>Select a user on the left to read and reply to their messages.</p>
            <?php else: ?>
                <h2>Support isn't available yet</h2>
                <p>No support team has been set up. Please try again later.</p>
            <?php endif; ?>
        </div>

        <div class="chat-head hidden" id="chatHead">
            <button class="icon-btn back-btn" id="backBtn"><i class="fas fa-arrow-left"></i></button>
            <div class="avatar" id="headAvatar"></div>
            <div>
                <div class="who" id="headName"></div>
                <div class="sub" id="headSub"></div>
            </div>
            <div class="spacer"></div>
            <button class="icon-btn close-chat" onclick="window.location.href='index.php'" title="Close">&times;</button>
        </div>

        <div class="thread hidden" id="thread"></div>

        <div class="composer hidden" id="composer">
            <textarea id="msgInput" rows="1" placeholder="Type a message..." maxlength="2000"></textarea>
            <button class="send-btn" id="sendBtn" title="Send"><i class="fas fa-paper-plane"></i></button>
        </div>
    </section>
</div>

<div class="toast" id="toast"><span id="toastMsg"></span></div>

<script>
    const IS_ADMIN = <?php echo $isAdmin ? 'true' : 'false'; ?>;
    let contacts = <?php echo json_encode($contacts, $flags); ?>;
    let activeId = 0, lastId = 0, lastDay = '', lastSide = null, sending = false;
    const openWith = <?php echo (int)$openWith; ?>;

    const $ = id => document.getElementById(id);
    const shell = $('shell'), thread = $('thread');

    function initials(name) {
        const p = name.trim().split(/\s+/);
        return ((p[0] || '')[0] || '?').toUpperCase() + ((p[1] || '')[0] || '').toUpperCase();
    }
    function parseTs(ts) { return new Date(String(ts).replace(' ', 'T')); }
    function fmtTime(d) { return d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }); }
    function dayLabel(d) {
        const today = new Date(); today.setHours(0,0,0,0);
        const dd = new Date(d); dd.setHours(0,0,0,0);
        const diff = Math.round((today - dd) / 86400000);
        if (diff === 0) return 'Today';
        if (diff === 1) return 'Yesterday';
        return d.toLocaleDateString([], { day: 'numeric', month: 'short', year: 'numeric' });
    }
    function listTime(ts) {
        if (!ts) return '';
        const d = parseTs(ts), l = dayLabel(d);
        return l === 'Today' ? fmtTime(d) : (l === 'Yesterday' ? 'Yesterday' : d.toLocaleDateString([], { day: 'numeric', month: 'short' }));
    }
    function toast(msg) {
        $('toastMsg').textContent = msg;
        const t = $('toast'); t.classList.add('show');
        setTimeout(() => t.classList.remove('show'), 3500);
    }

    /* ---------- admin: contact list ---------- */
    function renderContacts() {
        if (!IS_ADMIN) return;
        const listEl = $('contactList');
        const q = $('search').value.trim().toLowerCase();
        listEl.textContent = '';
        const shown = contacts.filter(c => c.fullName.toLowerCase().includes(q));
        if (!shown.length) {
            const d = document.createElement('div'); d.className = 'no-contacts';
            d.textContent = q ? 'No one matches your search.' : 'No users yet.';
            listEl.appendChild(d); return;
        }
        shown.forEach(c => {
            const row = document.createElement('div');
            row.className = 'contact' + (c.userID === activeId ? ' active' : '');
            row.onclick = () => openChat(c.userID);

            const av = document.createElement('div'); av.className = 'avatar'; av.textContent = initials(c.fullName);
            const body = document.createElement('div'); body.className = 'c-body';
            const r1 = document.createElement('div'); r1.className = 'c-row';
            const nm = document.createElement('span'); nm.className = 'c-name'; nm.textContent = c.fullName;
            const tm = document.createElement('span'); tm.className = 'c-time'; tm.textContent = listTime(c.lastTime);
            r1.append(nm, tm);
            const r2 = document.createElement('div'); r2.className = 'c-row2';
            const ls = document.createElement('span'); ls.className = 'c-last'; ls.textContent = c.lastText ? c.lastText : 'No messages yet';
            r2.appendChild(ls);
            if (c.unread > 0 && c.userID !== activeId) {
                const b = document.createElement('span'); b.className = 'badge-unread'; b.textContent = c.unread; r2.appendChild(b);
            }
            body.append(r1, r2); row.append(av, body); listEl.appendChild(row);
        });
    }

    /* ---------- chat ---------- */
    function openChat(id) {
        const c = contacts.find(x => x.userID === id);
        if (!c) return;
        activeId = id; lastId = 0; lastDay = ''; lastSide = null;
        thread.textContent = '';

        $('headAvatar').textContent = initials(c.fullName);
        $('headName').textContent = c.fullName;
        $('headSub').textContent = IS_ADMIN ? 'User' : 'We reply here as soon as we can';
        ['chatHead', 'thread', 'composer'].forEach(i => $(i).classList.remove('hidden'));
        $('emptyState').classList.add('hidden');
        shell.classList.add('chat-open');
        renderContacts();
        poll(true);
        $('msgInput').focus();
    }

    function addMessage(m) {
        const old = thread.querySelector('.hint'); if (old) old.remove();
        const d = parseTs(m.ts), day = dayLabel(d);
        if (day !== lastDay) {
            const sep = document.createElement('div'); sep.className = 'day-sep'; sep.textContent = day;
            thread.appendChild(sep); lastDay = day; lastSide = null;
        }
        const side = m.mine ? 'mine' : 'theirs';
        const b = document.createElement('div');
        b.className = 'bubble ' + side + (lastSide !== side ? ' first-of-group' : '');
        b.appendChild(document.createTextNode(m.text));
        const t = document.createElement('span'); t.className = 't'; t.textContent = fmtTime(d);
        b.appendChild(t); thread.appendChild(b);
        lastSide = side; lastId = Math.max(lastId, m.id);
    }

    function showHintIfEmpty() {
        if (lastId === 0 && !thread.querySelector('.hint')) {
            const h = document.createElement('div'); h.className = 'hint';
            h.innerHTML = '<i class="far fa-comment-dots"></i>';
            h.appendChild(document.createTextNode(IS_ADMIN
                ? 'No messages with this user yet. Write the first one below.'
                : 'Have a question about your budget, bank account or payments? Send us a message and our team will reply here.'));
            thread.appendChild(h);
        }
    }

    const nearBottom = () => thread.scrollHeight - thread.scrollTop - thread.clientHeight < 120;
    const toBottom = () => { thread.scrollTop = thread.scrollHeight; };

    async function poll(forceScroll) {
        try {
            const res = await fetch('?ajax=poll&with=' + activeId + '&after=' + lastId, { cache: 'no-store' });
            if (res.status === 401) { window.location.href = 'login.php'; return; }
            const data = await res.json();
            if (data.contacts) { contacts = data.contacts; renderContacts(); }
            if (activeId) {
                if (data.messages && data.messages.length) {
                    const stick = forceScroll || nearBottom();
                    data.messages.forEach(addMessage);
                    if (stick) toBottom();
                } else { showHintIfEmpty(); }
            }
        } catch (e) { /* retry next tick */ }
    }

    async function sendMessage() {
        const input = $('msgInput'), text = input.value.trim();
        if (!text || !activeId || sending) return;
        sending = true; $('sendBtn').disabled = true;
        try {
            const body = new URLSearchParams({ ajax: 'send', receiver_id: activeId, message_text: text });
            const res = await fetch(location.pathname, { method: 'POST', body });
            const data = await res.json();
            if (data.error) toast(data.error);
            else { input.value = ''; input.style.height = 'auto'; await poll(true); }
        } catch (e) { toast('Could not send. Check your connection.'); }
        sending = false; $('sendBtn').disabled = false; input.focus();
    }

    /* ---------- events ---------- */
    $('sendBtn').onclick = sendMessage;
    $('msgInput').addEventListener('keydown', e => {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
    });
    $('msgInput').addEventListener('input', function () {
        this.style.height = 'auto'; this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    });
    if (IS_ADMIN) {
        $('search').addEventListener('input', renderContacts);
        $('backBtn').onclick = () => {
            shell.classList.remove('chat-open'); activeId = 0;
            ['chatHead', 'thread', 'composer'].forEach(i => $(i).classList.add('hidden'));
            $('emptyState').classList.remove('hidden'); renderContacts();
        };
    }

    renderContacts();
    if (IS_ADMIN) { if (openWith) openChat(openWith); }
    else if (contacts.length) { openChat(contacts[0].userID); }
    setInterval(() => { if (!document.hidden) poll(false); }, 3000);
</script>
</body>
</html>
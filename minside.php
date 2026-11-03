<?php
declare(strict_types=1);
require __DIR__ . '/inc/auth.php';
require_login();
?>
<!DOCTYPE html>
<html lang="da">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Tidsregistrering – <?= e($config['site_name']) ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body>
  <header class="bar">
    <strong><?= e($config['site_name']) ?> · <?= e((string) current_user()) ?></strong>
    <form method="post" action="logout.php">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <button type="submit" class="ghost">Log ud</button>
    </form>
  </header>

  <main class="content layout">
   <div class="col">
    <section class="timer" aria-label="Stopur">
      <div id="clock" class="clock" role="timer" aria-live="off">00:00:00</div>
      <input id="note" type="text" maxlength="200" autocomplete="off"
             placeholder="Hvad arbejder du på? (valgfrit)" aria-label="Note til tidsregistreringen">
      <button id="toggle" type="button" class="big">Start</button>
      <p id="today" class="today">I dag i alt: 0 min</p>
      <p id="msg" class="error" role="alert" hidden></p>
    </section>

    <section class="history" aria-labelledby="hist">
      <h2 id="hist">Registreringer (seneste 30 dage)</h2>
      <div id="days"><p>Ingen registreringer endnu. Tryk på Start for at begynde.</p></div>
    </section>
   </div>

    <aside class="chart-box" aria-labelledby="chart-title">
      <h2 id="chart-title">Dagligt forbrug</h2>
      <div id="chart"></div>
      <p id="legend" class="legend">Timer pr. dag, seneste 14 dage</p>
      <p id="chart-info" class="legend info" aria-live="polite">Tryk på en søjle for at se tiden.</p>
    </aside>
  </main>

<script>
const csrf = <?= json_encode(csrf_token()) ?>;
const $ = id => document.getElementById(id);
let state = null, offset = 0, busy = false;

const pad = n => String(n).padStart(2, '0');
const clock = s => `${pad(Math.floor(s / 3600))}:${pad(Math.floor(s % 3600 / 60))}:${pad(Math.floor(s % 60))}`;
const nice = s => {
  const h = Math.floor(s / 3600), m = Math.floor(s % 3600 / 60);
  return h ? `${h} t ${pad(m)} min` : `${m} min`;
};
const niceSec = s => {
  const h = Math.floor(s / 3600), m = Math.floor(s % 3600 / 60), sec = Math.floor(s % 60);
  return (h ? `${h} t ${pad(m)} min ` : (m ? `${m} min ` : '')) + `${pad(sec)} s`;
};
const nowSec = () => Date.now() / 1000 + offset;
const hhmm = ts => new Date(ts * 1000).toLocaleTimeString('da-DK', {hour: '2-digit', minute: '2-digit', second: '2-digit'});

async function call(action, extra = {}) {
  if (busy) return;
  busy = true; $('toggle').disabled = true; $('msg').hidden = true;
  try {
    const r = await fetch('api.php', {
      method: 'POST', credentials: 'same-origin',
      body: new URLSearchParams({csrf, action, ...extra})
    });
    if (r.status === 401) { location.href = 'index.php'; return; }
    const j = await r.json();
    if (!r.ok) throw new Error(j.error || 'Noget gik galt.');
    state = j; offset = j.now - Date.now() / 1000;
    if (action === 'stop') $('note').value = '';
    else if (action === 'status' && state.running_start !== null && document.activeElement !== $('note')) {
      $('note').value = state.running_note || '';
    }
    renderDays();
    renderChart();
  } catch (err) {
    $('msg').textContent = err.message; $('msg').hidden = false;
  } finally {
    busy = false; $('toggle').disabled = false; tick();
  }
}


let lastChartSec = -1, lastChartWidth = 0;
function renderChart() {
  if (!state) return;
  const NS = 'http://www.w3.org/2000/svg';
  const el = (name, attrs = {}, text) => {
    const n = document.createElementNS(NS, name);
    for (const k in attrs) n.setAttribute(k, attrs[k]);
    if (text !== undefined) n.textContent = text;
    return n;
  };
  const fmt = v => v.toLocaleString('da-DK', {maximumFractionDigits: 1});

  const boxW = $('chart').clientWidth;
  const W = boxW > 0 ? Math.max(260, Math.round(boxW)) : 480;
  const N = W < 420 ? 7 : 14;
  const H = W < 420 ? 240 : 300;
  const L = 38, R = 8, T = 18, B = 42;
  $('legend').textContent = `Timer pr. dag, seneste ${N} dage`;
  lastChartWidth = boxW;
  const pw = W - L - R, ph = H - T - B;
  const running = state.running_start !== null;

  const byDate = {};
  for (const d of state.days) byDate[d.date] = d.total;

  const items = [];
  for (let i = N - 1; i >= 0; i--) {
    const dt = new Date((state.today_start + 12 * 3600 - i * 86400) * 1000);
    let secs = byDate[localDate(dt.getTime() / 1000)] || 0;
    if (i === 0 && running) secs += Math.max(0, nowSec() - Math.max(state.running_start, state.today_start));
    items.push({dt, secs, today: i === 0});
  }

  const maxH = Math.max(...items.map(x => x.secs)) / 3600;
  const step = [0.25, 0.5, 1, 2, 4, 5, 10, 20].find(s => maxH / s <= 5) || 24;
  const top = Math.max(1, Math.ceil(maxH / step) * step);

  const svg = el('svg', {viewBox: `0 0 ${W} ${H}`, role: 'img', 'aria-label': `Søjlediagram over timer pr. dag de seneste ${N} dage`});

  for (let v = 0; v <= top + 1e-9; v += step) {
    const y = T + ph - (v / top) * ph;
    svg.append(el('line', {x1: L, x2: W - R, y1: y, y2: y, class: 'c-grid'}));
    svg.append(el('text', {x: L - 6, y: y + 4, class: 'c-axis', 'text-anchor': 'end'}, fmt(v)));
  }

  const slot = pw / N, bw = slot * 0.62;
  items.forEach((it, i) => {
    const x = L + i * slot + (slot - bw) / 2;
    const h = (it.secs / 3600 / top) * ph;
    const y = T + ph - h;
    const label = it.dt.toLocaleDateString('da-DK', {weekday: 'long', day: 'numeric', month: 'long'});

    const bar = el('rect', {x, y, width: bw, height: Math.max(h, it.secs > 0 ? 1 : 0), rx: 2, class: 'c-bar' + (it.today ? ' today' : '')});
    bar.append(el('title', {}, `${label}: ${niceSec(it.secs)}`));
    bar.addEventListener('click', () => { $('chart-info').textContent = `${label}: ${niceSec(it.secs)}`; });
    svg.append(bar);

    if (it.secs > 0 && slot >= 24) svg.append(el('text', {x: x + bw / 2, y: y - 4, class: 'c-val'}, fmt(it.secs / 3600)));

    const cx = x + bw / 2;
    svg.append(el('text', {x: cx, y: H - 24, class: 'c-axis', 'text-anchor': 'middle'}, it.dt.getDate()));
    const wd = it.dt.toLocaleDateString('da-DK', {weekday: 'short'}).replace('.', '').slice(0, 2);
    svg.append(el('text', {x: cx, y: H - 10, class: 'c-axis', 'text-anchor': 'middle'}, wd));
  });

  $('chart').replaceChildren(svg);
}

function tick() {
  if (!state) return;
  const running = state.running_start !== null;
  const elapsed = running ? Math.max(0, nowSec() - state.running_start) : 0;
  $('clock').textContent = clock(elapsed);
  $('toggle').textContent = running ? 'Stop og gem' : 'Start';
  $('toggle').classList.toggle('stop', running);

  const todayEntry = state.days.find(d => d.date === localDate(state.today_start));
  let total = todayEntry ? todayEntry.total : 0;
  if (running) total += Math.max(0, nowSec() - Math.max(state.running_start, state.today_start));
  $('today').textContent = 'I dag i alt: ' + nice(total);
  const live = $('live-dur');
  if (live) live.textContent = niceSec(elapsed);
  const ln = $('live-note');
  if (ln) ln.textContent = $('note').value;
  if (running && Math.floor(nowSec()) !== lastChartSec) {
    lastChartSec = Math.floor(nowSec());
    renderChart();
  }
}

function localDate(ts) {
  const d = new Date(ts * 1000);
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

function renderDays() {
  const box = $('days');
  box.textContent = '';
  const rows = [];
  for (const day of state.days) for (const e of day.entries) rows.push(e);
  rows.sort((a, b) => b.start - a.start);

  if (!rows.length && state.running_start === null) {
    const p = document.createElement('p');
    p.textContent = 'Ingen registreringer endnu. Tryk på Start for at begynde.';
    box.append(p);
    return;
  }

  const ul = document.createElement('ul');
  ul.className = 'log';
  const dateLabel = ts => new Date(ts * 1000).toLocaleDateString('da-DK', {weekday: 'short', day: 'numeric', month: 'short'});
  const cell = (text, cls) => {
    const el = document.createElement('span');
    el.textContent = text;
    if (cls) el.className = cls;
    return el;
  };
  const mkBtn = (text) => {
    const b = document.createElement('button');
    b.type = 'button'; b.className = 'ghost small'; b.textContent = text;
    return b;
  };

  if (state.running_start !== null) {
    const li = document.createElement('li');
    li.className = 'live';
    const dur = cell('', 'dur');
    dur.id = 'live-dur';
    const note = cell($('note').value, 'note');
    note.id = 'live-note';
    li.append(cell(dateLabel(state.running_start), 'date'),
              cell(`${hhmm(state.running_start)}– kører nu`, 'time'),
              dur, cell(''), note);
    ul.append(li);
  }

  for (const e of rows) {
    const li = document.createElement('li');
    const actions = document.createElement('div');
    actions.className = 'actions';
    const edit = mkBtn('Rediger'), del = mkBtn('Slet');
    const note = cell(e.note || '', 'note');
    edit.addEventListener('click', () => editNote(note, e, mkBtn));
    del.addEventListener('click', () => {
      if (confirm('Slet denne registrering?')) call('delete', {id: e.id});
    });
    actions.append(edit, del);
    li.append(cell(dateLabel(e.start), 'date'),
              cell(`${hhmm(e.start)}–${hhmm(e.end)}`, 'time'),
              cell(niceSec(e.seconds), 'dur'), actions, note);
    ul.append(li);
  }
  box.append(ul);
}

function editNote(noteEl, e, mkBtn) {
  const wrap = document.createElement('div');
  wrap.className = 'note editing';
  const input = document.createElement('input');
  input.type = 'text'; input.maxLength = 200; input.value = e.note || '';
  input.setAttribute('aria-label', 'Rediger note');
  const save = mkBtn('Gem'), cancel = mkBtn('Annuller');
  const doSave = () => call('editnote', {id: e.id, note: input.value});
  save.addEventListener('click', doSave);
  cancel.addEventListener('click', renderDays);
  input.addEventListener('keydown', ev => {
    if (ev.key === 'Enter') { ev.preventDefault(); doSave(); }
    if (ev.key === 'Escape') renderDays();
  });
  wrap.append(input, save, cancel);
  noteEl.replaceWith(wrap);
  input.focus();
}

$('toggle').addEventListener('click', () => {
  if (!state) return;
  call(state.running_start === null ? 'start' : 'stop', {note: $('note').value});
});

// Gem noten løbende, mens uret kører (så den overlever en genindlæsning)
let noteTimer;
async function saveNote() {
  if (!state || state.running_start === null) return;
  try {
    await fetch('api.php', {
      method: 'POST', credentials: 'same-origin',
      body: new URLSearchParams({csrf, action: 'setnote', note: $('note').value})
    });
  } catch (_) {}
}
$('note').addEventListener('input', () => { clearTimeout(noteTimer); noteTimer = setTimeout(saveNote, 800); });
$('note').addEventListener('change', saveNote);
$('note').addEventListener('keydown', ev => {
  if (ev.key !== 'Enter') return;
  ev.preventDefault();
  if (state && state.running_start === null) $('toggle').click(); else saveNote();
});

window.addEventListener('resize', () => {
  if ($('chart').clientWidth !== lastChartWidth) renderChart();
});
setInterval(tick, 250);
call('status');
</script>
</body>
</html>

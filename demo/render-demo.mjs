// Records demo/stage.html (driving the real app) into a 1080×1350 MP4 for LinkedIn.
//   node demo/render-demo.mjs fr     -> demo/out/snan-demo-fr.mp4
// Needs the app running on localhost:8000 with fresh demo data (php artisan migrate:fresh --seed).
import { spawn, spawnSync, execFileSync } from 'node:child_process';
import { writeFile, mkdir, mkdtemp, rm, cp } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(HERE, '..');
const STUDIO = resolve(ROOT, '../../');
const lang = process.argv[2] || 'fr';
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const FFMPEG = execFileSync(join(STUDIO, '.tools/bin/python'), ['-c', 'import imageio_ffmpeg;print(imageio_ffmpeg.get_ffmpeg_exe())']).toString().trim();

// The stage must share the app's origin to drive its pages.
const pub = join(ROOT, 'app/public/_demo');
await mkdir(pub, { recursive: true });
await cp(join(HERE, 'stage.html'), join(pub, 'stage.html'));
await cp(join(HERE, 'captions.json'), join(pub, 'captions.json'));

const profile = await mkdtemp(join(tmpdir(), 'demo-'));
const port = 9300 + Math.floor(Math.random() * 400);
const chrome = spawn('google-chrome', ['--headless=new', '--hide-scrollbars', '--run-all-compositor-stages-before-draw', `--remote-debugging-port=${port}`, `--user-data-dir=${profile}`, 'about:blank'], { stdio: 'ignore' });
let targets;
for (let i = 0; i < 60 && !targets; i++) { try { targets = await (await fetch(`http://127.0.0.1:${port}/json`)).json(); } catch { await sleep(200); } }
const ws = new WebSocket(targets.find((t) => t.type === 'page').webSocketDebuggerUrl);
await new Promise((r) => (ws.onopen = r));
let id = 0; const pending = new Map(), handlers = [];
ws.onmessage = (e) => {
  const m = JSON.parse(e.data);
  if (m.id && pending.has(m.id)) { pending.get(m.id)(m.result); pending.delete(m.id); }
  if (m.method) handlers.forEach((h) => h(m));
};
const send = (method, params = {}) => new Promise((r) => { const i = ++id; pending.set(i, r); ws.send(JSON.stringify({ id: i, method, params })); });
const val = async (expr) => (await send('Runtime.evaluate', { expression: expr, returnByValue: true })).result?.value;

await send('Page.enable');
await send('Runtime.enable');
handlers.push((m) => { if (m.method === 'Runtime.consoleAPICalled' && ['warning', 'error'].includes(m.params.type)) console.log('[stage]', m.params.args.map((a) => a.value).join(' ')); });
await send('Emulation.setDeviceMetricsOverride', { width: 540, height: 675, deviceScaleFactor: 2, mobile: false });

const frames = [];
handlers.push((m) => {
  if (m.method !== 'Page.screencastFrame') return;
  frames.push({ data: m.params.data, ts: Date.now() / 1000 });
  send('Page.screencastFrameAck', { sessionId: m.params.sessionId });
});
const loaded = new Promise((r) => handlers.push((m) => m.method === 'Page.loadEventFired' && r()));
await send('Page.navigate', { url: `http://localhost:8000/_demo/stage.html?lang=${lang}` });
await loaded;
await send('Page.startScreencast', { format: 'jpeg', quality: 92, maxWidth: 1080, maxHeight: 1350, everyNthFrame: 1 });
let t0, tEnd;
for (let i = 0; i < 1500 && !tEnd; i++) { await sleep(200); t0 ??= await val('window.__t0'); tEnd = await val('window.__tEnd'); }
await sleep(800);
await send('Page.stopScreencast');
if (!t0 || !tEnd) throw new Error('stage never reported start/end');

const from = t0 / 1000, to = tEnd / 1000 + 0.6;
const kept = frames.filter((f) => f.ts >= from && f.ts <= to);
const before = frames.filter((f) => f.ts < from).at(-1);
if (before) kept.unshift({ ...before, ts: from });
const dir = await mkdtemp(join(tmpdir(), 'demo-frames-'));
let list = '';
for (let i = 0; i < kept.length; i++) {
  const f = join(dir, `${String(i).padStart(5, '0')}.jpg`);
  await writeFile(f, Buffer.from(kept[i].data, 'base64'));
  list += `file '${f}'\nduration ${Math.max((kept[i + 1]?.ts ?? to) - kept[i].ts, 0.001).toFixed(4)}\n`;
}
list += `file '${join(dir, String(kept.length - 1).padStart(5, '0') + '.jpg')}'\n`;
await writeFile(join(dir, 'list.txt'), list);
await mkdir(join(HERE, 'out'), { recursive: true });
const mp4 = join(HERE, 'out', `snan-demo-${lang}.mp4`);
const r = spawnSync(FFMPEG, ['-y', '-loglevel', 'error', '-f', 'concat', '-safe', '0', '-i', join(dir, 'list.txt'),
  '-f', 'lavfi', '-i', 'anullsrc=channel_layout=stereo:sample_rate=44100',
  '-vf', 'fps=30,scale=1080:1350:flags=lanczos,format=yuv420p', '-c:v', 'libx264', '-preset', 'slow', '-crf', '18', '-profile:v', 'high',
  '-c:a', 'aac', '-b:a', '128k', '-shortest', '-movflags', '+faststart', mp4], { stdio: 'inherit' });
// A cover frame from the hook, once the reply is on screen.
const cover = kept[Math.min(kept.length - 1, Math.round(kept.length * 0.075))];
await writeFile(join(HERE, 'out', `snan-demo-${lang}-cover.jpg`), Buffer.from(cover.data, 'base64'));
await rm(dir, { recursive: true, force: true });
await rm(pub, { recursive: true, force: true });
ws.close();
chrome.kill();
if (r.status) throw new Error('ffmpeg failed');
console.log(`${mp4}  ${kept.length} frames, ${(to - from).toFixed(1)} s`);

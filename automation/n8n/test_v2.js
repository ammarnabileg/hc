// n8n Code-node harness for the feature build (v2).
const fs = require('fs');
const vm = require('vm');

const wf = JSON.parse(fs.readFileSync('/home/user/hc/automation/n8n/Watad_Unified_Engine_Brand_KB_driven.json', 'utf8'));
const N = Object.fromEntries(wf.nodes.map(n => [n.name, n]));
const items = a => a.map(j => ({ json: j }));

function run(nodeName, { input = [], nodes = {}, executionId = 'ex1' } = {}) {
  const mk = arr => ({
    all: (b = 0) => (Array.isArray(arr[0]) ? (arr[b] || []) : arr),
    first: () => { const a = Array.isArray(arr[0]) ? (arr[0] || []) : arr; if (!a.length) throw new Error('no items'); return a[0]; },
  });
  const ctx = {
    $input: mk(input),
    $: n => { if (!(n in nodes)) throw new Error(`node "${n}" did not run`); return mk(nodes[n]); },
    $execution: { id: executionId },
    console: { log() {} },
    JSON, Math, Date, String, Number, Object, Array, Intl, RegExp, Error, isNaN,
    parseInt, parseFloat, encodeURIComponent,
  };
  vm.createContext(ctx);
  return vm.runInContext(`(function(){${N[nodeName].parameters.jsCode}})()`, ctx, { filename: nodeName });
}

let fails = 0;
const check = (l, c, x) => c ? console.log('  ok   ' + l)
  : (fails++, console.log('  FAIL ' + l, x === undefined ? '' : JSON.stringify(x)));

// ============================================================ Extract Brand Targets
console.log('\n== Extract Brand Targets ==');
let r = run('Extract Brand Targets', {
  nodes: { 'Zernio: List Profiles': items([{ profiles: [
    { _id: 'p1', name: 'Watad', accounts: [{ platform: 'LinkedIn', _id: 'li1' }] },
    { _id: 'p2', name: 'Navid' }] }]) },
});
check('daily: one target per profile', r.length === 2 && r[0].json.profileId === 'p1', r.length);
check('daily: platform keys lowercased', r[0].json.accounts.linkedin === 'li1', r[0].json.accounts);
check('daily: mode tagged', r[0].json._mode === 'daily');

r = run('Extract Brand Targets', {
  nodes: {
    'Forced Topic': items([{ Company: 'watad', Forced_Topic: 'OT patching' }]),
    'Analyze Reference Image': items([{ content: [{ text: 'dark control room' }] }]),
  },
});
check('form: single target with brand', r.length === 1 && r[0].json.brand === 'watad');
check('form: forced topic + image analysis carried',
  r[0].json.forced_topic === 'OT patching' && r[0].json.image_analysis === 'dark control room');

let threw = false;
try { run('Extract Brand Targets', { nodes: { 'Zernio: List Profiles': items([{}]) } }); }
catch (e) { threw = /no profiles/.test(e.message); }
check('daily: empty profile list is a hard error', threw);

// ============================================================ Map Companies (Daily)
console.log('\n== Map Companies (Daily) — registry + topic memory ==');
const TARGETS = items([
  { brand: '', profileId: 'p1', profileName: 'Watad', accounts: { linkedin: 'li1' }, forced_topic: '', image_analysis: '' },
  { brand: '', profileId: 'p9', profileName: 'Ghost Co', accounts: {}, forced_topic: '', image_analysis: '' },
]);
const RESOLVED = items([
  { resolved: true, matched_by: 'profile_id', brand: 'watad', brand_name: 'Watad Digital',
    kb: 'Registry KB.', never_say: 'no prices', brand_tone: 'registry tone', brand_focus: 'registry focus',
    brand_audience: 'registry audience', anti_hype: 'registry anti-hype', visual_style: 'registry visuals',
    hashtags: '#Registry', escalation_email: 'ops@wataddigital.com' },
  { error: 'Brand KB: no row matched.' },   // registry threw, onError let it through
]);
const RECENT = items([
  { brand: 'watad', topic_title: 'Old topic A' },
  { brand: 'watad', topic_title: 'Old topic B' },
  { brand: 'watad', topic_title: 'Old topic B' },
  { brand: 'navid', topic_title: 'Navid topic' },
]);

r = run('Map Companies (Daily)', {
  input: RECENT,
  nodes: { 'Extract Brand Targets': TARGETS, 'Brand KB Registry (Content)': RESOLVED },
});
check('registry answer wins', r[0].json.brand_tone === 'registry tone', r[0].json.brand_tone);
check('brand_source names the match', r[0].json.brand_source === 'registry:profile_id', r[0].json.brand_source);
check('never_say carried for the compliance gate', r[0].json.never_say === 'no prices');
check('accounts from the Zernio profile', r[0].json.accounts.linkedin === 'li1');
check('topic memory scoped to the brand + deduped',
  r[0].json.recent_topics === 'Old topic B | Old topic A', r[0].json.recent_topics);
check('unresolvable, unknown-to-code target is dropped', r.length === 1, r.map(x => x.json.brand));

// registry down entirely -> code fallback keeps the engine alive
r = run('Map Companies (Daily)', {
  input: [],
  nodes: {
    'Extract Brand Targets': items([{ brand: 'navid', profileId: '', profileName: '', accounts: {} }]),
    'Brand KB Registry (Content)': items([{ error: 'boom' }]),
  },
});
check('falls back to code when the registry fails',
  r.length === 1 && r[0].json.brand === 'navid' && r[0].json.brand_source === 'fallback_code');
check('fallback still yields an escalation address', r[0].json.escalation_email === 'A.Nabil@wataddigital.com');
check('no topic memory -> empty string', r[0].json.recent_topics === '');

threw = false;
try {
  run('Map Companies (Daily)', {
    input: [],
    nodes: { 'Extract Brand Targets': items([{ brand: 'ghost', profileName: 'Ghost' }]),
             'Brand KB Registry (Content)': items([{ error: 'x' }]) },
  });
} catch (e) { threw = /No brand could be resolved/.test(e.message); }
check('nothing resolvable at all throws a useful error', threw);

// ======================================================== Apply Compliance Verdict
console.log('\n== Apply Compliance Verdict ==');
const ROW = {
  run_id: 'c1', brand: 'watad', topic_title: 'T', never_say: 'prices, delivery dates',
  facebook_post: 'FB copy', instagram_post: 'IG copy', linkedin_post: 'LI copy',
  twitter_post: 'TW copy', tiktok_caption: 'TT copy',
};
const verdict = o => items([{ output: JSON.stringify(Object.assign(
  { unsupported_claims: false, forbidden_content: false, off_brand: false, wrong_language: false, evidence: [], summary: 'looks fine' }, o)) }]);

r = run('Apply Compliance Verdict', { input: verdict({}), nodes: { 'Parse Content JSON': items([ROW]) } });
check('clean content passes', r[0].json.compliance_status === 'PASS' && r[0].json.compliance_ok === true, r[0].json.compliance_notes);

r = run('Apply Compliance Verdict', {
  input: verdict({}),
  nodes: { 'Parse Content JSON': items([Object.assign({}, ROW, { linkedin_post: 'Our prices start at 10k' })]) },
});
check('never_say term blocks deterministically',
  r[0].json.compliance_status === 'BLOCKED' && /forbidden thing: "prices"/.test(r[0].json.compliance_notes), r[0].json.compliance_notes);

r = run('Apply Compliance Verdict', {
  input: verdict({}),
  nodes: { 'Parse Content JSON': items([Object.assign({}, ROW, { twitter_post: 'x'.repeat(300) })]) },
});
check('over-long tweet blocks', /over 280/.test(r[0].json.compliance_notes));

r = run('Apply Compliance Verdict', {
  input: verdict({ unsupported_claims: true, evidence: ['we serve 900 clients'] }),
  nodes: { 'Parse Content JSON': items([ROW]) },
});
check('reported unsupported claim blocks', r[0].json.compliance_status === 'BLOCKED');
check('evidence surfaced to the human', /we serve 900 clients/.test(r[0].json.compliance_notes));

r = run('Apply Compliance Verdict', {
  input: verdict({ off_brand: true }), nodes: { 'Parse Content JSON': items([ROW]) },
});
check('off_brand alone is a note, not a block',
  r[0].json.compliance_status === 'PASS' && /off-brand/.test(r[0].json.compliance_notes));

r = run('Apply Compliance Verdict', {
  input: items([{ output: 'the reviewer rambled without JSON' }]),
  nodes: { 'Parse Content JSON': items([ROW]) },
});
check('unreadable reviewer output does not block on its own',
  r[0].json.compliance_status === 'PASS' && /no JSON/.test(r[0].json.compliance_notes), r[0].json.compliance_notes);

r = run('Apply Compliance Verdict', {
  input: verdict({}),
  nodes: { 'Parse Content JSON': items([Object.assign({}, ROW,
    { facebook_post: '', instagram_post: '', linkedin_post: '' })]) },
});
check('mostly-empty content blocks', /mostly empty/.test(r[0].json.compliance_notes));

// ============================================================ Plan Analytics Pull
console.log('\n== Plan Analytics Pull ==');
function riyadhAgo(hours) {
  const t = new Date(Date.now() - hours * 3600000 + 3 * 3600000);
  return t.toISOString().slice(0, 19) + ' Asia/Riyadh';
}
r = run('Plan Analytics Pull', { input: items([
  { run_id: 'a', zernio_post_id: 'z1', scheduled_for: riyadhAgo(30), perf_stage: '' },
  { run_id: 'b', zernio_post_id: 'z2', scheduled_for: riyadhAgo(30), perf_stage: '24h' },
  { run_id: 'c', zernio_post_id: 'z3', scheduled_for: riyadhAgo(80), perf_stage: '24h' },
  { run_id: 'd', zernio_post_id: 'z4', scheduled_for: riyadhAgo(80), perf_stage: '72h' },
  { run_id: 'e', zernio_post_id: 'z5', scheduled_for: riyadhAgo(3), perf_stage: '' },
  { run_id: 'f', zernio_post_id: '',   scheduled_for: riyadhAgo(30), perf_stage: '' },
  { run_id: 'g', zernio_post_id: 'z7', scheduled_for: 'not a date', perf_stage: '' },
])});
const plan = Object.fromEntries(r.map(x => [x.json.run_id, x.json.stage]));
check('30h + no stage -> 24h', plan.a === '24h');
check('30h + 24h done -> skipped', !plan.b);
check('80h + 24h done -> 72h', plan.c === '72h');
check('80h + 72h done -> skipped', !plan.d);
check('3h old -> too early', !plan.e);
check('no post id -> skipped', !plan.f);
check('unparseable date -> skipped', !plan.g);
check('only the due rows come out', r.length === 2, r.length);
check('riyadh offset applied (30h reads as ~30h)',
  Math.abs(r.find(x => x.json.run_id === 'a').json.hours_since - 30) <= 1,
  r.find(x => x.json.run_id === 'a').json.hours_since);

// ================================================================ Parse Analytics
console.log('\n== Parse Analytics ==');
const PLAN = items([
  { run_id: 'a', brand: 'watad', zernio_post_id: 'z1', stage: '24h', topic_title: 'T', published_platforms: 'linkedin' },
  { run_id: 'b', brand: 'watad', zernio_post_id: 'z2', stage: '24h', topic_title: 'T2', published_platforms: 'facebook' },
]);
r = run('Parse Analytics', {
  input: items([
    { statusCode: 200, body: { data: { analytics: { impressions: 1200, reach: 900, likes: 30, comments: 4, shares: 2, saves: 1 } } } },
    { statusCode: 202, body: {} },
  ]),
  nodes: { 'Plan Analytics Pull': PLAN },
});
check('200 parsed', r[0].json.ok === true && r[0].json.impressions === 1200, r[0].json);
check('engagement derived when absent', r[0].json.engagement === 37, r[0].json.engagement);
check('202 marked not-ok so the stage cursor is not advanced', r[1].json.ok === false && r[1].json.http_status === 202);

r = run('Parse Analytics', {
  input: items([{ statusCode: 200, body: { analytics: { impressions: 5, engagement: 99 } } }]),
  nodes: { 'Plan Analytics Pull': items([PLAN[0].json]) },
});
check('flat body.analytics shape also parsed', r[0].json.impressions === 5 && r[0].json.engagement === 99);

// ============================================================= Plan Log Rotation
console.log('\n== Plan Log Rotation ==');
const day = n => new Date(Date.now() - n * 86400000).toISOString();
r = run('Plan Log Rotation', { input: items([
  { event_key: 'e1', timestamp: day(200) },
  { event_key: 'e2', timestamp: day(150) },
  { event_key: 'e3', timestamp: day(10) },
  { event_key: 'e4', timestamp: day(300) },
])});
check('archives only the contiguous old prefix', r.length === 2, r.map(x => x.json.event_key));
check('delete count matches what was archived', r[0].json._delete_count === 2);
check('a recent row stops the walk (e4 kept even though old)',
  !r.some(x => x.json.event_key === 'e4'));

r = run('Plan Log Rotation', { input: items([{ event_key: 'e1', timestamp: day(3) }]) });
check('nothing old enough -> no deletion', r.length === 0);
r = run('Plan Log Rotation', { input: items([{ event_key: 'e1', timestamp: 'garbage' }]) });
check('unparseable timestamp stops the walk', r.length === 0);
r = run('Plan Log Rotation', { input: [] });
check('empty tab -> no deletion', r.length === 0);

// ======================================================== Approval webhook chain
console.log('\n== Approval webhook ==');
const req = q => run('Parse Approval Request', { input: items([{ query: q }]) })[0].json;

let a = req({ entity: 'content', run_id: 'c1', token: 'tok', action: 'approve' });
check('first click only asks for confirmation', a.needs_confirm === true && a.valid === true);
a = req({ entity: 'content', run_id: 'c1', token: 'tok', action: 'approve', confirm: '1' });
check('confirmed click proceeds', a.needs_confirm === false && a.is_content === true);
check('bad entity rejected', req({ entity: 'x', run_id: 'c1', token: 't', action: 'approve' }).valid === false);
check('bad action rejected', req({ entity: 'reply', run_id: 'c1', token: 't', action: 'delete' }).valid === false);
check('missing token rejected', req({ entity: 'reply', run_id: 'c1', action: 'approve' }).valid === false);
check('reply entity routes away from content', req({ entity: 'reply', run_id: 'r1', token: 't', action: 'reject', confirm: '1' }).is_content === false);

const APPROVE_REQ = items([{ entity: 'content', action: 'approve', run_id: 'c1', token: 'tok', confirm: true, valid: true, needs_confirm: false }]);
const dc = (rows, request = APPROVE_REQ) =>
  run('Decide Content', { input: items(rows), nodes: { 'Parse Approval Request': request } })[0].json;

check('valid token + pending row approves',
  dc([{ run_id: 'c1', approval_token: 'tok', approval_status: 'PENDING', topic_title: 'T' }]).new_status === 'approved');
check('wrong token refused',
  dc([{ run_id: 'c1', approval_token: 'other', approval_status: 'PENDING' }]).ok === false);
check('already-approved row is not re-flipped',
  /Already handled/.test(dc([{ run_id: 'c1', approval_token: 'tok', approval_status: 'approved' }]).message));
check('missing row reported', /No row found/.test(dc([{ run_id: 'zzz', approval_token: 'tok' }]).message));
check('reject writes rejected', dc([{ run_id: 'c1', approval_token: 'tok', approval_status: '' }],
  items([{ entity: 'content', action: 'reject', run_id: 'c1', token: 'tok', confirm: true, valid: true, needs_confirm: false }])).new_status === 'rejected');

const dr = rows => run('Decide Reply', {
  input: items(rows),
  nodes: { 'Parse Approval Request': items([{ entity: 'reply', action: 'approve', run_id: 'r1', token: 'tk', confirm: true, valid: true, needs_confirm: false }]) },
})[0].json;
check('reply: pending status accepted', dr([{ run_id: 'r1', approval_token: 'tk', status: 'PENDING' }]).ok === true);
check('reply: already SENT not re-approved', dr([{ run_id: 'r1', approval_token: 'tk', status: 'SENT' }]).ok === false);

// -------- Build Approval Page
const page = (request, decideContent) => run('Build Approval Page', {
  nodes: Object.assign({ 'Parse Approval Request': items([request]) },
    decideContent ? { 'Decide Content': items([decideContent]) } : {}),
})[0].json.html;

let html = page({ entity: 'content', action: 'approve', run_id: 'c1', token: 'tok', valid: true, needs_confirm: true });
check('confirm page carries a confirm=1 link', /confirm=1/.test(html) && /YES, APPROVE/.test(html));
check('confirm page does not leak raw html from input', !/<script/i.test(html));

html = page({ entity: 'content', action: 'reject', run_id: 'c1', token: 'tok', valid: true, needs_confirm: true });
check('reject confirmation says reject', /YES, REJECT/.test(html));

html = page({ entity: 'content', action: 'approve', run_id: 'c1', valid: true, needs_confirm: false },
  { ok: true, message: 'Approved. It will be picked up within 5 minutes.', topic: 'A <b>topic</b>' });
check('result page shows the decision', /Approved\. It will be picked up/.test(html));
check('topic is html-escaped', /A &lt;b&gt;topic&lt;\/b&gt;/.test(html));

html = page({ entity: 'x', valid: false, needs_confirm: false, message: 'Bad approval link: unknown entity' });
check('invalid link renders an error page', /Link not usable/.test(html));

// ============================================================ Resolve Design Brief
console.log('\n== Resolve Design Brief (via the registry) ==');
r = run('Resolve Design Brief', {
  input: items([{ resolved: true, brand: 'navid', brand_name: 'Navid', kb_facts: 'Navid registry KB.',
    services: 'AI deployment', brand_tone: 'registry design tone', never_say: 'no roadmap dates',
    hashtags: '#NavidReg', escalation_email: 'design@navid.sa' }]),
  nodes: { 'Design Studio (Form)': items([{ Output_Type: 'Carousel', Design: 'Yehia', Forced_Topic: '' }]) },
});
let b = r[0].json;
check('brand from the registry', b.brand === 'navid');
check('escalation from the registry', b.escalation_email === 'design@navid.sa');
check('registry tone folded into VOICE', /registry design tone/.test(b.ai_prompt));
check('registry never_say folded into BRAND RULE', /no roadmap dates/.test(b.ai_prompt));
check('registry kb_facts added to BRAND FACTS', /Navid registry KB/.test(b.ai_prompt));
check('variant facts survive', /OALL average score 65\.68/.test(b.ai_prompt));
check('never_say exported for the slide check', b.never_say === 'no roadmap dates');
check('approval token minted', typeof b.approval_token === 'string' && b.approval_token.length >= 20);
check('carousel -> 5 slides', b.slide_total === 5);

r = run('Resolve Design Brief', {
  input: items([{ error: 'registry down' }]),
  nodes: { 'Design Studio (Form)': items([{ Output_Type: 'Image', Design: 'Navid Company', Forced_Topic: 'sovereign AI' }]) },
});
b = r[0].json;
check('registry failure falls back cleanly', b.brand === 'navid' && b.brand_source === 'fallback_code');
check('forced topic still honoured', /"sovereign AI"/.test(b.ai_prompt));
check('default escalation on fallback', b.escalation_email === 'A.Nabil@wataddigital.com');

// ============================================================== Collect Slide URLs
console.log('\n== Collect Slide URLs (design compliance) ==');
const BUILD = items([
  { run_id: 'd1', slide_index: 0, topic_title: 'T', facebook_post: 'FB', instagram_post: 'IG',
    linkedin_post: 'LI', twitter_post: 'TW', tiktok_caption: 'TT' },
  { run_id: 'd1', slide_index: 1, topic_title: 'T', facebook_post: 'FB', instagram_post: 'IG',
    linkedin_post: 'LI', twitter_post: 'TW', tiktok_caption: 'TT' },
]);
const brief = extra => items([Object.assign(
  { brand: 'navid', never_say: 'roadmap dates, pricing', approval_token: 'dtok' }, extra)]);

r = run('Collect Slide URLs', {
  input: items([{}, {}]),
  nodes: {
    'Build Slide HTML': BUILD,
    'Render HTML → PNG': items([{ url: 'https://hcti/1.png' }, { url: 'https://hcti/2.png' }]),
    'Upload to Google Drive': items([{ id: 'D1' }, { id: 'D2' }]),
    'Resolve Design Brief': brief(),
  },
});
check('one row per run', r.length === 1);
check('both slides collected in order',
  r[0].json.Photo_Links.split(',').length === 2 && /id=D1/.test(r[0].json.Photo_Links.split(',')[0]));
check('clean design passes', r[0].json.compliance_status === 'PASS', r[0].json.compliance_notes);
check('brand + token carried onto the row', r[0].json.brand === 'navid' && r[0].json.approval_token === 'dtok');

const BAD = BUILD.map(x => ({ json: Object.assign({}, x.json, { linkedin_post: 'Our pricing is public' }) }));
r = run('Collect Slide URLs', {
  input: items([{}, {}]),
  nodes: {
    'Build Slide HTML': BAD,
    'Render HTML → PNG': items([{ url: 'u1' }, { url: 'u2' }]),
    'Upload to Google Drive': items([{ id: 'D1' }, { id: 'D2' }]),
    'Resolve Design Brief': brief(),
  },
});
check('design never_say hit blocks',
  r[0].json.compliance_status === 'BLOCKED' && /pricing/.test(r[0].json.compliance_notes), r[0].json.compliance_notes);

// ============================================================== Parse Content JSON
console.log('\n== Parse Content JSON (tokens + KB passthrough) ==');
const BRIEFS = items([{ brand: 'watad', profileId: 'p1', profileName: 'Watad Digital',
  escalation_email: 'ops@w.com', never_say: 'no prices', kb: 'KB text', account_ids: '{}' }]);
r = run('Parse Content JSON', {
  input: items([{ output: JSON.stringify({ topic_title: 'T', facebook_post: 'F', instagram_post: 'I',
    linkedin_post: 'L', twitter_post: 'W', tiktok_caption: 'K', photo_prompt: 'P', video_prompt: 'V' }) }]),
  nodes: { 'Map Companies (Daily)': BRIEFS },
});
b = r[0].json;
check('approval token minted per row', typeof b.approval_token === 'string' && b.approval_token.length >= 20);
check('never_say + kb forwarded to the compliance gate', b.never_say === 'no prices' && b.kb_for_check === 'KB text');
check('escalation forwarded', b.escalation_email === 'ops@w.com');
check('run_id generated', /^RUN-watad-/.test(b.run_id), b.run_id);

const tokens = new Set();
for (let i = 0; i < 200; i++) tokens.add(run('Parse Content JSON', {
  input: items([{ output: '{"topic_title":"T","twitter_post":"W"}' }]),
  nodes: { 'Map Companies (Daily)': BRIEFS },
})[0].json.approval_token);
check('tokens are unique across runs', tokens.size === 200, tokens.size);

console.log(fails ? `\n${fails} FAILURE(S)` : '\nall assertions passed');
process.exit(fails ? 1 : 0);

import { spawn } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const ROOT = path.resolve('public/assets/docs');
const HTML_PATH = path.join(ROOT, 'role-guide.html');
const PDF_NAME = 'SGC-SmartTrack-User-Roles-Guide.pdf';
const PDF_PUBLIC = path.join(ROOT, PDF_NAME);
const PDF_MEMO = path.resolve('..', 'docs', PDF_NAME);
const CHROME = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const GUIDE_URL = process.env.ROLE_GUIDE_URL || 'http://sgc-smarttrack-prod.test/assets/docs/role-guide.html';

const shot = (name) => `${name}.png`;

const guides = [
    {
        id: 'super',
        label: '1. Super Admin',
        summary: 'Opens the portal, creates the Division Admin, watches live counts, and can clear school FAT data for a new cycle.',
        who: 'Platform owner. One Super Admin is created on /setup while the system is empty. Super does not encode school packets or validate MOVs.',
        canDo: [
            'Create the first Super Admin on /setup.',
            'Create Division Admin accounts under Users & roles.',
            'Watch Overview, Divisions, and Cycles with live database counts.',
            'Reset School Heads, Encoders, packets, and MOV files without dropping Super or Division accounts.',
        ],
        cannotDo: [
            'Register as a school or accept School Heads.',
            'Encode indicators, upload MOVs, or certify QA.',
            'Accept or return school files in the validation queue.',
        ],
        screens: [
            ['Overview', '/super', 'Live KPIs for schools, packets, and cycle progress.', 'super-overview'],
            ['Divisions', '/super/divisions', 'SDO Cadiz City record for this single-division portal.', 'super-divisions'],
            ['Users & roles', '/super/users', 'Create Division Admin and reset FAT data.', 'super-users'],
            ['Cycles', '/super/cycles', 'Confirm the open FAT cycle and deadline.', 'super-cycles'],
        ],
        steps: [
            [1, 'Create Super Admin (first run only)', 'Open https://sgc-smarttrack.com/setup while no Super Admin exists. Enter full name, email, and password (minimum 8 characters). Click Create Super Admin. This form disappears after the first Super exists.', '/setup', 'Create Super Admin', null],
            [2, 'Sign in', 'Open Sign in. Use the Super Admin email and password. The portal opens Overview.', '/login', 'Sign in to SmartTrack', 'login'],
            [3, 'Create Division Admin', 'Open Users & roles. Fill Full name, DepEd email, Office (example: SGOD / SGC Focal), Position from the list, Password, and Confirm password. Click Create Division Admin. Super is the only role that can create this account. School Heads still self-register later.', '/super/users', 'Create Division Admin', 'super-users'],
            [4, 'Hand over Division credentials', 'Give the Division Admin their email and temporary password. They should sign in and change the password under Account.', '/account', null, 'account'],
            [5, 'Confirm the FAT cycle', 'Open Cycles. Confirm the 2026 SGC Functionality Assessment window is open. Schools cannot encode or submit without an open cycle.', '/super/cycles', null, 'super-cycles'],
            [6, 'Watch live counts', 'Overview and Divisions show real database counts, not sample figures. Empty production is expected until School Heads register and Division accepts them.', '/super', null, 'super-overview'],
            [7, 'Reset FAT data only when needed', 'On Users & roles, scroll to Reset operational data. Type RESET FAT DATA exactly, then click Clear school and FAT data. This removes School Heads, Encoders, packets, and MOV files. Super and Division stay. Do not use this as a daily tool.', '/super/users', 'Type RESET FAT DATA', 'super-users'],
        ],
        notes: [
            'Production starts empty on purpose. Do not seed demo school accounts on the live site.',
            'This portal is one SDO (Cadiz City), one domain. Super does not create extra divisions as tenants.',
        ],
    },
    {
        id: 'division',
        label: '2. Division Admin',
        summary: 'Accepts School Heads, validates MOV files, and records Functional or Not Yet Functional for SDO Cadiz City.',
        who: 'SDO Cadiz City SGC focal or validator. Created by Super Admin. Does not register on the public Register page.',
        canDo: [
            'Accept pending School Head registrations.',
            'See schools that belong to this division.',
            'Review submitted packets, download MOVs, Accept or Return a file with a reason.',
            'Complete review so the school result is posted.',
        ],
        cannotDo: [
            'Create Super Admin or another Division Admin.',
            'Accept Encoders. That is the School Head.',
            'Encode a school packet or certify School Head QA.',
        ],
        screens: [
            ['Overview', '/division', 'Live submission and validation counts.', 'division-overview'],
            ['Validation queue', '/division/queue', 'Packets waiting for MOV review.', 'division-queue'],
            ['Schools', '/division/schools', 'Accepted schools and packet status.', 'division-schools'],
            ['Registrations', '/division/registrations', 'Accept School Heads only.', 'division-registrations'],
            ['Review packet', '/division/queue/{id}', 'Accept or Return each MOV file.', 'division-review'],
            ['Alerts', '/division/alerts', 'Overdue or returned packets.', 'division-alerts'],
        ],
        steps: [
            [1, 'Sign in', 'Use the DepEd email Super Admin created. Change the password under Account after first sign-in.', '/login', 'Sign in to SmartTrack', 'login'],
            [2, 'Accept School Heads', 'Open Registrations. Each row is a pending School Head. Confirm school name and School ID are unique, then click Accept. Teachers never appear here.', '/division/registrations', 'Accept', 'division-registrations'],
            [3, 'Confirm the school list', 'Open Schools. Accepted School Heads now appear with their School ID. Encoders stay pending until that Head accepts them.', '/division/schools', null, 'division-schools'],
            [4, 'Wait for a submitted packet', 'Open Validation queue. A row appears after the School Head certifies QA and clicks Submit to Division.', '/division/queue', null, 'division-queue'],
            [5, 'Open Review', 'Click Review on a packet. Download each MOV. Check the Minimum MOV for every Yes and the Validity Form.', '/division/queue', 'Review', 'division-review'],
            [6, 'Accept or Return each file', 'Click Accept on a valid file. For an invalid file, type why in Return reason, then click Return. Only that file goes back to the school. Do not ask the school to rebuild the whole packet.', null, 'Accept or Return', 'division-review'],
            [7, 'Complete the review', 'When files are decided, complete the review. Functional status needs 10 of 12 Yes answers with valid MOVs. The school sees the result on Submit.', null, 'Complete review', 'division-review'],
            [8, 'Watch returned packets come back', 'If you returned a file, the school replaces it, the Head recertifies QA, and resubmits. The packet returns to the queue. Check Alerts for overdue schools.', '/division/alerts', null, 'division-alerts'],
        ],
        notes: [
            'Division accepts School Heads only. Encoder accept lives under the School Head Encoders page.',
            'Do not ask a school to rebuild the whole packet for one invalid MOV.',
        ],
    },
    {
        id: 'school_head',
        label: '3. School Head',
        summary: 'Owns the school identity, accepts teachers, certifies QA, submits to Division, and can remove or withdraw unapproved files.',
        who: 'Principal, Head Teacher, Teacher-in-Charge, or Officer-in-Charge. Registers with a unique school name and School ID.',
        canDo: [
            'Register as School Head and wait for Division accept.',
            'Accept Encoder (teacher) registrations for the same School ID.',
            'Encode indicators and upload MOVs, or let the Encoder do that work.',
            'Remove an unapproved MOV, certify School Head QA, submit, and withdraw if Division has not accepted any file yet.',
        ],
        cannotDo: [
            'Create Division Admin.',
            'Accept another School Head.',
            'Register a second Head with the same school name or School ID.',
            'Remove a MOV that Division already marked valid.',
        ],
        screens: [
            ['Dashboard', '/school', 'Packet progress and 6-step FAT path.', 'head-dashboard'],
            ['My assessment', '/school/assessment', 'Yes / No for 12 functionality indicators.', 'head-assessment'],
            ['MOV files', '/school/movs', 'Upload, remove, or withdraw files.', 'head-movs'],
            ['Submit', '/school/submit', 'Certify QA, submit, or withdraw.', 'head-submit'],
            ['Encoders', '/school/encoders', 'Accept teachers for this School ID.', 'head-encoders'],
            ['Notifications', '/school/notifications', 'Encoder requests and Division returns.', 'head-notifications'],
        ],
        steps: [
            [1, 'Register as School Head', 'Open Register. Choose I am registering as → School Head. Select a School Head position from the list. Enter a unique school name and School ID. Use your official DepEd email and a password of at least 8 characters. Click Submit registration. You stay pending until Division accepts you.', '/register', 'I am registering as → School Head', 'register-head'],
            [2, 'Wait for Division accept', 'Division Admin opens Registrations and clicks Accept. After that you can sign in and approve teachers. Encoders cannot register until you are active.', '/login', null, 'login'],
            [3, 'Accept teachers', 'Open Encoders. Teachers who used your exact school name and School ID appear here. Click Accept so they can encode.', '/school/encoders', 'Accept', 'head-encoders'],
            [4, 'Encode the 12 indicators', 'Open My assessment. Answer Yes or No for each functionality indicator. A Yes needs a Minimum MOV later. You or the Encoder can encode.', '/school/assessment', 'Yes or No', 'head-assessment'],
            [5, 'Check MOV files', 'Open MOV files. Confirm a file for every Yes plus the Validity Form. If a draft file is wrong, click Remove. If the Encoder asked for removal, that row shows Removal requested.', '/school/movs', 'Remove', 'head-movs'],
            [6, 'Certify School Head QA', 'Open Submit. When the checklist is clear, click Certify School Head QA. Teachers cannot click this button.', '/school/submit', 'Certify School Head QA', 'head-submit'],
            [7, 'Submit to Division', 'Click Submit to Division. The packet enters the Division validation queue. After this, files lock until you withdraw or Division returns a file.', '/school/submit', 'Submit to Division', 'head-submit'],
            [8, 'Withdraw if not yet approved', 'If you need to change files and Division has not marked any MOV valid, click Withdraw from Division. Then remove or replace files, certify QA again, and resubmit.', '/school/submit', 'Withdraw from Division', 'head-submit'],
            [9, 'Fix a returned MOV', 'If Division returned a file: the Encoder replaces only that file on MOV files. You certify QA again, then click Resubmit to Division.', '/school/movs', 'Resubmit to Division', 'head-movs'],
        ],
        notes: [
            'School name and School ID must be unique among School Heads.',
            'Encoder and Head share one packet through the same School ID.',
        ],
    },
    {
        id: 'school',
        label: '4. Encoder',
        summary: 'Teacher who encodes Yes / No, uploads MOVs, and requests removal of a wrong file. Cannot submit to Division.',
        who: 'Teacher, SGC coordinator, or other school staff on the Encoder position list. Registers only after an active School Head exists for that School ID.',
        canDo: [
            'Register as Encoder using the Head’s school name and School ID.',
            'Encode Yes / No on My assessment.',
            'Upload Minimum MOVs and the Validity Form.',
            'Request removal of an unapproved file so the School Head can delete it.',
            'Replace a MOV that Division returned.',
        ],
        cannotDo: [
            'Register before the School Head is active for that School ID.',
            'Use a different school name from the Head.',
            'Certify School Head QA, submit, or withdraw the packet.',
            'Remove a file directly. Request removal instead.',
        ],
        screens: [
            ['Dashboard', '/school', 'See packet progress. Submit stays locked for Encoder.', 'encoder-dashboard'],
            ['My assessment', '/school/assessment', 'Answer Yes or No for 12 FIs.', 'encoder-assessment'],
            ['MOV files', '/school/movs', 'Upload files and request removal.', 'encoder-movs'],
            ['Submit', '/school/submit', 'Read the checklist. QA and Submit are for the Head only.', 'encoder-submit'],
            ['Notifications', '/school/notifications', 'Accept notice, returned MOV, and Head actions.', 'encoder-notifications'],
        ],
        steps: [
            [1, 'Confirm the School Head is active', 'Your Principal must already be registered, accepted by Division, and active. Ask for the exact school name and School ID. You cannot register until that Head exists.', null, null, null],
            [2, 'Register as Encoder', 'Open Register. Choose I am registering as → Encoder. Select your position. Type the same school name and School ID as the Head. Use your DepEd email. Click Submit registration.', '/register', 'I am registering as → Encoder', 'register-encoder'],
            [3, 'Wait for School Head accept', 'You stay pending. The Head opens Encoders and clicks Accept. Then you can sign in and encode.', '/login', null, 'login'],
            [4, 'Encode all 12 indicators', 'Open My assessment. Click Yes or No for each functionality indicator. Yes requires a Minimum MOV on the next page.', '/school/assessment', 'Yes or No', 'encoder-assessment'],
            [5, 'Upload MOV files', 'Open MOV files. Upload the Minimum MOV for every Yes, plus the Validity Form. Wait for School Head review and Division validation.', '/school/movs', null, 'encoder-movs'],
            [6, 'Request removal if a file is wrong', 'If the packet is not yet approved and a file is wrong, click Request removal. The Head is notified and can Remove the file so you can upload again.', '/school/movs', 'Request removal', 'encoder-movs'],
            [7, 'Leave QA and Submit to the Head', 'Open Submit to see the checklist. Only the School Head can Certify School Head QA and Submit to Division.', '/school/submit', null, 'encoder-submit'],
            [8, 'Replace a returned file', 'If Division returns a MOV, replace only that file. The Head certifies QA again and resubmits. You do not rebuild the whole packet.', '/school/movs', null, 'encoder-movs'],
        ],
        notes: [
            'Position list changes with “I am registering as.” Encoder positions are teacher and SGC staff titles, not Principal titles.',
            'One School ID = one packet. Do not invent a second school code for the same school.',
        ],
    },
];

const matrix = [
    ['Create first Super Admin', 'Yes — /setup once', 'No', 'No', 'No'],
    ['Create Division Admin', 'Users & roles', 'No', 'No', 'No'],
    ['Accept School Head', 'No', 'Registrations', 'No', 'No'],
    ['Accept Encoder', 'No', 'No', 'Encoders', 'No'],
    ['Encode Yes / No', 'No', 'No', 'Yes', 'Yes'],
    ['Upload MOV', 'No', 'No', 'Yes', 'Yes'],
    ['Remove unapproved MOV', 'No', 'No', 'Remove', 'Request only'],
    ['Certify QA and submit', 'No', 'No', 'Yes', 'No'],
    ['Withdraw packet', 'No', 'No', 'If no MOV is valid yet', 'No'],
    ['Accept or return MOV', 'No', 'Validation queue', 'No', 'No'],
    ['Reset school and FAT data', 'Type RESET FAT DATA', 'No', 'No', 'No'],
];

const esc = (value) => String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;');

const figure = (file, caption) => {
    if (!file) {
        return '';
    }

    return `<figure class="shot"><img src="${esc(shot(file))}" alt="${esc(caption)}"><figcaption>${esc(caption)}</figcaption></figure>`;
};

const list = (items) => `<ul>${items.map((item) => `<li>${esc(item)}</li>`).join('')}</ul>`;

const roleHtml = (guide) => {
    const screens = guide.screens.map(([name, href, purpose, file]) => `
        <article class="screen">
            <h3>${esc(name)} <code>${esc(href)}</code></h3>
            <p>${esc(purpose)}</p>
            ${figure(file, name)}
        </article>`).join('');

    const seen = new Set();
    const steps = guide.steps.map(([n, title, detail, href, action, file]) => {
        const show = file && !seen.has(file);
        if (file) {
            seen.add(file);
        }

        return `
        <article class="step">
            <div class="n">${n}</div>
            <div>
                <h3>${esc(title)}</h3>
                <p>${esc(detail)}</p>
                ${action ? `<p><strong>Click:</strong> ${esc(action)}</p>` : ''}
                ${href ? `<p><strong>Page:</strong> <code>${esc(href)}</code></p>` : ''}
                ${show ? figure(file, title) : ''}
            </div>
        </article>`;
    }).join('');

    return `
    <section class="role" id="${esc(guide.id)}">
        <h1>${esc(guide.label)}</h1>
        <p class="lead">${esc(guide.summary)}</p>
        <p>${esc(guide.who)}</p>
        <div class="split">
            <div><h2>This role can</h2>${list(guide.canDo)}</div>
            <div><h2>This role cannot</h2>${list(guide.cannotDo)}</div>
        </div>
        <h2>Screens</h2>
        ${screens}
        <h2>Walkthrough</h2>
        <p>Follow these clicks in order. Button names match the live screens.</p>
        ${steps}
        <h2>Notes</h2>
        ${list(guide.notes)}
    </section>`;
};

const html = `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>SGC SmartTrack User Role Guide</title>
<style>
  :root { --ink:#12222a; --muted:#4d5f68; --line:#d5dee2; --teal:#1f7a74; --paper:#fff; }
  * { box-sizing: border-box; }
  body { margin: 0; color: var(--ink); background: var(--paper); font: 12.5px/1.5 "Segoe UI", Calibri, Arial, sans-serif; }
  .page { max-width: 900px; margin: 0 auto; padding: 24px 28px 48px; }
  h1 { font-size: 26px; margin: 0 0 8px; color: var(--teal); page-break-after: avoid; }
  h2 { font-size: 16px; margin: 22px 0 8px; color: var(--teal); page-break-after: avoid; }
  h3 { font-size: 13px; margin: 0 0 6px; }
  p, li { color: var(--ink); }
  .muted, figcaption { color: var(--muted); }
  .cover { min-height: 900px; display: flex; flex-direction: column; justify-content: center; border-bottom: 4px solid var(--teal); margin-bottom: 28px; padding-bottom: 40px; }
  .cover .kicker { letter-spacing: .16em; text-transform: uppercase; font-size: 11px; color: var(--teal); font-weight: 700; }
  .cover h1 { font-size: 34px; margin-top: 8px; }
  .cover .sub { font-size: 16px; color: var(--muted); max-width: 36em; }
  .meta { margin-top: 28px; }
  .meta p { margin: 4px 0; }
  .toc a { color: var(--teal); text-decoration: none; }
  table { width: 100%; border-collapse: collapse; font-size: 11.5px; margin: 10px 0 18px; }
  th, td { border: 1px solid var(--line); padding: 7px 8px; vertical-align: top; text-align: left; }
  th { background: #eef6f5; color: var(--teal); }
  code { font-family: Consolas, "Courier New", monospace; font-size: 11px; background: #eef3f4; padding: 1px 5px; border-radius: 4px; }
  .split { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
  .role { page-break-before: always; }
  .screen, .step { page-break-inside: avoid; margin: 0 0 16px; }
  .step { display: grid; grid-template-columns: 28px 1fr; gap: 10px; }
  .n { width: 28px; height: 28px; border-radius: 50%; background: var(--teal); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 12px; }
  .shot { margin: 8px 0 0; border: 1px solid var(--line); border-radius: 8px; overflow: hidden; background: #0b181c; }
  .shot img { display: block; width: 100%; height: auto; }
  .shot figcaption { background: #f4f7f8; padding: 6px 10px; font-size: 11px; }
  .flow { background: #eef6f5; border: 1px solid #cfe3e0; padding: 12px 16px; border-radius: 8px; }
  @page { size: A4; margin: 14mm 12mm; }
  @media print {
    .page { max-width: none; padding: 0; }
    .cover { min-height: auto; }
    a { color: inherit; text-decoration: none; }
  }
</style>
</head>
<body>
<div class="page">
  <section class="cover">
    <div class="kicker">Schools Division of Cadiz City · Negros Island Region</div>
    <h1>SGC SmartTrack</h1>
    <p class="sub">User Role Guide — Super Admin, Division Admin, School Head, and Encoder. Complete instructions with screen snapshots.</p>
    <div class="meta">
      <p><strong>Portal:</strong> https://sgc-smarttrack.com</p>
      <p><strong>Policy:</strong> DepEd Order 26, s. 2022 — School Governance Council Functionality Assessment Tool</p>
      <p><strong>Scope:</strong> One SDO, one domain. Not multi-tenant.</p>
      <p><strong>Date:</strong> 20 September 2026</p>
    </div>
  </section>

  <h1>Contents</h1>
  <ol class="toc">
    <li><a href="#how">How the portal works</a></li>
    <li><a href="#order">Order of work</a></li>
    <li><a href="#who">Who does what</a></li>
    <li><a href="#super">Super Admin</a></li>
    <li><a href="#division">Division Admin</a></li>
    <li><a href="#school_head">School Head</a></li>
    <li><a href="#school">Encoder</a></li>
    <li><a href="#return">Returned or invalid MOV</a></li>
    <li><a href="#register">Registration rules and positions</a></li>
  </ol>

  <section id="how">
    <h1>How the portal works</h1>
    <p>SGC SmartTrack is the school-to-division FAT platform for SDO Cadiz City. A school encodes 12 functionality indicators. <strong>Functional</strong> status requires 10 of 12 Yes answers, each Yes backed by a valid Minimum MOV. The Validity Form is required for every packet.</p>
    <p>One FAT packet is shared by School ID and cycle. The Encoder and School Head of the same school work on the same packet.</p>
    <p>The school path is:</p>
    <div class="flow"><strong>Encode → MOV files → School Head QA → Submit to Division → Validation → Result (Functional 10/12)</strong></div>
  </section>

  <section id="order">
    <h1>Order of work</h1>
    <ol>
      <li>On a new production site, open <code>/setup</code> and create Super Admin. This is available only while no Super exists.</li>
      <li>Super Admin signs in and creates the Division Admin under Users &amp; roles.</li>
      <li>The School Head registers with a unique school name and School ID, then waits for Division Accept.</li>
      <li>Teachers register as Encoder using that same school name and School ID. They cannot register until the Head is active.</li>
      <li>The School Head accepts those teachers under Encoders.</li>
      <li>The Encoder (or Head) answers Yes / No for all 12 indicators and uploads Minimum MOVs plus the Validity Form.</li>
      <li>The School Head certifies QA and submits to Division. Only the Head can submit or withdraw.</li>
      <li>Division reviews each MOV, Accepts or Returns flagged files, then completes the review.</li>
      <li>If a file is returned: replace only that file, recertify QA, resubmit. Do not rebuild the packet.</li>
    </ol>
  </section>

  <section id="who">
    <h1>Who does what</h1>
    <table>
      <thead>
        <tr>
          <th>Action</th>
          <th>Super Admin</th>
          <th>Division Admin</th>
          <th>School Head</th>
          <th>Encoder</th>
        </tr>
      </thead>
      <tbody>
        ${matrix.map((row) => `<tr>${row.map((cell) => `<td>${esc(cell)}</td>`).join('')}</tr>`).join('')}
      </tbody>
    </table>
  </section>

  ${guides.map(roleHtml).join('')}

  <section class="role" id="return">
    <h1>Returned or invalid MOV</h1>
    <p>Division returns only the flagged file. The school does not rebuild the packet.</p>
    <ol>
      <li>The Encoder replaces that file on MOV files.</li>
      <li>The School Head certifies School Head QA again.</li>
      <li>The School Head clicks Resubmit to Division.</li>
    </ol>
    ${figure('division-review', 'Division Review — Accept or Return each file')}
    ${figure('head-submit', 'School Head Submit — recertify QA, then resubmit')}
  </section>

  <section class="role" id="register">
    <h1>Registration rules and positions</h1>
    <p>School Heads register first. School name and School ID must be unique among Heads. Encoders must type the Head’s exact school name and School ID. Position is a select list that depends on “I am registering as.”</p>
    ${figure('register-head', 'Register as School Head')}
    ${figure('register-encoder', 'Register as Encoder')}
    <div class="split">
      <div>
        <h2>School Head positions</h2>
        <p>School Head, Principal I–IV, Head Teacher I–VI, Teacher-in-Charge, Officer-in-Charge.</p>
      </div>
      <div>
        <h2>Encoder positions</h2>
        <p>Teacher I–III, Master Teacher I–III, SGC Coordinator, SGC Secretary, SGC Teacher-Member, SPED Teacher, Guidance Counselor, Administrative Officer.</p>
      </div>
    </div>
    <h2>Division Admin positions</h2>
    <p>Created by Super Admin, not by Register: Schools Division Superintendent, Assistant Schools Division Superintendent, SGOD Chief, CID Chief, Education Program Supervisor, SGC Focal Person, Public Schools District Supervisor, Division Validator.</p>
    <p class="muted">In-app manuals and walkthroughs: sign in, then open Docs or Help. Portal: https://sgc-smarttrack.com</p>
  </section>
</div>
</body>
</html>
`;

fs.mkdirSync(ROOT, { recursive: true });
fs.writeFileSync(HTML_PATH, html);
console.log('wrote', HTML_PATH);

const printPdf = (outFile) => new Promise((resolve, reject) => {
    fs.mkdirSync(path.dirname(outFile), { recursive: true });
    const child = spawn(CHROME, [
        '--headless=new',
        '--disable-gpu',
        '--no-pdf-header-footer',
        '--no-first-run',
        '--no-default-browser-check',
        `--virtual-time-budget=20000`,
        `--print-to-pdf=${outFile}`,
        GUIDE_URL,
    ], { stdio: 'inherit' });

    child.on('exit', (code) => {
        if (code === 0 && fs.existsSync(outFile)) {
            resolve();
            return;
        }
        reject(new Error(`Chrome print failed (${code}) for ${outFile}`));
    });
});

await printPdf(PDF_PUBLIC);
fs.copyFileSync(PDF_PUBLIC, PDF_MEMO);
console.log('wrote', PDF_PUBLIC);
console.log('wrote', PDF_MEMO);
console.log('size', fs.statSync(PDF_PUBLIC).size);

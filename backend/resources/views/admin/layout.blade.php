<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="theme-color" content="#0b0b15">
  <title>@yield('title', 'Overview') · Hamid Portfolio Studio</title>
  <style>
    :root{color-scheme:dark;--bg:#0b0b14;--panel:#11111e;--panel-2:#151523;--line:rgba(195,183,240,.12);--text:#f2effa;--muted:#9793a8;--dim:#696779;--violet:#b69bff;--cyan:#92d8ee;--green:#99d9bd;--red:#f0a2a7;--mono:"SFMono-Regular",Consolas,monospace;--sans:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;font-family:var(--sans);font-synthesis:none;text-rendering:optimizeLegibility;-webkit-font-smoothing:antialiased}
    *{box-sizing:border-box}body{margin:0;min-width:320px;background:radial-gradient(ellipse at 82% -15%,rgba(119,87,189,.12),transparent 36rem),var(--bg);color:var(--text);font-size:13px}a{color:inherit;text-decoration:none}button,input,textarea,select{font:inherit}button{color:inherit}button,a,input,textarea,select{outline-offset:3px} :focus-visible{outline:2px solid #b69bff} .shell{min-height:100vh}.sidebar{position:fixed;z-index:5;inset:0 auto 0 0;display:flex;width:246px;flex-direction:column;padding:23px 15px;border-right:1px solid var(--line);background:rgba(11,11,20,.96);backdrop-filter:blur(20px)}.brand{display:flex;align-items:center;gap:11px;padding:2px 8px 26px}.brand-mark{display:grid;width:34px;height:34px;place-items:center;border:1px solid rgba(192,174,255,.4);border-radius:11px;color:#f4efff;background:linear-gradient(140deg,rgba(152,124,255,.25),rgba(92,83,150,.08));font-weight:800}.brand-mark span{color:var(--violet)}.brand-copy strong{display:block;font-size:12px;letter-spacing:-.02em}.brand-copy small{display:block;margin-top:3px;color:var(--dim);font-family:var(--mono);font-size:8px;letter-spacing:.08em}.nav-label{margin:18px 10px 8px;color:#696779;font-family:var(--mono);font-size:8px;letter-spacing:.12em}.nav-link{display:flex;min-height:39px;align-items:center;gap:10px;padding:0 10px;border:1px solid transparent;border-radius:7px;color:#a6a2b3;font-size:11px;transition:background .16s ease,color .16s ease,border-color .16s ease}.nav-link:hover,.nav-link.active{border-color:rgba(193,174,255,.12);color:#e8e2f5;background:rgba(184,158,255,.07)}.nav-icon{display:grid;width:17px;place-items:center;color:#ab97dc;font-size:13px}.sidebar-bottom{margin-top:auto}.sidebar-note{padding:12px;border:1px solid rgba(185,168,240,.1);border-radius:8px;color:#878496;background:rgba(255,255,255,.02);font-size:9px;line-height:1.7}.sidebar-note strong{color:#d5c9f3;font-weight:600}.main{min-height:100vh;margin-left:246px}.topbar{position:sticky;z-index:4;top:0;display:flex;height:66px;align-items:center;justify-content:space-between;padding:0 35px;border-bottom:1px solid var(--line);background:rgba(11,11,20,.84);backdrop-filter:blur(18px)}.breadcrumbs{color:#777487;font-size:10px}.breadcrumbs strong{color:#d8d4e4;font-weight:550}.top-actions{display:flex;align-items:center;gap:10px}.button{display:inline-flex;min-height:34px;align-items:center;justify-content:center;gap:8px;padding:0 12px;border:1px solid rgba(196,180,244,.17);border-radius:6px;color:#d9d4e6;background:rgba(255,255,255,.025);cursor:pointer;font-size:10px;transition:.18s ease}.button:hover{border-color:rgba(190,164,255,.4);background:rgba(180,153,255,.09)}.button-primary{border-color:rgba(194,173,255,.32);color:#110f1b;background:linear-gradient(110deg,#c4b1ff,#b9dfeb);font-weight:650}.button-danger{border-color:rgba(240,162,167,.2);color:#efb3b8}.menu-toggle{display:none;width:36px;height:34px;border:1px solid var(--line);border-radius:6px;background:transparent}.content{max-width:1440px;margin:0 auto;padding:32px 35px 52px}.page-head{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;margin-bottom:25px}.page-head h1{margin:0;font-size:25px;font-weight:600;letter-spacing:-.055em}.page-head p{margin:8px 0 0;color:var(--muted);font-size:10px;line-height:1.75}.eyebrow{margin:0 0 9px;color:#a990df;font-family:var(--mono);font-size:8px;letter-spacing:.13em}.flash{margin-bottom:20px;padding:12px 14px;border:1px solid rgba(153,217,189,.2);border-radius:7px;color:#b9e8d1;background:rgba(89,169,129,.08);font-size:10px}.error-box{margin-bottom:18px;padding:12px 14px;border:1px solid rgba(240,162,167,.2);border-radius:7px;color:#f0b5b8;background:rgba(178,86,101,.07);font-size:10px}.grid{display:grid;gap:14px}.stats{grid-template-columns:repeat(4,minmax(0,1fr));margin-bottom:22px}.stat-card,.panel,.editor-card{border:1px solid var(--line);border-radius:9px;background:linear-gradient(145deg,rgba(22,21,36,.86),rgba(15,15,26,.84))}.stat-card{padding:17px}.stat-top{display:flex;align-items:center;justify-content:space-between;color:#9290a2;font-size:9px}.stat-mark{color:#a990df;font-size:15px}.stat-number{margin:15px 0 5px;font-size:28px;font-weight:600;letter-spacing:-.06em}.stat-caption{color:#777487;font-size:9px}.dashboard-columns{grid-template-columns:1.2fr .8fr}.panel{padding:18px}.panel-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:15px}.panel-head h2{margin:0;font-size:12px;font-weight:600}.panel-head a{color:#b69bff;font-size:9px}.activity-list{display:grid;gap:0}.activity-row{display:flex;align-items:flex-start;gap:11px;padding:12px 0;border-top:1px solid rgba(255,255,255,.055)}.activity-dot{width:7px;height:7px;flex:0 0 auto;margin-top:4px;border-radius:50%;background:#a98cea;box-shadow:0 0 10px rgba(175,147,242,.5)}.activity-copy{min-width:0;flex:1}.activity-copy strong{display:block;color:#dbd8e6;font-size:10px;font-weight:550}.activity-copy p{margin:4px 0 0;color:#848194;font-size:9px;line-height:1.6}.activity-time{flex:0 0 auto;color:#706e80;font-family:var(--mono);font-size:8px}.status-pill{display:inline-flex;align-items:center;gap:6px;padding:5px 8px;border:1px solid rgba(153,217,189,.16);border-radius:999px;color:#addbc5;background:rgba(91,170,128,.07);font-family:var(--mono);font-size:8px}.status-pill::before{width:5px;height:5px;border-radius:50%;background:#8fd3af;content:""}.status-pill.off{border-color:rgba(240,162,167,.16);color:#e5a9ae;background:rgba(180,79,91,.07)}.status-pill.off::before{background:#e5a9ae}.quick-actions{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}.quick-action{display:flex;min-height:65px;flex-direction:column;justify-content:center;gap:7px;padding:12px;border:1px solid rgba(195,183,240,.11);border-radius:7px;color:#d7d2e3;background:rgba(255,255,255,.018);font-size:10px}.quick-action:hover{border-color:rgba(194,173,255,.31);background:rgba(180,153,255,.05)}.quick-action span{color:#858295;font-size:8px}.editor-layout{display:grid;grid-template-columns:minmax(0,1fr) minmax(340px,.9fr);align-items:start;gap:17px}.editor-form{display:grid;gap:13px}.editor-card{padding:17px}.editor-card h2{margin:0 0 4px;font-size:12px;font-weight:600}.card-help{margin:0 0 16px;color:#858294;font-size:9px;line-height:1.7}.field-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.field{display:grid;gap:6px;min-width:0}.field.full{grid-column:1/-1}.field label,.field-label{color:#aaa7b7;font-size:9px}.field input,.field textarea,.field select{width:100%;min-height:34px;padding:8px 10px;border:1px solid rgba(190,179,223,.15);border-radius:5px;color:#e5e1ed;background:#0d0e18;font-size:10px}.field textarea{min-height:78px;resize:vertical;line-height:1.6}.field input[type=color]{height:38px;padding:3px}.field input:focus,.field textarea:focus,.field select:focus{border-color:rgba(184,159,255,.55);outline:0;box-shadow:0 0 0 3px rgba(175,145,250,.08)}.field-hint{color:#747184;font-size:8px;line-height:1.5}.checkbox-row{display:flex;align-items:center;gap:8px;color:#b7b2c3;font-size:9px}.checkbox-row input{accent-color:#a98cea}.section-editor{margin-top:12px;padding:13px;border:1px solid rgba(195,183,240,.1);border-radius:7px;background:rgba(255,255,255,.018)}.section-editor:first-of-type{margin-top:0}.section-editor-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}.section-editor-head strong{font-size:10px;font-weight:600}.section-editor-head small{color:#777487;font-family:var(--mono);font-size:8px}.section-editor .field-grid{gap:9px}.editor-actions{position:sticky;bottom:12px;z-index:2;display:flex;justify-content:flex-end;gap:9px;padding:11px;border:1px solid var(--line);border-radius:8px;background:rgba(15,15,26,.94);backdrop-filter:blur(14px)}.preview-card{position:sticky;top:82px;overflow:hidden;padding:0}.preview-head{display:flex;align-items:center;justify-content:space-between;padding:12px 14px;border-bottom:1px solid var(--line)}.preview-head strong{font-size:10px;font-weight:600}.preview-head span{color:#888497;font-family:var(--mono);font-size:8px}.preview-frame{display:block;width:100%;height:min(75vh,820px);min-height:550px;border:0;background:#070811}.project-list{display:grid;gap:14px}.project-editor{display:grid;grid-template-columns:minmax(0,1fr) 120px;gap:14px}.project-editor-main{min-width:0}.project-editor-actions{display:flex;flex-direction:column;align-items:stretch;gap:8px}.project-editor-actions .button{width:100%}.project-title-row{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:13px}.project-title-row h2{margin:0;font-size:13px;font-weight:600}.project-form{display:grid;gap:11px}.table-wrap{overflow:auto}.data-table{width:100%;border-collapse:collapse}.data-table th,.data-table td{padding:11px 9px;border-bottom:1px solid rgba(255,255,255,.06);text-align:left;vertical-align:top}.data-table th{color:#777487;font-family:var(--mono);font-size:8px;font-weight:400;letter-spacing:.07em}.data-table td{color:#c3bfce;font-size:9px;line-height:1.7}.data-table td small{display:block;color:#7e7b8d;font-size:8px}.form-inline{display:flex;align-items:flex-start;gap:8px}.form-inline select,.form-inline textarea{width:100%;min-height:31px;padding:6px 8px;border:1px solid rgba(190,179,223,.15);border-radius:5px;color:#ddd8e9;background:#0d0e18;font-size:9px}.media-grid{grid-template-columns:repeat(4,minmax(0,1fr))}.media-card{overflow:hidden;border:1px solid var(--line);border-radius:8px;background:var(--panel)}.media-card img{display:block;width:100%;height:130px;object-fit:cover;background:#171625}.media-meta{padding:10px}.media-meta strong{display:block;overflow:hidden;color:#dedbe7;font-size:9px;text-overflow:ellipsis;white-space:nowrap}.media-meta small{display:block;margin-top:5px;color:#828093;font-size:8px}.upload-form{display:grid;grid-template-columns:1.5fr 1fr 1fr auto;align-items:end;gap:9px}.login-page{display:grid;min-height:100vh;place-items:center;padding:24px}.login-card{width:min(410px,100%);padding:28px;border:1px solid var(--line);border-radius:12px;background:linear-gradient(150deg,#171627,#10101b);box-shadow:0 30px 100px rgba(0,0,0,.4)}.login-card h1{margin:0;font-size:24px;letter-spacing:-.06em}.login-card>p{margin:9px 0 24px;color:var(--muted);font-size:10px;line-height:1.8}.login-card form{display:grid;gap:13px}.login-card .button{min-height:39px;margin-top:4px}.pagination{display:flex;justify-content:center;gap:7px;margin-top:18px}.pagination a,.pagination span{padding:7px 10px;border:1px solid var(--line);border-radius:5px;color:#c5c0d2;font-size:9px}.pagination .active{color:#15111e;background:#c4b1ff}
    @media(max-width:1100px){.editor-layout{grid-template-columns:minmax(0,1fr) minmax(300px,.8fr)}.stats{grid-template-columns:repeat(2,minmax(0,1fr))}.media-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
    @media(max-width:800px){.sidebar{width:220px;transform:translateX(-101%);transition:transform .2s ease}.sidebar.open{transform:translateX(0);box-shadow:20px 0 60px rgba(0,0,0,.38)}.main{margin-left:0}.topbar{padding:0 20px}.menu-toggle{display:grid;place-items:center}.content{padding:25px 20px 40px}.editor-layout{grid-template-columns:1fr}.preview-card{position:relative;top:auto}.preview-frame{height:620px;min-height:420px}.upload-form{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:520px){.content{padding-inline:15px}.topbar{height:58px;padding-inline:15px}.top-actions .button-quiet{display:none}.page-head{align-items:flex-start;flex-direction:column}.stats{gap:9px}.stat-card{padding:13px}.stat-number{font-size:24px}.dashboard-columns{grid-template-columns:1fr}.field-grid{grid-template-columns:1fr}.field.full{grid-column:auto}.project-editor{grid-template-columns:1fr}.project-editor-actions{flex-direction:row;flex-wrap:wrap}.project-editor-actions .button{width:auto}.media-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.upload-form{grid-template-columns:1fr}.preview-frame{height:540px}.activity-time{display:none}}
    @media(prefers-reduced-motion:reduce){*,*::before,*::after{scroll-behavior:auto!important;animation-duration:.01ms!important;transition-duration:.01ms!important}}
  </style>
  <style>
    .field input[type="checkbox"],
    .field input[type="radio"],
    .checkbox-row input[type="checkbox"],
    .checkbox-row input[type="radio"] {
      width: 14px;
      height: 14px;
      min-height: 14px;
      flex: 0 0 14px;
      margin: 0;
      padding: 0;
      border: 0;
      border-radius: 2px;
      background: transparent;
      accent-color: #a98cea;
    }
    .inbox-card,
    .inbox-message-head > div {
      min-width: 0;
    }
    .inbox-message-head > div,
    .inbox-message {
      overflow-wrap: anywhere;
    }
    .sidebar {
      overflow-y: auto;
    }
    .form-inline {
      flex-wrap: wrap;
    }
    .panel,
    .editor-card {
      min-width: 0;
    }
    .project-editor-main,
    .project-title-row h2 {
      min-width: 0;
    }
    .project-title-row h2 {
      overflow-wrap: anywhere;
    }
    @media (max-width: 1100px) {
      .editor-layout,
      .project-editor {
        grid-template-columns: minmax(0, 1fr);
      }
      .preview-card {
        position: relative;
        top: auto;
      }
      .project-editor-actions {
        flex-direction: row;
        flex-wrap: wrap;
      }
      .project-editor-actions .button {
        width: auto;
      }
    }
  </style>
</head>
<body>
@if(auth()->check())
<div class="shell">
  <aside class="sidebar" id="admin-sidebar">
    <a class="brand" href="{{ route('admin.dashboard') }}">
      <span class="brand-mark">H<span>.</span></span>
      <span class="brand-copy"><strong>Hamid Portfolio</strong><small>CONTROL ROOM / 01</small></span>
    </a>
    <p class="nav-label">WORKSPACE</p>
    <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}"><span class="nav-icon">◈</span>Overview</a>
    <a class="nav-link {{ request()->routeIs('admin.site-editor.*') ? 'active' : '' }}" href="{{ route('admin.site-editor.edit') }}"><span class="nav-icon">◉</span>Live site editor</a>
    <a class="nav-link {{ request()->routeIs('admin.projects.*') ? 'active' : '' }}" href="{{ route('admin.projects.index') }}"><span class="nav-icon">▧</span>Projects</a>
    <a class="nav-link {{ request()->routeIs('admin.sections.*') ? 'active' : '' }}" href="{{ route('admin.sections.index') }}"><span class="nav-icon">+</span>Sections</a>
    <a class="nav-link {{ request()->routeIs('admin.revisions.*') ? 'active' : '' }}" href="{{ route('admin.revisions.index') }}"><span class="nav-icon">↶</span>Content history</a>
    <p class="nav-label">LIBRARY</p>
    <a class="nav-link {{ request()->routeIs('admin.media.*') ? 'active' : '' }}" href="{{ route('admin.media.index') }}"><span class="nav-icon">▤</span>Media library</a>
    <a class="nav-link {{ request()->routeIs('admin.fonts.*') ? 'active' : '' }}" href="{{ route('admin.fonts.index') }}"><span class="nav-icon">Aa</span>Fonts</a>
    <a class="nav-link {{ request()->routeIs('admin.inbox.*') ? 'active' : '' }}" href="{{ route('admin.inbox.index') }}"><span class="nav-icon">✉</span>Contact inbox</a>
    <div class="sidebar-bottom">
      <div class="sidebar-note"><strong>Private studio</strong><br>Changes publish to the live portfolio only when you choose to save them.</div>
      <p class="nav-label">ACCOUNT</p>
      <form method="post" action="{{ route('admin.logout') }}">@csrf<button class="nav-link" type="submit" style="width:100%;text-align:left;background:none;cursor:pointer"><span class="nav-icon">↗</span>Sign out</button></form>
    </div>
  </aside>
  <div class="main">
    <header class="topbar">
      <div style="display:flex;align-items:center;gap:12px"><button class="menu-toggle" id="menu-toggle" type="button" aria-label="Open dashboard navigation" aria-expanded="false">☰</button><div class="breadcrumbs">Studio <span aria-hidden="true">/</span> <strong>@yield('breadcrumb', 'Overview')</strong></div></div>
      <div class="top-actions">
        <a class="button button-quiet" href="{{ route('portfolio.home') }}" target="_blank" rel="noreferrer">View live site <span aria-hidden="true">↗</span></a>
        <a class="button" href="{{ route('admin.site-editor.edit') }}">Edit portfolio</a>
      </div>
    </header>
    <main class="content">
      @if(session('status'))<div class="flash" role="status">{{ session('status') }}</div>@endif
      @if($errors->any())<div class="error-box" role="alert">{{ $errors->first() }}</div>@endif
      @yield('content')
    </main>
  </div>
</div>
@else
  @yield('content')
@endif
<script>
  const menuToggle = document.querySelector('#menu-toggle');
  const sidebar = document.querySelector('#admin-sidebar');
  menuToggle?.addEventListener('click', () => {
    const expanded = menuToggle.getAttribute('aria-expanded') === 'true';
    menuToggle.setAttribute('aria-expanded', String(!expanded));
    sidebar.classList.toggle('open', !expanded);
  });
  document.querySelectorAll('form[data-confirm]').forEach((form) => form.addEventListener('submit', (event) => {
    if (!window.confirm(form.dataset.confirm)) event.preventDefault();
  }));
  document.querySelectorAll('form[data-unsaved]').forEach((form) => {
    let dirty = false;
    form.addEventListener('input', () => { dirty = true; });
    form.addEventListener('submit', () => { dirty = false; });
    window.addEventListener('beforeunload', (event) => {
      if (dirty) { event.preventDefault(); event.returnValue = ''; }
    });
  });
</script>
@stack('scripts')
</body>
</html>

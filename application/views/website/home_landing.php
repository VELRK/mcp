<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Talk AI Pilot — AI Conversations That Convert</title>
  <meta name="description" content="Automate WhatsApp, Instagram, Facebook, YouTube and mobile app conversations with one AI sales and support platform.">
  <link rel="icon" href="<?= base_url('assets/images/logo/favicon.svg') ?>">
  <style>
    :root{--ink:#10172b;--muted:#657087;--purple:#6750e8;--purple2:#8c63ff;--navy:#070d24;--line:#e8e9f2;--soft:#f6f5fb;--green:#20c876;--white:#fff}
    *{box-sizing:border-box}
    html{scroll-behavior:smooth}
    body{margin:0;color:var(--ink);background:#fff;font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;line-height:1.6}
    a{color:inherit;text-decoration:none}
    img{display:block;max-width:100%}
    .wrap{width:min(1180px,calc(100% - 40px));margin:auto}
    .nav{position:sticky;top:0;z-index:20;background:rgba(255,255,255,.9);border-bottom:1px solid rgba(232,233,242,.9);backdrop-filter:blur(14px)}
    .nav-inner{height:76px;display:flex;align-items:center;justify-content:space-between;gap:24px}
    .brand{font-size:21px;font-weight:850;letter-spacing:-.045em;white-space:nowrap}.brand span{color:var(--purple)}
    .nav-links{display:flex;align-items:center;gap:30px;font-size:14px;font-weight:650;color:#424a60}
    .nav-links a:hover{color:var(--purple)}
    .nav-actions{display:flex;align-items:center;gap:12px}
    .btn{display:inline-flex;align-items:center;justify-content:center;gap:9px;min-height:46px;padding:0 21px;border:1px solid transparent;border-radius:12px;font-weight:750;font-size:14px;transition:.2s ease}
    .btn:hover{transform:translateY(-2px)}
    .btn-primary{color:#fff;background:linear-gradient(135deg,var(--purple),var(--purple2));box-shadow:0 12px 28px rgba(103,80,232,.25)}
    .btn-light{background:#fff;border-color:var(--line)}
    .menu-btn{display:none;background:none;border:0;font-size:25px;color:var(--ink)}
    .hero{position:relative;overflow:hidden;padding:88px 0 86px;background:radial-gradient(circle at 80% 15%,#ece7ff 0,transparent 34%),linear-gradient(180deg,#fbfaff,#fff)}
    .hero:before{content:"";position:absolute;width:520px;height:520px;left:-300px;top:-240px;border-radius:50%;background:#e9fff4;filter:blur(12px)}
    .hero-grid{position:relative;display:grid;grid-template-columns:1.03fr .97fr;gap:66px;align-items:center}
    .eyebrow{display:inline-flex;align-items:center;gap:8px;padding:7px 12px;border:1px solid #ddd7ff;border-radius:99px;background:#f4f1ff;color:#5741d5;font-size:12px;font-weight:800;letter-spacing:.08em;text-transform:uppercase}
    .eyebrow i{width:8px;height:8px;border-radius:50%;background:var(--green);box-shadow:0 0 0 4px rgba(32,200,118,.13)}
    h1{margin:20px 0 22px;font-size:clamp(42px,5.2vw,68px);line-height:1.04;letter-spacing:-.06em}
    h1 span{color:var(--purple)}
    .hero-copy>p{max-width:620px;margin:0 0 30px;color:var(--muted);font-size:18px;line-height:1.75}
    .hero-actions{display:flex;flex-wrap:wrap;gap:12px}
    .hero-note{display:flex;flex-wrap:wrap;gap:19px;margin-top:25px;color:#626b80;font-size:13px;font-weight:650}
    .hero-note span:before{content:"✓";color:var(--green);font-weight:900;margin-right:7px}
    .hero-media{position:relative}
    .hero-frame{padding:10px;border-radius:30px;background:linear-gradient(145deg,#8d72ff,#27176b);box-shadow:0 34px 80px rgba(38,24,101,.3);transform:rotate(1deg)}
    .hero-frame img{width:100%;aspect-ratio:4/3;object-fit:cover;border-radius:22px}
    .float-card{position:absolute;display:flex;align-items:center;gap:10px;padding:12px 15px;background:#fff;border:1px solid #ece9f8;border-radius:14px;box-shadow:0 15px 38px rgba(27,23,55,.16);font-size:12px;font-weight:800}
    .float-card b{display:block}.float-card small{display:block;color:var(--muted);font-weight:600}
    .float-card.one{left:-38px;top:12%}.float-card.two{right:-30px;bottom:12%}
    .float-icon{width:34px;height:34px;display:grid;place-items:center;border-radius:10px;color:#fff;background:var(--green)}
    .float-card.two .float-icon{background:var(--purple)}
    .trust{border-bottom:1px solid var(--line);padding:27px 0;background:#fff}
    .trust-inner{display:flex;align-items:center;justify-content:center;gap:38px;flex-wrap:wrap}
    .trust-label{font-size:13px;color:var(--muted);font-weight:700}
    .channel{display:flex;align-items:center;gap:8px;font-weight:800;font-size:14px}.channel svg{width:20px;height:20px}
    section{padding:96px 0}
    .section-head{max-width:720px;margin:0 auto 48px;text-align:center}
    .kicker{display:block;margin-bottom:10px;color:var(--purple);font-size:12px;font-weight:850;letter-spacing:.12em;text-transform:uppercase}
    h2{margin:0 0 16px;font-size:clamp(31px,4vw,48px);line-height:1.12;letter-spacing:-.045em}
    .section-head p{margin:0;color:var(--muted);font-size:17px}
    .channels{background:var(--soft)}
    .cards{display:grid;grid-template-columns:repeat(5,1fr);gap:14px}
    .card{padding:25px 21px;border:1px solid #e7e5f0;border-radius:19px;background:#fff;transition:.25s ease}
    .card:hover{transform:translateY(-6px);border-color:#cbc1ff;box-shadow:0 18px 44px rgba(43,34,87,.09)}
    .card-icon{width:48px;height:48px;display:grid;place-items:center;margin-bottom:22px;border-radius:14px;color:#fff}
    .card-icon svg{width:24px;height:24px}
    .wa{background:#20c876}.ig{background:linear-gradient(135deg,#f99b4a,#d62976,#7442be)}.yt{background:#fb2447}.fb{background:#2778ec}.app{background:#6852e8}
    .card h3{margin:0 0 9px;font-size:17px}.card p{margin:0;color:var(--muted);font-size:14px}
    .workflow-grid{display:grid;grid-template-columns:.88fr 1.12fr;gap:68px;align-items:center}
    .workflow-copy>p{color:var(--muted);font-size:17px}
    .check-list{list-style:none;margin:28px 0 0;padding:0;display:grid;gap:17px}
    .check-list li{display:grid;grid-template-columns:38px 1fr;gap:13px;align-items:start}
    .check-list i{width:38px;height:38px;display:grid;place-items:center;border-radius:11px;background:#eeebff;color:var(--purple);font-style:normal;font-weight:900}
    .check-list b{display:block;margin-bottom:2px}.check-list span{color:var(--muted);font-size:14px}
    .flow{position:relative;padding:28px;border-radius:25px;background:var(--navy);box-shadow:0 24px 65px rgba(7,13,36,.2)}
    .flow-top{display:flex;align-items:center;justify-content:space-between;color:#fff;margin-bottom:25px}.flow-top small{color:#9ba4c2}
    .live{padding:6px 10px;border-radius:99px;background:rgba(32,200,118,.14);color:#5ae6a0;font-size:11px;font-weight:800}
    .flow-step{display:grid;grid-template-columns:42px 1fr auto;gap:13px;align-items:center;padding:14px;margin-top:10px;border:1px solid rgba(255,255,255,.09);border-radius:15px;background:rgba(255,255,255,.05);color:#fff}
    .flow-step em{width:42px;height:42px;display:grid;place-items:center;border-radius:12px;background:#6e55ed;font-style:normal;font-weight:850}
    .flow-step b{display:block;font-size:14px}.flow-step span{color:#9ba4c2;font-size:12px}.flow-step strong{color:#63e4a3;font-size:11px}
    .features{background:#0b112a;color:#fff}
    .features .section-head p{color:#aab1c8}
    .feature-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
    .feature{padding:28px;border:1px solid rgba(255,255,255,.1);border-radius:19px;background:rgba(255,255,255,.045)}
    .feature-num{color:#9f8cff;font-weight:850;font-size:13px}.feature h3{font-size:19px;margin:18px 0 8px}.feature p{margin:0;color:#aab1c8;font-size:14px}
    .use-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}
    .use{min-height:245px;position:relative;overflow:hidden;padding:27px;border-radius:20px;background:linear-gradient(155deg,#f1edff,#fff);border:1px solid var(--line)}
    .use:after{content:"";position:absolute;width:120px;height:120px;right:-35px;bottom:-35px;border-radius:50%;background:rgba(103,80,232,.09)}
    .use span{color:var(--purple);font-size:12px;font-weight:850;text-transform:uppercase}.use h3{font-size:21px;margin:47px 0 10px}.use p{color:var(--muted);font-size:14px;margin:0}
    .core{background:var(--soft)}.core-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}.core-item{padding:22px;border:1px solid var(--line);border-radius:16px;background:#fff}.core-item b{display:block;margin-bottom:6px}.core-item p{margin:0;color:var(--muted);font-size:13px}.core-link{display:flex;justify-content:center;margin-top:30px}
    .pricing-preview{display:grid;grid-template-columns:1fr 1fr;gap:18px}.price-box{padding:32px;border:1px solid var(--line);border-radius:21px;background:#fff}.price-box.highlight{color:#fff;border:0;background:linear-gradient(145deg,#141d46,#5234cb)}.price-box span{color:var(--purple);font-size:12px;font-weight:850;text-transform:uppercase}.price-box.highlight span{color:#bcaeff}.price-box h3{margin:12px 0 8px;font-size:25px}.price-box p{color:var(--muted)}.price-box.highlight p{color:#d5d7e6}
    .faq{max-width:820px;margin:auto;display:grid;gap:12px}.faq details{padding:19px 21px;border:1px solid var(--line);border-radius:14px;background:#fff}.faq summary{cursor:pointer;font-weight:800}.faq p{margin:12px 0 0;color:var(--muted);font-size:14px}
    .cta{padding-top:30px}
    .cta-box{display:grid;grid-template-columns:1fr auto;gap:30px;align-items:center;padding:50px;border-radius:26px;color:#fff;background:radial-gradient(circle at 85% 25%,rgba(146,114,255,.7),transparent 27%),linear-gradient(130deg,#111a43,#4425b8);box-shadow:0 25px 70px rgba(53,36,144,.25)}
    .cta-box h2{font-size:clamp(29px,4vw,44px);margin-bottom:10px}.cta-box p{margin:0;color:#d8d9e9}
    .cta-box .btn{background:#fff;color:#33207f;white-space:nowrap}
    footer{margin-top:90px;padding:54px 0 28px;background:#070b1c;color:#a5abc0}
    .footer-grid{display:grid;grid-template-columns:1.5fr repeat(3,1fr);gap:45px}.footer-brand p{max-width:340px;font-size:14px}.footer-title{color:#fff;font-weight:800;margin-bottom:13px}.footer-links{display:grid;gap:9px;font-size:14px}.footer-links a:hover{color:#fff}
    .copyright{margin-top:44px;padding-top:22px;border-top:1px solid rgba(255,255,255,.1);display:flex;justify-content:space-between;gap:20px;font-size:12px}
    @media(max-width:1050px){.cards{grid-template-columns:repeat(3,1fr)}.use-grid,.core-grid{grid-template-columns:1fr 1fr}.nav-links{display:none}.nav-links.open{position:absolute;display:grid;left:20px;right:20px;top:68px;padding:20px;gap:14px;border:1px solid var(--line);border-radius:14px;background:#fff;box-shadow:0 18px 45px rgba(20,18,50,.14)}.menu-btn{display:block}.hero-grid{gap:38px}.float-card{display:none}}
    @media(max-width:800px){.wrap{width:min(100% - 30px,680px)}.nav-inner{height:68px}.nav-actions .btn-light{display:none}.hero{padding:62px 0}.hero-grid,.workflow-grid{grid-template-columns:1fr}.hero-copy{text-align:center}.hero-copy>p{margin-left:auto;margin-right:auto}.hero-actions,.hero-note{justify-content:center}.hero-media{max-width:600px;margin:auto}.cards,.feature-grid{grid-template-columns:1fr 1fr}.cta-box{grid-template-columns:1fr;text-align:center}.footer-grid{grid-template-columns:1fr 1fr}}
    @media(max-width:560px){section{padding:70px 0}.nav-actions .btn-primary{display:none}.hero{padding-top:45px}h1{font-size:39px}.hero-copy>p{font-size:16px}.hero-actions{display:grid}.hero-actions .btn{width:100%}.cards,.feature-grid,.use-grid,.core-grid,.pricing-preview,.footer-grid{grid-template-columns:1fr}.channel{font-size:12px}.trust-inner{gap:18px}.workflow-grid{gap:38px}.flow{padding:17px}.flow-step{grid-template-columns:38px 1fr}.flow-step strong{display:none}.cta-box{padding:34px 23px}.copyright{flex-direction:column}}
  </style>
</head>
<body>
  <?php
  $icon = static function (string $name): string {
      $paths = [
          'wa' => '<path d="M12 2a9.6 9.6 0 0 0-8.3 14.4L2.3 21.7l5.2-1.4A9.6 9.6 0 1 0 12 2Zm4.8 13.6c-.2.6-1.2 1.1-1.8 1.2-.5.1-1.1.2-3.2-.7-2.7-1.1-4.4-3.8-4.5-4-.2-.2-1.1-1.4-1.1-2.7s.7-2 1-2.2c.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.5l.8 1.9c.1.2.1.3 0 .5l-.6.9c-.2.2-.3.3-.1.6.1.2.6 1 1.3 1.6.9.8 1.7 1.1 2 1.2.2.1.4.1.5-.1l.8-1c.2-.2.3-.2.6-.1l1.6.8c.3.1.5.2.5.3.1.1.1.6-.1 1.2Z"/>',
          'ig' => '<path d="M7.5 2h9A5.5 5.5 0 0 1 22 7.5v9a5.5 5.5 0 0 1-5.5 5.5h-9A5.5 5.5 0 0 1 2 16.5v-9A5.5 5.5 0 0 1 7.5 2Zm9 2h-9A3.5 3.5 0 0 0 4 7.5v9A3.5 3.5 0 0 0 7.5 20h9a3.5 3.5 0 0 0 3.5-3.5v-9A3.5 3.5 0 0 0 16.5 4ZM12 7a5 5 0 1 1 0 10 5 5 0 0 1 0-10Zm0 2a3 3 0 1 0 0 6 3 3 0 0 0 0-6Zm5.3-3.2a1.2 1.2 0 1 1 0 2.4 1.2 1.2 0 0 1 0-2.4Z"/>',
          'yt' => '<path d="M23 12s0-3.5-.5-5.1c-.3-.9-1-1.6-1.9-1.8C18.9 4.6 12 4.6 12 4.6s-6.9 0-8.6.5c-.9.2-1.6.9-1.9 1.8C1 8.5 1 12 1 12s0 3.5.5 5.1c.3.9 1 1.6 1.9 1.8 1.7.5 8.6.5 8.6.5s6.9 0 8.6-.5c.9-.2 1.6-.9 1.9-1.8.5-1.6.5-5.1.5-5.1Zm-13.2 3.2V8.8L15.4 12l-5.6 3.2Z"/>',
          'fb' => '<path d="M14.5 8.2V6.4c0-.8.5-1 1.1-1h2V2.5h-2.8c-3 0-3.9 2-3.9 3.8v1.9H8.8v2.9h2.1v10.4h3.6V11.1H17l.4-2.9h-2.9Z"/>',
          'app' => '<path d="M8.2 2h7.6A2.7 2.7 0 0 1 18.5 4.7v14.6a2.7 2.7 0 0 1-2.7 2.7H8.2a2.7 2.7 0 0 1-2.7-2.7V4.7A2.7 2.7 0 0 1 8.2 2Zm-.7 3v12h9V5h-9Zm4.5 15a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z"/>',
      ];
      return '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">'.($paths[$name] ?? '').'</svg>';
  };
  ?>
  <header class="nav">
    <div class="wrap nav-inner">
      <a class="brand" href="<?= base_url() ?>">Talk <span>AI</span> Pilot</a>
      <nav class="nav-links" aria-label="Primary">
        <a href="<?= base_url() ?>">Home</a><a href="<?= site_url('about') ?>">About</a><a href="<?= site_url('services') ?>">Services</a><a href="<?= site_url('contact') ?>">Contact</a>
      </nav>
      <div class="nav-actions">
        <a class="btn btn-light" href="<?= site_url('admin/login') ?>">Sign in</a>
        <a class="btn btn-primary" href="<?= site_url('contact') ?>">Start free</a>
        <button class="menu-btn" type="button" aria-label="Open navigation" onclick="document.querySelector('.nav-links').classList.toggle('open')">☰</button>
      </div>
    </div>
  </header>

  <main>
    <section class="hero">
      <div class="wrap hero-grid">
        <div class="hero-copy">
          <span class="eyebrow"><i></i> One AI agent. Every conversation.</span>
          <h1>Your complete <span>AI business automation</span> platform.</h1>
          <p>Connect customer conversations, AI agents, workflow automation, CRM, ecommerce actions and analytics across WhatsApp, Instagram, Facebook, YouTube, web and mobile apps.</p>
          <div class="hero-actions">
            <a class="btn btn-primary" href="<?= site_url('contact') ?>">Build your automation <span>→</span></a>
            <a class="btn btn-light" href="<?= site_url('platform') ?>">Explore the platform</a>
          </div>
          <div class="hero-note"><span>24/7 automated replies</span><span>Human handoff</span><span>One shared inbox</span></div>
        </div>
        <div class="hero-media">
          <div class="hero-frame"><img src="<?= base_url('assets/website/images/talk-ai-pilot-platform-core.jpg') ?>" alt="Talk AI Pilot platform connecting channels, AI automation, CRM, ecommerce and analytics" width="1024" height="768"></div>
          <div class="float-card one"><span class="float-icon">✓</span><span><b>Lead captured</b><small>Added to sales pipeline</small></span></div>
          <div class="float-card two"><span class="float-icon">AI</span><span><b>Reply sent</b><small>In under 3 seconds</small></span></div>
        </div>
      </div>
    </section>

    <div class="trust">
      <div class="wrap trust-inner">
        <span class="trust-label">Connect every customer channel</span>
        <span class="channel"><?= $icon('wa') ?> WhatsApp</span><span class="channel"><?= $icon('ig') ?> Instagram</span><span class="channel"><?= $icon('fb') ?> Facebook</span><span class="channel"><?= $icon('yt') ?> YouTube</span><span class="channel"><?= $icon('app') ?> Mobile App</span>
      </div>
    </div>

    <section class="channels" id="channels">
      <div class="wrap">
        <div class="section-head"><span class="kicker">Omnichannel automation</span><h2>Meet customers wherever they talk</h2><p>Every comment, DM and chat arrives in one workspace where AI can respond instantly and your team can take over anytime.</p></div>
        <div class="cards">
          <article class="card"><span class="card-icon wa"><?= $icon('wa') ?></span><h3>WhatsApp</h3><p>Reply, qualify leads, share products, send payment links and follow up automatically.</p></article>
          <article class="card"><span class="card-icon ig"><?= $icon('ig') ?></span><h3>Instagram</h3><p>Turn comments and DMs into guided sales and support conversations.</p></article>
          <article class="card"><span class="card-icon fb"><?= $icon('fb') ?></span><h3>Facebook</h3><p>Manage Messenger enquiries and page conversations from the same inbox.</p></article>
          <article class="card"><span class="card-icon yt"><?= $icon('yt') ?></span><h3>YouTube</h3><p>Detect buying intent in comments and move interested viewers into your funnel.</p></article>
          <article class="card"><span class="card-icon app"><?= $icon('app') ?></span><h3>Mobile App</h3><p>Bring in-app support, notifications and customer activity into one workflow.</p></article>
        </div>
      </div>
    </section>

    <section id="automation">
      <div class="wrap workflow-grid">
        <div class="workflow-copy">
          <span class="kicker">Your always-on AI pilot</span>
          <h2>From first message to the next best action</h2>
          <p>Create practical automations that understand the customer, use your business information, and keep your team focused on conversations that need a human.</p>
          <ul class="check-list">
            <li><i>01</i><div><b>Capture and understand</b><span>Identify the customer, channel, intent and conversation context.</span></div></li>
            <li><i>02</i><div><b>Reply and qualify</b><span>Answer FAQs, recommend products and ask the right follow-up questions.</span></div></li>
            <li><i>03</i><div><b>Act and follow up</b><span>Create leads, notify staff, send reminders and continue the journey automatically.</span></div></li>
          </ul>
        </div>
        <div class="flow">
          <div class="flow-top"><div><b>Lead conversion workflow</b><br><small>WhatsApp · Running now</small></div><span class="live">● LIVE</span></div>
          <div class="flow-step"><em>1</em><div><b>New customer message</b><span>“Do you have this product in stock?”</span></div><strong>TRIGGERED</strong></div>
          <div class="flow-step"><em>2</em><div><b>AI identifies buying intent</b><span>Checks product and inventory data</span></div><strong>COMPLETE</strong></div>
          <div class="flow-step"><em>3</em><div><b>Personalised reply</b><span>Sends price, options and next step</span></div><strong>SENT</strong></div>
          <div class="flow-step"><em>4</em><div><b>Lead assigned to sales</b><span>Full context available for human handoff</span></div><strong>READY</strong></div>
        </div>
      </div>
    </section>

    <section class="features" id="features">
      <div class="wrap">
        <div class="section-head"><span class="kicker">Everything in one platform</span><h2>Built for real customer operations</h2><p>More than a chatbot—Talk AI Pilot connects messages, customer data and actions.</p></div>
        <div class="feature-grid">
          <article class="feature"><span class="feature-num">01</span><h3>Unified team inbox</h3><p>See WhatsApp, Instagram, Facebook, website and app conversations together.</p></article>
          <article class="feature"><span class="feature-num">02</span><h3>AI knowledge</h3><p>Train responses using your products, services, FAQs, policies and approved content.</p></article>
          <article class="feature"><span class="feature-num">03</span><h3>Visual automation</h3><p>Build trigger, condition and action workflows without repetitive manual work.</p></article>
          <article class="feature"><span class="feature-num">04</span><h3>Campaign messaging</h3><p>Send compliant broadcasts, reminders and follow-ups to the right audience.</p></article>
          <article class="feature"><span class="feature-num">05</span><h3>Human handoff</h3><p>Route complex or valuable conversations to the correct person with full context.</p></article>
          <article class="feature"><span class="feature-num">06</span><h3>Reports that matter</h3><p>Measure response time, conversations, leads, automation outcomes and sales activity.</p></article>
        </div>
      </div>
    </section>

    <section class="core">
      <div class="wrap">
        <div class="section-head"><span class="kicker">The application core</span><h2>Everything your customer operation needs</h2><p>Talk AI Pilot is a connected operating system—not a single chatbot or messaging screen.</p></div>
        <div class="core-grid">
          <article class="core-item"><b>Conversations</b><p>One inbox, assignment, tags, notes and customer history.</p></article>
          <article class="core-item"><b>AI Agents</b><p>Product search, recommendations, support answers and qualification.</p></article>
          <article class="core-item"><b>Automation</b><p>Triggers, conditions, actions, scheduled tasks and handoff rules.</p></article>
          <article class="core-item"><b>Customer CRM</b><p>Leads, segments, customer 360 and engagement timelines.</p></article>
          <article class="core-item"><b>Commerce</b><p>Products, inventory, cart, payment, order and invoice workflows.</p></article>
          <article class="core-item"><b>Marketing</b><p>Broadcasts, campaigns, abandoned cart, win-back and reorder.</p></article>
          <article class="core-item"><b>Operations</b><p>Shipping, tracking, returns, alerts and staff tasks.</p></article>
          <article class="core-item"><b>Analytics</b><p>Conversation, campaign, revenue and automation reporting.</p></article>
        </div>
        <div class="core-link"><a class="btn btn-primary" href="<?= site_url('platform') ?>">View complete platform →</a></div>
      </div>
    </section>

    <section id="solutions">
      <div class="wrap">
        <div class="section-head"><span class="kicker">Made for growing teams</span><h2>One platform, many business journeys</h2><p>Configure the AI and automation around the way your customers already buy, book and ask for help.</p></div>
        <div class="use-grid">
          <article class="use"><span>Ecommerce</span><h3>Product discovery to paid order</h3><p>Recommend products, confirm stock, recover carts and send order updates.</p></article>
          <article class="use"><span>Services</span><h3>Enquiry to appointment</h3><p>Qualify requirements, book a slot and send reminders automatically.</p></article>
          <article class="use"><span>Creators</span><h3>Comment to qualified lead</h3><p>Turn social engagement into trackable conversations and opportunities.</p></article>
          <article class="use"><span>Support</span><h3>Question to resolution</h3><p>Resolve common requests instantly and escalate sensitive issues to your team.</p></article>
        </div>
      </div>
    </section>

    <section>
      <div class="wrap">
        <div class="section-head"><span class="kicker">Start at the right scale</span><h2>Launch one workflow, grow into the full platform</h2><p>Plans support small teams getting started and larger operations that need multiple channels, automation and commerce.</p></div>
        <div class="pricing-preview">
          <article class="price-box"><span>Start focused</span><h3>Connect your first channel</h3><p>Launch a shared inbox, AI knowledge and a practical automation for your most important customer journey.</p><a class="btn btn-light" href="<?= site_url('pricing') ?>">See plans</a></article>
          <article class="price-box highlight"><span>Scale operations</span><h3>Unify every customer workflow</h3><p>Add social channels, CRM, campaigns, ecommerce actions, reporting and role-based team routing.</p><a class="btn btn-light" href="<?= site_url('contact') ?>">Discuss your requirements</a></article>
        </div>
      </div>
    </section>

    <section class="channels">
      <div class="wrap">
        <div class="section-head"><span class="kicker">Common questions</span><h2>What businesses need to know</h2></div>
        <div class="faq">
          <details open><summary>Is Talk AI Pilot only a WhatsApp tool?</summary><p>No. WhatsApp is one channel. The platform also covers Instagram, Facebook, YouTube, website chat and mobile apps, with shared automation, CRM and reporting.</p></details>
          <details><summary>Can our team take over from the AI?</summary><p>Yes. Human handoff is a core part of the product. The assigned person receives the conversation and customer context.</p></details>
          <details><summary>Can it connect with products, payments and orders?</summary><p>Yes. Ecommerce workflows can search products, check inventory, recover carts, send payment links, create orders and provide tracking updates.</p></details>
          <details><summary>Do we have to automate everything at once?</summary><p>No. The best approach is to launch one high-value journey, measure it, and then expand to more channels and processes.</p></details>
        </div>
      </div>
    </section>

    <section class="cta">
      <div class="wrap cta-box">
        <div><h2>Ready to put customer conversations on autopilot?</h2><p>Connect your channels and launch your first automation with Talk AI Pilot.</p></div>
        <a class="btn" href="<?= site_url('contact') ?>">Talk to our team →</a>
      </div>
    </section>
  </main>

  <footer>
    <div class="wrap">
      <div class="footer-grid">
        <div class="footer-brand"><a class="brand" style="color:#fff" href="<?= base_url() ?>">Talk <span>AI</span> Pilot</a><p>AI-powered conversations and automation for WhatsApp, social channels, websites and mobile apps.</p></div>
        <div><div class="footer-title">Platform</div><div class="footer-links"><a href="<?= site_url('platform') ?>">Platform overview</a><a href="<?= site_url('channels') ?>">Channels</a><a href="<?= site_url('solutions') ?>">Solutions</a><a href="<?= site_url('pricing') ?>">Pricing</a></div></div>
        <div><div class="footer-title">Company</div><div class="footer-links"><a href="<?= site_url('about') ?>">About</a><a href="<?= site_url('services') ?>">Services</a><a href="<?= site_url('contact') ?>">Contact</a><a href="<?= site_url('admin/login') ?>">Sign in</a></div></div>
        <div><div class="footer-title">Legal</div><div class="footer-links"><a href="<?= site_url('privacy') ?>">Privacy policy</a><a href="<?= site_url('terms') ?>">Terms</a></div></div>
      </div>
      <div class="copyright"><span>© <?= date('Y') ?> Talk AI Pilot. All rights reserved.</span><span>AI conversations that keep business moving.</span></div>
    </div>
  </footer>
</body>
</html>

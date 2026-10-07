<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= htmlspecialchars($title) ?> — Talk AI Pilot</title>
  <meta name="description" content="<?= htmlspecialchars($lead) ?>">
  <link rel="icon" href="<?= base_url('assets/images/logo/favicon.svg') ?>">
  <link rel="stylesheet" href="<?= base_url('assets/website/css/landing.css') ?>">
  <style>
    :root{--ink:#0b1220;--muted:#5c6478;--purple:#20c876;--purple2:#17a864;--navy:#0a1628;--line:#e5e8ef;--soft:#f4f7fb;--green:#20c876;--green-dark:#17a864}
    *{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;color:var(--ink);font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;line-height:1.6}a{color:inherit;text-decoration:none}
    .wrap{width:min(1160px,calc(100% - 40px));margin:auto}.nav{position:sticky;top:0;z-index:20;background:rgba(255,255,255,.93);border-bottom:1px solid var(--line);backdrop-filter:blur(14px)}.nav-in{height:76px;display:flex;align-items:center;justify-content:space-between;gap:24px}
    .brand{font-size:21px;font-weight:850;letter-spacing:-.045em;white-space:nowrap}.brand span,.active{color:var(--purple)}.links{display:flex;align-items:center;gap:25px;font-size:14px;font-weight:700;color:#424a60}.links a:hover{color:var(--purple)}
    .actions{display:flex;gap:10px}.btn{min-height:44px;padding:0 19px;display:inline-flex;align-items:center;justify-content:center;border:1px solid var(--line);border-radius:11px;background:#fff;font-size:14px;font-weight:800}.btn-primary{color:#fff;border:0;background:linear-gradient(135deg,var(--purple),var(--purple2));box-shadow:0 10px 25px rgba(103,80,232,.23)}
    .menu{display:none;border:0;background:none;font-size:24px}.hero{padding:94px 0 86px;text-align:center;background:radial-gradient(circle at 50% 0,#e9e3ff,transparent 44%),linear-gradient(#fbfaff,#fff)}.eyebrow{display:inline-flex;padding:7px 12px;border-radius:99px;background:#f0edff;color:#5741d5;font-size:12px;font-weight:850;letter-spacing:.1em;text-transform:uppercase}.hero h1{max-width:900px;margin:20px auto 20px;font-size:clamp(40px,5.4vw,65px);line-height:1.06;letter-spacing:-.055em}.hero p{max-width:760px;margin:0 auto 30px;color:var(--muted);font-size:18px}
    .hero-special{padding:82px 0;background:radial-gradient(circle at 82% 20%,#e8e1ff,transparent 35%),linear-gradient(180deg,#fbfaff,#fff)}.hero-special-grid{display:grid;grid-template-columns:1fr 1fr;gap:65px;align-items:center}.hero-special h1{margin:18px 0;font-size:clamp(39px,5vw,61px);line-height:1.07;letter-spacing:-.055em}.hero-special p{margin:0 0 27px;color:var(--muted);font-size:17px}.hero-visual{position:relative;overflow:hidden;padding:9px;border-radius:25px;background:linear-gradient(140deg,#8d72ff,#251468);box-shadow:0 25px 65px rgba(44,31,106,.22)}.hero-visual img{display:block;width:100%;aspect-ratio:4/3;object-fit:cover;border-radius:18px}.hero-visual-copy{padding:35px;border-radius:24px;background:var(--navy);color:#fff}.hero-visual-copy small{color:#9f8cff;font-weight:850;text-transform:uppercase}.hero-visual-copy h3{font-size:27px;line-height:1.2;margin:16px 0}.hero-visual-copy p{color:#b2b9cf;font-size:14px}.mini-flow{display:grid;gap:9px;margin-top:24px}.mini-flow span{padding:12px 14px;border:1px solid rgba(255,255,255,.1);border-radius:11px;background:rgba(255,255,255,.05);font-size:13px}.mini-flow b{color:#62e0a1;margin-right:8px}
    .platform-strip{margin-top:-30px}.strip{display:grid;grid-template-columns:repeat(6,1fr);padding:18px;border:1px solid var(--line);border-radius:18px;background:#fff;box-shadow:0 18px 50px rgba(25,20,63,.08)}.strip span{text-align:center;padding:8px;font-size:12px;font-weight:800;border-right:1px solid var(--line)}.strip span:last-child{border:0}
    section{padding:90px 0}.head{max-width:700px;margin:0 auto 45px;text-align:center}.kicker{color:var(--purple);font-size:12px;font-weight:850;letter-spacing:.12em;text-transform:uppercase}.head h2{font-size:clamp(30px,4vw,45px);line-height:1.13;letter-spacing:-.04em;margin:10px 0 0}
    .grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}.card{padding:28px;border:1px solid var(--line);border-radius:19px;background:#fff;box-shadow:0 10px 32px rgba(25,20,63,.045)}.card-num{width:40px;height:40px;display:grid;place-items:center;border-radius:12px;background:#efecff;color:var(--purple);font-weight:900}.card h3{margin:20px 0 8px;font-size:20px}.card>p{color:var(--muted);font-size:14px}.card ul{list-style:none;padding:15px 0 0;margin:15px 0 0;border-top:1px solid var(--line);display:grid;gap:8px;font-size:13px}.card li:before{content:"✓";color:var(--green);font-weight:900;margin-right:8px}
    .steps-bg{background:var(--navy);color:#fff}.steps-bg .kicker{color:#aa99ff}.steps{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}.step{padding:25px;border:1px solid rgba(255,255,255,.1);border-radius:17px;background:rgba(255,255,255,.045)}.step b{display:block;color:#9e89ff;font-size:13px}.step h3{margin:14px 0 7px}.step p{margin:0;color:#aeb5ca;font-size:14px}
    .steps-light .step{border-color:var(--line);background:var(--soft)}.steps-light .step p{color:var(--muted)}
    .price-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}.price{padding:32px;border:1px solid var(--line);border-radius:21px;background:#fff}.price:nth-child(2){border:2px solid var(--purple);box-shadow:0 20px 55px rgba(103,80,232,.15)}.price h3{font-size:22px;margin:0}.price>p{min-height:52px;color:var(--muted);font-size:14px}.amount{display:block;margin:24px 0;font-size:34px;font-weight:900;letter-spacing:-.04em}.price ul{list-style:none;margin:0 0 25px;padding:0;display:grid;gap:10px;font-size:14px}.price li:before{content:"✓";color:var(--green);font-weight:900;margin-right:9px}.price .btn{width:100%}
    .contact{display:grid;grid-template-columns:.75fr 1.25fr;gap:55px}.contact-copy h2{font-size:38px;line-height:1.14;letter-spacing:-.04em}.contact-copy p{color:var(--muted)}.contact-point{margin-top:22px;padding:18px;border-radius:14px;background:var(--soft);font-size:14px}.form{display:grid;grid-template-columns:1fr 1fr;gap:14px;padding:28px;border:1px solid var(--line);border-radius:20px;box-shadow:0 15px 45px rgba(25,20,63,.07)}.field{display:grid;gap:6px}.field.full{grid-column:1/-1}.field label{font-size:12px;font-weight:800}.field input,.field textarea,.field select{width:100%;padding:12px 13px;border:1px solid #dfe1eb;border-radius:10px;font:inherit}.field textarea{min-height:120px;resize:vertical}.form .btn{grid-column:1/-1}
    .legal{max-width:900px;margin:auto;padding:32px;border:1px solid var(--line);border-radius:20px;background:#fff;box-shadow:0 15px 45px rgba(25,20,63,.06)}.legal .site-page{padding:0}.legal .site-legal-inner{max-width:none}.legal h2{margin-top:30px;font-size:23px;letter-spacing:-.02em}.legal p,.legal li{color:var(--muted);font-size:15px}.legal a{color:var(--purple);font-weight:700}
    .story{display:grid;grid-template-columns:.8fr 1.2fr;gap:65px;align-items:start}.story h2{margin:0;font-size:42px;line-height:1.15;letter-spacing:-.045em}.story-copy p{margin:0 0 18px;color:var(--muted);font-size:17px}.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:13px;margin-top:45px}.stat{padding:22px;border:1px solid var(--line);border-radius:15px;background:var(--soft);text-align:center}.stat strong{display:block;color:var(--purple);font-size:28px}.stat span{font-size:12px;color:var(--muted);font-weight:700}
    .outcomes{display:grid;grid-template-columns:repeat(3,1fr);gap:17px;margin-top:40px}.outcome{padding:25px;border-radius:17px;background:#121a3d;color:#fff}.outcome b{font-size:18px}.outcome p{margin:8px 0 0;color:#b3bad0;font-size:14px}
    .contact-topics{display:grid;gap:11px;margin-top:25px}.contact-topic{display:grid;grid-template-columns:38px 1fr;gap:12px;padding:15px;border:1px solid var(--line);border-radius:13px}.contact-topic>span{width:38px;height:38px;display:grid;place-items:center;border-radius:10px;background:#efecff;color:var(--purple);font-size:12px;font-weight:900}.contact-topic b{display:block}.contact-topic small{color:var(--muted)}
    .cta{padding:45px 0 90px}.cta-in{display:grid;grid-template-columns:1fr auto;gap:25px;align-items:center;padding:45px;border-radius:24px;background:linear-gradient(130deg,#111a43,#4425b8);color:#fff}.cta h2{margin:0;font-size:34px;letter-spacing:-.04em}.cta p{margin:6px 0 0;color:#d4d7e7}.cta .btn{color:#312077}
    footer{padding:50px 0 25px;background:#070b1c;color:#a5abc0}.footer{display:grid;grid-template-columns:1.4fr repeat(3,1fr);gap:38px}.ft-title{color:#fff;font-weight:800;margin-bottom:12px}.ft-links{display:grid;gap:8px;font-size:14px}.copy{margin-top:35px;padding-top:20px;border-top:1px solid rgba(255,255,255,.1);font-size:12px}
    @media(max-width:1020px){.links{display:none}.links.open{position:absolute;display:grid;left:20px;right:20px;top:68px;padding:20px;border:1px solid var(--line);border-radius:14px;background:#fff;box-shadow:0 18px 45px rgba(20,18,50,.14)}.menu{display:block}.strip{grid-template-columns:repeat(3,1fr)}.grid{grid-template-columns:1fr 1fr}.steps{grid-template-columns:1fr 1fr}.hero-special-grid{gap:35px}}
    @media(max-width:720px){.wrap{width:min(100% - 30px,620px)}.nav-in{height:68px}.actions .btn{display:none}.hero{padding:65px 0}.hero h1{font-size:38px}.hero-special{padding:58px 0}.hero-special-grid,.story{grid-template-columns:1fr}.strip{grid-template-columns:1fr 1fr}.grid,.steps,.price-grid,.contact,.footer,.outcomes{grid-template-columns:1fr}.stats{grid-template-columns:1fr 1fr}.form{grid-template-columns:1fr}.field{grid-column:1}.cta-in{grid-template-columns:1fr;text-align:center;padding:32px 22px}.cta h2{font-size:28px}}
  </style>
</head>
<body>
  <header class="nav"><div class="wrap nav-in">
    <a class="brand" href="<?= base_url() ?>">Talk <span>AI</span> Pilot</a>
    <nav class="links" aria-label="Primary">
      <a href="<?= base_url() ?>">Home</a>
      <a class="<?= ($active ?? '') === 'platform' ? 'active' : '' ?>" href="<?= site_url('platform') ?>">Product</a>
      <a class="<?= ($active ?? '') === 'channels' ? 'active' : '' ?>" href="<?= site_url('channels') ?>">Channels</a>
      <a class="<?= ($active ?? '') === 'pricing' ? 'active' : '' ?>" href="<?= site_url('pricing') ?>">Pricing</a>
      <a class="<?= ($active ?? '') === 'about' ? 'active' : '' ?>" href="<?= site_url('about') ?>">About</a>
      <a class="<?= ($active ?? '') === 'services' ? 'active' : '' ?>" href="<?= site_url('services') ?>">Services</a>
      <a class="<?= ($active ?? '') === 'contact' ? 'active' : '' ?>" href="<?= site_url('contact') ?>">Contact</a>
    </nav>
    <div class="actions"><a class="btn" href="<?= site_url('admin/login') ?>">Login</a><a class="btn btn-primary" href="<?= site_url('contact') ?>">Book demo</a><button class="menu" onclick="document.querySelector('.links').classList.toggle('open')" aria-label="Menu">☰</button></div>
  </div></header>

  <main>
    <?php if (!empty($page_variant) && in_array($page_variant, ['about', 'services', 'contact'], true)): ?>
    <section class="hero-special"><div class="wrap hero-special-grid">
      <div><span class="eyebrow"><?= htmlspecialchars($eyebrow) ?></span><h1><?= htmlspecialchars($title) ?></h1><p><?= htmlspecialchars($lead) ?></p><a class="btn btn-primary" href="<?= $page_variant === 'contact' ? '#contact-form' : site_url('contact') ?>"><?= $page_variant === 'contact' ? 'Share your requirements ↓' : 'Talk to our team →' ?></a></div>
      <?php if ($page_variant === 'about'): ?>
      <div class="hero-visual"><img src="<?= base_url('assets/website/images/talk-ai-pilot-platform-core.jpg') ?>" alt="Talk AI Pilot connected customer automation platform"></div>
      <?php else: ?>
      <div class="hero-visual-copy">
        <small><?= $page_variant === 'services' ? 'Implementation, not just software' : 'A useful first conversation' ?></small>
        <h3><?= $page_variant === 'services' ? 'A complete path from discovery to a live workflow.' : 'Bring your channels, process and goals. We will help define the right starting point.' ?></h3>
        <div class="mini-flow">
          <?php if ($page_variant === 'services'): ?>
          <span><b>01</b> Discover the customer journey</span><span><b>02</b> Design AI, data and handoff</span><span><b>03</b> Implement and test</span><span><b>04</b> Launch and improve</span>
          <?php else: ?>
          <span><b>✓</b> No generic sales pitch</span><span><b>✓</b> Practical workflow recommendation</span><span><b>✓</b> Clear channels and integration scope</span>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </div></section>
    <?php else: ?>
    <section class="hero"><div class="wrap"><span class="eyebrow"><?= htmlspecialchars($eyebrow) ?></span><h1><?= htmlspecialchars($title) ?></h1><p><?= htmlspecialchars($lead) ?></p><a class="btn btn-primary" href="<?= site_url('contact') ?>">Talk to our team →</a></div></section>
    <div class="platform-strip"><div class="wrap strip"><span>Unified Inbox</span><span>AI Agent</span><span>Automation</span><span>CRM</span><span>Commerce</span><span>Analytics</span></div></div>
    <?php endif; ?>

    <?php if (!empty($about_story)): ?>
    <section><div class="wrap">
      <div class="story"><div><span class="kicker">Why we exist</span><h2>Customer communication should not feel fragmented.</h2></div><div class="story-copy"><?php foreach ($about_story as $paragraph): ?><p><?= htmlspecialchars($paragraph) ?></p><?php endforeach; ?></div></div>
      <div class="stats"><?php foreach ($about_stats as $stat): ?><div class="stat"><strong><?= htmlspecialchars($stat[0]) ?></strong><span><?= htmlspecialchars($stat[1]) ?></span></div><?php endforeach; ?></div>
    </div></section>
    <?php endif; ?>

    <?php if (!empty($sections)): ?>
    <section><div class="wrap"><div class="head"><span class="kicker"><?= !empty($page_variant) && $page_variant === 'services' ? 'What we implement' : (!empty($page_variant) && $page_variant === 'about' ? 'What defines us' : 'Explore the capabilities') ?></span><h2><?= !empty($page_variant) && $page_variant === 'services' ? 'Services built around real operational outcomes' : (!empty($page_variant) && $page_variant === 'about' ? 'A practical approach to responsible automation' : 'Designed as one connected system') ?></h2></div><div class="grid">
      <?php foreach ($sections as $i => $section): ?>
      <article class="card"><span class="card-num"><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></span><h3><?= htmlspecialchars($section[0]) ?></h3><p><?= htmlspecialchars($section[1]) ?></p><ul><?php foreach ($section[2] as $item): ?><li><?= htmlspecialchars($item) ?></li><?php endforeach; ?></ul></article>
      <?php endforeach; ?>
    </div></div></section>
    <?php endif; ?>

    <?php if (!empty($pricing)): ?>
    <section><div class="wrap"><div class="head"><span class="kicker">Plans for every stage</span><h2>Choose the right starting point</h2></div><div class="price-grid">
      <?php foreach ($pricing as $plan): ?><article class="price"><h3><?= htmlspecialchars($plan[0]) ?></h3><p><?= htmlspecialchars($plan[1]) ?></p><span class="amount"><?= htmlspecialchars($plan[2]) ?></span><ul><?php foreach ($plan[3] as $item): ?><li><?= htmlspecialchars($item) ?></li><?php endforeach; ?></ul><a class="btn btn-primary" href="<?= site_url('contact') ?>">Get started</a></article><?php endforeach; ?>
    </div></div></section>
    <?php endif; ?>

    <?php if (!empty($contact_form)): ?>
    <section id="contact-form"><div class="wrap contact"><div class="contact-copy"><span class="kicker">What we will discuss</span><h2>Start with the customer journey—not a feature list</h2><p>We use your current process to determine which channel, knowledge, workflow and integration should come first.</p>
      <div class="contact-topics"><?php foreach ($contact_topics as $topic): ?><div class="contact-topic"><span><?= htmlspecialchars($topic[0]) ?></span><div><b><?= htmlspecialchars($topic[1]) ?></b><small><?= htmlspecialchars($topic[2]) ?></small></div></div><?php endforeach; ?></div>
      <div class="contact-point"><b>What happens next?</b><br>We review your requirements and contact you with a practical recommendation.</div></div>
      <form class="form" method="post" action="<?= site_url('contact/save') ?>">
        <?= sk_csrf_field() ?>
        <div class="field"><label for="name">Name</label><input id="name" name="name" required></div>
        <div class="field"><label for="email">Business email</label><input id="email" type="email" name="email" required></div>
        <div class="field"><label for="phone">Phone</label><input id="phone" name="phone"></div>
        <div class="field"><label for="business">Company</label><input id="business" name="business"></div>
        <div class="field full"><label for="subject">What do you want to automate?</label><select id="subject" name="subject"><option>WhatsApp automation</option><option>Social media conversations</option><option>AI sales and support</option><option>Ecommerce and payments</option><option>Complete omnichannel platform</option></select></div>
        <div class="field full"><label for="message">Tell us about your current process</label><textarea id="message" name="message" required></textarea></div>
        <button class="btn btn-primary" type="submit">Send enquiry</button>
      </form>
    </div></section>
    <?php endif; ?>

    <?php if (!empty($legal_html)): ?>
    <section><div class="wrap"><div class="legal"><?= $legal_html ?></div></div></section>
    <?php endif; ?>

    <?php if (!empty($service_outcomes)): ?>
    <section class="steps-bg"><div class="wrap"><div class="head"><span class="kicker">What changes after launch</span><h2>Automation your customers and team can feel</h2></div><div class="outcomes"><?php foreach ($service_outcomes as $outcome): ?><article class="outcome"><b><?= htmlspecialchars($outcome[0]) ?></b><p><?= htmlspecialchars($outcome[1]) ?></p></article><?php endforeach; ?></div></div></section>
    <?php endif; ?>

    <?php if (!empty($steps)): ?>
    <section class="<?= !empty($service_outcomes) ? 'steps-light' : 'steps-bg' ?>"><div class="wrap"><div class="head"><span class="kicker">How it works</span><h2><?= htmlspecialchars($steps_title) ?></h2></div><div class="steps">
      <?php foreach ($steps as $i => $step): ?><article class="step"><b>STEP <?= $i + 1 ?></b><h3><?= htmlspecialchars($step[0]) ?></h3><p><?= htmlspecialchars($step[1]) ?></p></article><?php endforeach; ?>
    </div></div></section>
    <?php endif; ?>

    <section class="cta"><div class="wrap cta-in"><div><h2>Build your first Talk AI Pilot workflow</h2><p>Connect the conversation to the next business action.</p></div><a class="btn" href="<?= site_url('contact') ?>">Start the conversation →</a></div></section>
  </main>
  <footer><div class="wrap"><div class="footer"><div><a class="brand" style="color:#fff" href="<?= base_url() ?>">Talk <span>AI</span> Pilot</a><p>AI conversations, automation, CRM and commerce in one connected platform.</p></div><div><div class="ft-title">Main menu</div><div class="ft-links"><a href="<?= base_url() ?>">Home</a><a href="<?= site_url('about') ?>">About</a><a href="<?= site_url('services') ?>">Services</a><a href="<?= site_url('contact') ?>">Contact</a></div></div><div><div class="ft-title">Platform</div><div class="ft-links"><a href="<?= site_url('platform') ?>">Platform</a><a href="<?= site_url('channels') ?>">Channels</a><a href="<?= site_url('solutions') ?>">Solutions</a></div></div><div><div class="ft-title">Legal</div><div class="ft-links"><a href="<?= site_url('privacy') ?>">Privacy</a><a href="<?= site_url('terms') ?>">Terms</a></div></div></div><div class="copy">© <?= date('Y') ?> Talk AI Pilot. All rights reserved.</div></div></footer>
</body>
</html>

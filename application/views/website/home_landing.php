<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Talk AI Pilot — WhatsApp &amp; AI Automation for Business</title>
  <meta name="description" content="Chat smarter, convert faster. One platform for WhatsApp, Instagram, Facebook, YouTube and app conversations — AI replies, CRM, commerce and human handoff.">
  <link rel="icon" href="<?= base_url('assets/images/logo/favicon.svg') ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;650;750;800;850&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= base_url('assets/website/css/landing.css') ?>">
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
      return '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">'.($paths[$name] ?? '').'</svg>';
  };
  ?>

  <div class="promo">Powered by <strong>Meta WhatsApp Cloud API</strong> · Meta Business Agent · Your data stays in your shop</div>

  <header class="nav">
    <div class="wrap nav-inner">
      <a class="brand" href="<?= base_url() ?>">Talk <span>AI</span> Pilot</a>
      <nav class="nav-links" id="navLinks" aria-label="Primary">
        <a href="<?= site_url('platform') ?>">Product</a>
        <a href="<?= site_url('channels') ?>">Channels</a>
        <a href="<?= site_url('solutions') ?>">Solutions</a>
        <a href="<?= site_url('pricing') ?>">Pricing</a>
        <a href="<?= site_url('about') ?>">About</a>
        <a href="<?= site_url('contact') ?>">Contact</a>
      </nav>
      <div class="nav-actions">
        <a class="btn btn-ghost" href="<?= site_url('admin/login') ?>">Login</a>
        <a class="btn btn-primary" href="<?= site_url('contact') ?>">Book demo</a>
        <button class="menu-btn" type="button" aria-label="Menu" onclick="document.getElementById('navLinks').classList.toggle('open')">☰</button>
      </div>
    </div>
  </header>

  <main>
    <section class="hero">
      <div class="wrap hero-grid">
        <div>
          <div class="hero-badges">
            <span class="pill meta">Meta Business Agent ready</span>
            <span class="pill hot">Chat · Product · Pay · Invoice</span>
          </div>
          <h1>Chat smarter.<br><em>Convert faster.</em></h1>
          <p class="hero-lead">Connect WhatsApp, Instagram, Facebook, YouTube and your app in one inbox. AI qualifies leads, shows products, sends pay links and hands off to your team — 24/7.</p>
          <div class="hero-actions">
            <a class="btn btn-primary" href="<?= site_url('contact') ?>">Start free trial →</a>
            <a class="btn btn-dark" href="<?= site_url('platform') ?>">See the platform</a>
          </div>
          <div class="hero-trust">
            <span>No credit card to start</span>
            <span>Human handoff built in</span>
            <span>Multi-vendor SaaS core</span>
          </div>
        </div>

        <div class="hero-ui">
          <div class="ui-stack">
            <div class="ui-main">
              <div class="ui-titlebar">
                <span class="ui-dots"><i></i><i></i><i></i></span>
                Talk AI Pilot · Shared inbox
                <span style="color:#20c876;font-weight:800">● Live</span>
              </div>
              <div class="ui-body">
                <div class="ui-sidebar">
                  <a class="active" href="#">Chats</a>
                  <a href="#">Campaigns</a>
                  <a href="#">Templates</a>
                  <a href="#">Automation</a>
                  <a href="#">CRM</a>
                </div>
                <div class="ui-chat">
                  <div class="chat-row chat-in">Do you have maroon Kanchipuram for this weekend?</div>
                  <div class="chat-row chat-out">Yes — maroon &amp; gold zari, 6 yards, in stock.
                    <div class="chat-product"><b>Kanchipuram Silk · ₹12,499</b>Pay link sent in chat</div>
                  </div>
                  <div class="chat-row chat-out" style="font-size:11px;opacity:.9">Paid · Invoice #SA-1842 on WhatsApp ✓</div>
                </div>
              </div>
            </div>
            <div class="ui-float one"><strong>98%</strong><small>Template delivery rate</small></div>
            <div class="ui-float two"><strong>47s</strong><small>Avg. time to paid order</small></div>
          </div>
        </div>
      </div>
    </section>

    <div class="logos">
      <div class="wrap logos-inner">
        <span><?= $icon('wa') ?> WhatsApp Cloud</span>
        <span><?= $icon('ig') ?> Instagram</span>
        <span><?= $icon('fb') ?> Facebook</span>
        <span><?= $icon('yt') ?> YouTube</span>
        <span><?= $icon('app') ?> Mobile app</span>
      </div>
    </div>

    <section id="features">
      <div class="wrap">
        <div class="section-head">
          <span class="kicker">Advanced features</span>
          <h2>Everything you need to grow on WhatsApp &amp; social</h2>
          <p>Same product depth as enterprise tools — built on your Talk AI Pilot core: inbox, Meta Agent, connectors, CRM and commerce.</p>
        </div>

        <div class="feature-row">
          <div class="feature-copy">
            <h3>Multiple chats, one team inbox</h3>
            <p>WhatsApp, Instagram DMs, Facebook Messenger and app enquiries land in one place. Assign, tag, note and take over from the AI anytime.</p>
            <ul class="feature-list">
              <li>Thread ownership &amp; Meta Business Agent handoff</li>
              <li>24-hour window + template restart</li>
              <li>Per-vendor isolation for SaaS</li>
            </ul>
          </div>
          <div class="feature-mock mock-panel">
            <strong style="font-size:13px;color:#64748b">INBOX PREVIEW</strong>
            <div style="margin-top:12px;display:grid;gap:8px;font-size:12px">
              <div style="padding:10px;border-radius:10px;background:#f8fafc;border:1px solid #e2e8f0"><b>Nahar Singh</b> · Yesterday · Photo</div>
              <div style="padding:10px;border-radius:10px;background:#ecfdf5;border:1px solid #bbf7d0"><b>RJ Host</b> · Active · Hii</div>
              <div style="padding:10px;border-radius:10px;background:#f8fafc;border:1px solid #e2e8f0;opacity:.7">Send template to restart window…</div>
            </div>
          </div>
        </div>

        <div class="feature-row reverse">
          <div class="feature-copy">
            <h3>Flow builder &amp; automation</h3>
            <p>Route messages, acknowledge customers, connect to Meta Agent or hand over to staff — without losing context.</p>
            <ul class="feature-list">
              <li>Triggers, conditions and actions</li>
              <li>Commerce connectors (search, stock, orders)</li>
              <li>Campaign broadcasts with reports</li>
            </ul>
          </div>
          <div class="feature-mock mock-panel dark">
            <strong style="font-size:12px;color:#94a3b8">AUTOMATION · DRAFT</strong>
            <div class="flow-steps">
              <div class="flow-step"><em>▶</em><div><b>Conversation start</b><span>On any message</span></div><strong>TRIGGER</strong></div>
              <div class="flow-step"><em>↳</em><div><b>Meta Agent reply</b><span>Product + pay link</span></div><strong>AI</strong></div>
              <div class="flow-step"><em>👤</em><div><b>Human handoff</b><span>Customer asks for agent</span></div><strong>READY</strong></div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="stats-band">
      <div class="wrap">
        <div class="section-head">
          <span class="kicker">Why WhatsApp?</span>
          <h2>Meet customers where they already are</h2>
        </div>
        <div class="stats-grid">
          <article class="stat-card"><strong>3B+</strong><span>People use WhatsApp monthly (Meta)</span></article>
          <article class="stat-card"><strong>24h</strong><span>Service window for free-form replies</span></article>
          <article class="stat-card"><strong>Cloud API</strong><span>Official Meta WhatsApp Business Platform</span></article>
        </div>
      </div>
    </section>

    <section class="channels" id="channels">
      <div class="wrap">
        <div class="section-head">
          <span class="kicker">Omnichannel</span>
          <h2>One customer, every channel</h2>
          <p>Every comment, DM and chat becomes the same lead in your CRM.</p>
        </div>
        <div class="grid-5">
          <article class="card"><span class="card-icon wa"><?= $icon('wa') ?></span><h3>WhatsApp</h3><p>AI replies, templates, pay links and invoices in one thread.</p></article>
          <article class="card"><span class="card-icon ig"><?= $icon('ig') ?></span><h3>Instagram</h3><p>Comments and DMs become qualified sales chats.</p></article>
          <article class="card"><span class="card-icon fb"><?= $icon('fb') ?></span><h3>Facebook</h3><p>Messenger and page engagement in the same inbox.</p></article>
          <article class="card"><span class="card-icon yt"><?= $icon('yt') ?></span><h3>YouTube</h3><p>Turn comment intent into conversations.</p></article>
          <article class="card"><span class="card-icon app"><?= $icon('app') ?></span><h3>Mobile app</h3><p>In-app support tied to the same customer record.</p></article>
        </div>
      </div>
    </section>

    <section>
      <div class="wrap">
        <div class="section-head">
          <span class="kicker">Platform core</span>
          <h2>Your stack, not a generic chatbot</h2>
        </div>
        <div class="grid-3">
          <article class="card"><h3>Unified inbox &amp; CRM</h3><p>Leads, segments, timeline and team assignment.</p></article>
          <article class="card"><h3>Meta Business Agent</h3><p>Primary AI on WhatsApp with standby ingest and connector tools.</p></article>
          <article class="card"><h3>Commerce actions</h3><p>Catalogue, stock, cart, payment, order status and delivery.</p></article>
          <article class="card"><h3>Campaigns</h3><p>Compliant broadcasts, reminders and win-back flows.</p></article>
          <article class="card"><h3>Analytics</h3><p>Response time, conversion, campaign and agent performance.</p></article>
          <article class="card"><h3>Integrations</h3><p>Webhooks, API, Razorpay and your existing shop data.</p></article>
        </div>
      </div>
    </section>

    <section class="pricing" id="pricing">
      <div class="wrap">
        <div class="section-head">
          <span class="kicker">Pricing</span>
          <h2>Plans for every stage</h2>
          <p>WhatsApp / Meta conversation fees and AI usage may apply separately per Meta billing.</p>
        </div>
        <div class="price-grid">
          <article class="price-card">
            <h3>Starter</h3>
            <p class="desc">Small team, first automated channel.</p>
            <div class="amount">₹999<small>/mo</small></div>
            <ul>
              <li>1 business channel</li>
              <li>Shared team inbox</li>
              <li>Basic AI replies</li>
              <li>Core automation</li>
            </ul>
            <a class="btn btn-primary" href="<?= site_url('contact') ?>">Get started</a>
          </article>
          <article class="price-card popular">
            <span class="price-badge">Most popular</span>
            <h3>Growth</h3>
            <p class="desc">Leads, campaigns and multi-channel support.</p>
            <div class="amount">₹2,999<small>/mo</small></div>
            <ul>
              <li>3 business channels</li>
              <li>AI knowledge base</li>
              <li>Advanced workflows</li>
              <li>CRM &amp; reports</li>
            </ul>
            <a class="btn btn-primary" href="<?= site_url('contact') ?>">Get started</a>
          </article>
          <article class="price-card">
            <h3>Business</h3>
            <p class="desc">Multi-team ops with commerce &amp; custom journeys.</p>
            <div class="amount">₹5,999<small>/mo</small></div>
            <ul>
              <li>All supported channels</li>
              <li>Meta Agent + connectors</li>
              <li>Roles &amp; routing</li>
              <li>Priority onboarding</li>
            </ul>
            <a class="btn btn-dark" href="<?= site_url('contact') ?>">Talk to sales</a>
          </article>
        </div>
      </div>
    </section>

    <section>
      <div class="wrap">
        <div class="section-head">
          <span class="kicker">FAQ</span>
          <h2>Common questions</h2>
        </div>
        <div class="faq-wrap">
          <details open><summary>Is this only WhatsApp?</summary><p>No. WhatsApp is the primary channel; Instagram, Facebook, YouTube and mobile app conversations share the same inbox, CRM and automation.</p></details>
          <details><summary>How does Meta Business Agent fit in?</summary><p>Meta can own the live reply on WhatsApp while your app ingests messages, exposes commerce connectors and handles human handoff when needed.</p></details>
          <details><summary>Can we keep our existing shop &amp; payments?</summary><p>Yes. Connectors search products, check stock, order status and delivery from your Talk AI Pilot store — per vendor in SaaS mode.</p></details>
          <details><summary>Do we automate everything on day one?</summary><p>No. Launch one high-value journey (e.g. WhatsApp sales), measure it, then expand channels and workflows.</p></details>
        </div>
      </div>
    </section>

    <section class="cta-section">
      <div class="wrap cta-box">
        <div>
          <h2>Ready to turn chats into revenue?</h2>
          <p>Book a demo — we’ll map channels, Meta Agent setup and your first automation.</p>
        </div>
        <a class="btn" href="<?= site_url('contact') ?>">Book demo →</a>
      </div>
    </section>
  </main>

  <footer>
    <div class="wrap">
      <div class="footer-grid">
        <div class="footer-brand">
          <a class="brand" style="color:#fff" href="<?= base_url() ?>">Talk <span>AI</span> Pilot</a>
          <p>WhatsApp-first AI automation for marketing, sales and support — built on your commerce and CRM core.</p>
        </div>
        <div>
          <div class="footer-title">Product</div>
          <div class="footer-links">
            <a href="<?= site_url('platform') ?>">Platform</a>
            <a href="<?= site_url('channels') ?>">Channels</a>
            <a href="<?= site_url('solutions') ?>">Solutions</a>
            <a href="<?= site_url('pricing') ?>">Pricing</a>
          </div>
        </div>
        <div>
          <div class="footer-title">Company</div>
          <div class="footer-links">
            <a href="<?= site_url('about') ?>">About</a>
            <a href="<?= site_url('services') ?>">Services</a>
            <a href="<?= site_url('contact') ?>">Contact</a>
            <a href="<?= site_url('admin/login') ?>">Login</a>
          </div>
        </div>
        <div>
          <div class="footer-title">Resources</div>
          <div class="footer-links">
            <a href="<?= site_url('admin/meta/agent') ?>">Meta Business Agent</a>
            <a href="<?= site_url('admin/login') ?>">Admin panel</a>
          </div>
        </div>
        <div>
          <div class="footer-title">Legal</div>
          <div class="footer-links">
            <a href="<?= site_url('privacy') ?>">Privacy</a>
            <a href="<?= site_url('terms') ?>">Terms</a>
          </div>
        </div>
      </div>
      <div class="copyright">
        <span>© <?= date('Y') ?> Talk AI Pilot. All rights reserved.</span>
        <span>Made for growing businesses in India &amp; beyond.</span>
      </div>
    </div>
  </footer>

  <div class="cookie" id="cookieBar" role="dialog" aria-label="Cookie notice">
    <span>We use cookies to improve your experience. See our <a href="<?= site_url('privacy') ?>" style="color:#6ee7b7">Privacy Policy</a>.</span>
    <button class="btn btn-primary" type="button" onclick="document.getElementById('cookieBar').classList.add('hidden');try{localStorage.setItem('tap_cookie','1')}catch(e){}">Accept</button>
  </div>
  <script>try{if(localStorage.getItem('tap_cookie'))document.getElementById('cookieBar').classList.add('hidden')}catch(e){}</script>
</body>
</html>

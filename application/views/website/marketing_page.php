<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($title) ?> — Talk AI Pilot</title>
  <meta name="description" content="<?= htmlspecialchars($lead) ?>">
  <link rel="icon" type="image/svg+xml" href="<?= base_url('assets/images/logo/favicon.svg') ?>">
  <link rel="alternate icon" href="<?= base_url('assets/images/logo/favicon.svg') ?>">
  
  <!-- Preconnect & Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- Stylesheet -->
  <link rel="stylesheet" href="<?= base_url('assets/website/css/landing.css') ?>">
</head>
<body>

  <!-- Top Robotic AI Announcement Banner -->
  <div class="promo">
    <span class="promo-badge"><span class="pulse-dot"></span> Robotic AI v4.2 Active</span>
    <span>Connected to <strong>Official Meta WhatsApp Cloud API</strong> &amp; Omnichannel Social Ingestion</span>
    <a class="promo-link" href="<?= site_url('contact') ?>">Start 14-Day Free Trial →</a>
  </div>

  <!-- Header Navigation -->
  <header class="nav" id="mainNav">
    <div class="wrap nav-inner">
      <a class="brand" href="<?= base_url() ?>" aria-label="Talk AI Pilot Home">
        <img class="brand-logo-img" src="<?= base_url('assets/images/logo/logo.svg') ?>" alt="Talk AI Pilot — Robotic Auto-Reply" height="42">
      </a>

      <!-- Primary Navigation Links Requested by User -->
      <nav class="links nav-links" id="navLinks" aria-label="Primary">
        <a class="<?= empty($active) || $active === 'home' ? 'active' : '' ?>" href="<?= base_url() ?>">Home</a>
        <a class="<?= ($active ?? '') === 'platform' ? 'active' : '' ?>" href="<?= site_url('platform') ?>">Product</a>
        <a class="<?= ($active ?? '') === 'channels' ? 'active' : '' ?>" href="<?= site_url('channels') ?>">Channels</a>
        <a class="<?= ($active ?? '') === 'pricing' ? 'active' : '' ?>" href="<?= site_url('pricing') ?>">Pricing</a>
        <a class="<?= ($active ?? '') === 'about' ? 'active' : '' ?>" href="<?= site_url('about') ?>">About</a>
        <a class="<?= ($active ?? '') === 'services' ? 'active' : '' ?>" href="<?= site_url('services') ?>">Services</a>
        <a class="<?= ($active ?? '') === 'contact' ? 'active' : '' ?>" href="<?= site_url('contact') ?>">Contact</a>
      </nav>

      <div class="nav-actions">
        <a class="btn btn-ghost" href="<?= site_url('admin/vendor/login') ?>">Login</a>
        <a class="btn btn-primary" href="<?= site_url('contact') ?>">
          <span>Launch AI Robot</span>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
        </a>
        <button class="menu-btn" type="button" aria-label="Open Navigation Menu" onclick="document.getElementById('navLinks').classList.toggle('open')">☰</button>
      </div>
    </div>
  </header>

  <main>
    <!-- Subpage Hero Section -->
    <section class="hero" style="padding: 70px 0 80px;">
      <div class="wrap hero-grid">
        <div class="hero-content">
          <div class="hero-badges">
            <span class="pill robot">
              <span class="pulse-dot"></span>
              <?= htmlspecialchars($eyebrow ?? 'Autonomous AI Robot') ?>
            </span>
            <span class="pill meta">Official Meta Partner Ready</span>
          </div>

          <h1><?= htmlspecialchars($title) ?></h1>
          <p class="hero-lead"><?= htmlspecialchars($lead) ?></p>

          <div class="hero-actions">
            <a class="btn btn-primary btn-lg" href="<?= ($active ?? '') === 'contact' ? '#contact-form' : site_url('contact') ?>">
              <span><?= ($active ?? '') === 'contact' ? 'Share Your Requirements ↓' : 'Get Started Free →' ?></span>
            </a>
            <?php if (($active ?? '') !== 'pricing'): ?>
            <a class="btn btn-dark btn-lg" href="<?= site_url('pricing') ?>">
              <span>View Pricing Plans</span>
            </a>
            <?php endif; ?>
          </div>

          <div class="hero-trust">
            <span>✓ Multi-Channel Connected</span>
            <span>✓ Sub-Second Response Latency</span>
            <span>✓ 100% Human Handoff Control</span>
          </div>
        </div>

        <?php if (!empty($hero_img)): ?>
        <div class="hero-ui">
          <div class="feature-visual" style="border-radius:24px;box-shadow:0 25px 60px rgba(15,23,42,0.14);">
            <img src="<?= htmlspecialchars($hero_img) ?>" alt="<?= htmlspecialchars($hero_img_alt ?? $title) ?>" style="aspect-ratio:16/11;object-fit:cover;width:100%;">
            <div class="visual-overlay-card">
              <div>
                <?php if (($active ?? '') === 'channels'): ?>
                  <strong>Talk AI Pilot · Omnichannel Hub</strong>
                  <small>WhatsApp · Instagram · FB · YouTube · Web</small>
                <?php elseif (($active ?? '') === 'solutions'): ?>
                  <strong>Talk AI Pilot · Industry Automation</strong>
                  <small>E-Commerce · Consulting · Travel · SaaS</small>
                <?php elseif (($active ?? '') === 'platform'): ?>
                  <strong>Talk AI Pilot · Cybernetic Core OS</strong>
                  <small>0.4s Sub-Second AI · Catalog &amp; UPI Pay</small>
                <?php else: ?>
                  <strong>Talk AI Pilot · <?= htmlspecialchars($eyebrow ?? 'Robotic Platform') ?></strong>
                  <small>Enterprise Autonomous Conversational OS</small>
                <?php endif; ?>
              </div>
              <span class="badge" style="background:#10b981;">Online &amp; Active</span>
            </div>
          </div>
        </div>
        <?php else: ?>
        <div class="hero-ui">
          <div class="ui-main" style="padding:32px;background:#0d1629;color:#fff;border-color:rgba(255,255,255,0.1);">
            <div style="font-size:12px;font-weight:800;color:#34d399;text-transform:uppercase;letter-spacing:0.08em;margin-bottom:12px;">
              ⚡ Autonomous Workflow Engine
            </div>
            <h3 style="font-size:24px;margin:0 0 14px;color:#fff;font-weight:850;">
              Turn Customer Messages Into Revenue 24/7
            </h3>
            <p style="color:#94a3b8;font-size:14.5px;line-height:1.65;margin:0 0 20px;">
              Deploy intelligent conversational agents that understand customer questions, query inventory, issue payment links, and notify staff with zero latency.
            </p>
            <div style="display:grid;gap:10px;font-size:13px;font-weight:700;">
              <div style="padding:10px 14px;background:rgba(255,255,255,0.06);border-radius:10px;border:1px solid rgba(255,255,255,0.1);display:flex;align-items:center;gap:10px;">
                <span style="color:#34d399;">✓</span> WhatsApp Cloud API &amp; Meta Agent Connectors
              </div>
              <div style="padding:10px 14px;background:rgba(255,255,255,0.06);border-radius:10px;border:1px solid rgba(255,255,255,0.1);display:flex;align-items:center;gap:10px;">
                <span style="color:#34d399;">✓</span> Instagram DMs, Reels Comments &amp; Stories
              </div>
              <div style="padding:10px 14px;background:rgba(255,255,255,0.06);border-radius:10px;border:1px solid rgba(255,255,255,0.1);display:flex;align-items:center;gap:10px;">
                <span style="color:#34d399;">✓</span> In-Chat UPI / Razorpay Payment Links &amp; PDF Invoices
              </div>
            </div>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </section>

    <!-- About Story Section (If about page) -->
    <?php if (!empty($about_story)): ?>
    <section style="background:var(--white);border-top:1px solid var(--line);border-bottom:1px solid var(--line);">
      <div class="wrap">
        <div style="display:grid;grid-template-columns:1fr 1.3fr;gap:60px;align-items:start;">
          <div>
            <span class="kicker">Why Talk AI Pilot Exists</span>
            <h2 style="font-size:clamp(30px,3.8vw,42px);font-weight:900;letter-spacing:-0.035em;margin:12px 0 20px;line-height:1.15;">
              Customer Communication Should Never Feel Broken or Slow.
            </h2>
            <p style="color:var(--muted);font-size:16px;line-height:1.7;">
              We believe every business deserves enterprise-grade conversational AI that drives real commerce without replacing the human touch.
            </p>
          </div>
          <div style="display:grid;gap:18px;font-size:16px;line-height:1.7;color:#334155;">
            <?php foreach ($about_story as $paragraph): ?>
            <p style="margin:0;"><?= htmlspecialchars($paragraph) ?></p>
            <?php endforeach; ?>
          </div>
        </div>

        <?php if (!empty($about_stats)): ?>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:20px;margin-top:54px;">
          <?php foreach ($about_stats as $stat): ?>
          <div style="padding:26px 20px;border-radius:16px;background:var(--soft);border:1px solid var(--line);text-align:center;">
            <strong style="display:block;font-size:38px;font-weight:900;color:#059669;letter-spacing:-0.04em;line-height:1.1;margin-bottom:6px;"><?= htmlspecialchars($stat[0]) ?></strong>
            <span style="font-size:13px;font-weight:750;color:var(--muted);text-transform:uppercase;letter-spacing:0.04em;"><?= htmlspecialchars($stat[1]) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </section>
    <?php endif; ?>

    <!-- Deep Sections Cards -->
    <?php if (!empty($sections)): ?>
    <section style="background:var(--soft);border-top:1px solid var(--line);border-bottom:1px solid var(--line);">
      <div class="wrap">
        <div class="section-head">
          <span class="kicker"><?= !empty($page_variant) && $page_variant === 'services' ? 'Bespoke Services' : (!empty($page_variant) && $page_variant === 'about' ? 'Core Capabilities' : 'Product Breakdown') ?></span>
          <h2>
            <?= !empty($page_variant) && $page_variant === 'services' ? 'Complete Path from Architecture to Live AI Scaling' : (!empty($page_variant) && $page_variant === 'about' ? 'Built with Responsible, Commercial AI Principles' : 'Explore the Platform Modules') ?>
          </h2>
          <p>
            <?= !empty($page_variant) && $page_variant === 'services' ? 'Everything you need to successfully deploy multi-channel conversational automation.' : 'Built as one unified layer combining messaging, catalog intelligence, and commerce.' ?>
          </p>
        </div>

        <div class="grid-3">
          <?php foreach ($sections as $i => $section): ?>
          <article class="card">
            <div style="width:40px;height:40px;border-radius:10px;background:#ecfdf5;border:1px solid rgba(16,185,129,0.3);color:#059669;font-weight:900;font-size:15px;display:grid;place-items:center;margin-bottom:16px;">
              <?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?>
            </div>
            <h3 style="font-size:20px;margin:0 0 10px;color:var(--ink);font-weight:850;"><?= htmlspecialchars($section[0]) ?></h3>
            <p style="margin:0 0 18px;color:var(--muted);font-size:14.5px;line-height:1.6;"><?= htmlspecialchars($section[1]) ?></p>
            
            <ul style="list-style:none;padding:14px 0 0;margin:0;border-top:1px solid var(--line);display:grid;gap:8px;font-size:13px;color:#334155;">
              <?php foreach ($section[2] as $item): ?>
              <li style="display:flex;align-items:flex-start;gap:8px;">
                <span style="color:#10b981;font-weight:900;">✓</span>
                <span><?= htmlspecialchars($item) ?></span>
              </li>
              <?php endforeach; ?>
            </ul>
          </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
    <?php endif; ?>

    <!-- Pricing Grid (If Pricing Page) -->
    <?php if (!empty($pricing)): ?>
    <section class="pricing" id="pricing-plans">
      <div class="wrap">
        <div class="section-head">
          <span class="kicker">Choose Your Plan</span>
          <h2>Clear, High-ROI Plans for Growing Teams</h2>
          <p>All plans include official Meta Cloud API connectivity, in-chat commerce, and human handoff.</p>
        </div>

        <div class="price-grid">
          <?php foreach ($pricing as $i => $plan): ?>
          <article class="price-card <?= $i === 1 ? 'popular' : '' ?>">
            <?php if ($i === 1): ?>
            <span class="price-badge">Most Popular</span>
            <?php endif; ?>

            <div>
              <h3><?= htmlspecialchars($plan[0]) ?></h3>
              <p class="desc"><?= htmlspecialchars($plan[1]) ?></p>
              <div class="amount"><?= htmlspecialchars($plan[2]) ?><small>/month</small></div>
              <ul>
                <?php foreach ($plan[3] as $item): ?>
                <li><?= htmlspecialchars($item) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
            <a class="btn <?= $i === 1 ? 'btn-primary' : ($i === 2 ? 'btn-dark' : 'btn-primary') ?>" href="<?= site_url('contact') ?>">
              <?= $i === 2 ? 'Talk to Enterprise Sales' : 'Get Started Now' ?>
            </a>
          </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
    <?php endif; ?>

    <!-- Service Outcomes Highlight -->
    <?php if (!empty($service_outcomes)): ?>
    <section class="stats-band">
      <div class="wrap">
        <div class="section-head" style="margin-bottom:36px;">
          <span class="kicker" style="background:rgba(255,255,255,0.1);color:#34d399;border-color:rgba(255,255,255,0.2);">Measurable Impact</span>
          <h2 style="color:#fff;">What Changes When You Activate Robotic AI</h2>
        </div>
        <div class="stats-grid" style="grid-template-columns:repeat(3, 1fr);">
          <?php foreach ($service_outcomes as $outcome): ?>
          <div class="stat-card" style="text-align:left;padding:28px 24px;">
            <strong style="font-size:22px;color:#34d399;margin-bottom:8px;"><?= htmlspecialchars($outcome[0]) ?></strong>
            <span style="font-size:14px;color:#cbd5e1;line-height:1.6;display:block;"><?= htmlspecialchars($outcome[1]) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
    <?php endif; ?>

    <!-- 4-Step Process Journey -->
    <?php if (!empty($steps)): ?>
    <section class="how-it-works-section">
      <div class="wrap">
        <div class="section-head">
          <span class="kicker">Execution Framework</span>
          <h2><?= htmlspecialchars($steps_title ?? 'How the Process Works') ?></h2>
        </div>
        <div class="steps-grid">
          <?php foreach ($steps as $i => $step): ?>
          <div class="step-card">
            <div class="step-number"><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></div>
            <h3><?= htmlspecialchars($step[0]) ?></h3>
            <p><?= htmlspecialchars($step[1]) ?></p>
            <span class="step-tag">Phase <?= $i + 1 ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
    <?php endif; ?>

    <!-- Contact Form & Topics (If Contact Page) -->
    <?php if (!empty($contact_form)): ?>
    <section id="contact-form" style="background:var(--white);">
      <div class="wrap" style="display:grid;grid-template-columns:1fr 1.35fr;gap:60px;align-items:start;">
        <div>
          <span class="kicker">Consultation &amp; Setup</span>
          <h2 style="font-size:clamp(30px,3.8vw,42px);font-weight:900;letter-spacing:-0.035em;margin:12px 0 16px;line-height:1.15;">
            Map Your Multi-Channel Journey in Minutes
          </h2>
          <p style="color:var(--muted);font-size:16.5px;line-height:1.7;margin-bottom:28px;">
            Tell us where your customers message and what your team currently handles manually. We’ll show you a working prototype.
          </p>

          <div style="display:grid;gap:14px;margin-bottom:28px;">
            <?php foreach ($contact_topics as $topic): ?>
            <div style="padding:16px;border-radius:14px;background:var(--soft);border:1px solid var(--line);display:flex;gap:14px;align-items:center;">
              <span style="width:36px;height:36px;border-radius:10px;background:#ecfdf5;color:#059669;font-weight:900;display:grid;place-items:center;font-size:13px;flex-shrink:0;">
                <?= htmlspecialchars($topic[0]) ?>
              </span>
              <div>
                <strong style="display:block;font-size:14.5px;color:var(--ink);"><?= htmlspecialchars($topic[1]) ?></strong>
                <small style="color:var(--muted);font-size:12.5px;line-height:1.5;display:block;margin-top:2px;"><?= htmlspecialchars($topic[2]) ?></small>
              </div>
            </div>
            <?php endforeach; ?>
          </div>

          <div style="padding:18px 22px;border-radius:14px;background:#0d1629;color:#fff;display:flex;align-items:center;gap:14px;margin-bottom:14px;">
            <div class="pulse-dot"></div>
            <div style="font-size:13px;">
              <strong>SLA Response Guarantee:</strong> Our solution engineers respond within 2 business hours.
            </div>
          </div>

          <div style="padding:18px 22px;border-radius:14px;background:var(--soft);border:1px solid var(--line);font-size:13px;line-height:1.7;color:var(--muted);">
            <strong style="color:var(--ink);display:block;margin-bottom:4px;">Merchant &amp; Customer Support:</strong>
            <div><strong>Email:</strong> support@talkaipilot.com</div>
            <div><strong>Hours:</strong> Mon – Sat, 9:30 AM – 6:30 PM IST</div>
            <div><strong>Product:</strong> B2B Conversational AI SaaS &amp; Automation Platform</div>
          </div>
        </div>

        <!-- Live Contact Form -->
        <div style="background:var(--soft);border:1px solid var(--line);border-radius:24px;padding:36px;box-shadow:var(--shadow-md);">
          <h3 style="margin:0 0 8px;font-size:22px;font-weight:850;color:var(--ink);">Schedule Architecture Call</h3>
          <p style="margin:0 0 24px;font-size:14px;color:var(--muted);">Fill out your requirements below to test a live demo.</p>

          <form class="js-contact-ajax" method="post" action="<?= site_url('contact/save') ?>" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <?= sk_csrf_field() ?>
            <div class="site-form-ok js-form-msg" data-form-msg hidden style="grid-column:1/-1;padding:14px;border-radius:10px;background:#ecfdf5;color:#065f46;font-size:14px;font-weight:700;"></div>

            <div style="display:grid;gap:6px;">
              <label for="name" style="font-size:12px;font-weight:800;color:#334155;">Full Name *</label>
              <input id="name" name="name" required placeholder="e.g. Rahul Sharma" style="width:100%;padding:12px;border-radius:10px;border:1px solid var(--line);font-size:14px;outline:none;">
            </div>

            <div style="display:grid;gap:6px;">
              <label for="email" style="font-size:12px;font-weight:800;color:#334155;">Business Email *</label>
              <input id="email" type="email" name="email" required placeholder="rahul@company.com" style="width:100%;padding:12px;border-radius:10px;border:1px solid var(--line);font-size:14px;outline:none;">
            </div>

            <div style="display:grid;gap:6px;">
              <label for="phone" style="font-size:12px;font-weight:800;color:#334155;">WhatsApp Phone *</label>
              <input id="phone" name="phone" required placeholder="+91 98765 43210" style="width:100%;padding:12px;border-radius:10px;border:1px solid var(--line);font-size:14px;outline:none;">
            </div>

            <div style="display:grid;gap:6px;">
              <label for="business" style="font-size:12px;font-weight:800;color:#334155;">Company / Store Name</label>
              <input id="business" name="business" placeholder="Brand / E-Store Name" style="width:100%;padding:12px;border-radius:10px;border:1px solid var(--line);font-size:14px;outline:none;">
            </div>

            <div style="grid-column:1/-1;display:grid;gap:6px;">
              <label for="subject" style="font-size:12px;font-weight:800;color:#334155;">What Channels Do You Want to Automate?</label>
              <select id="subject" name="subject" style="width:100%;padding:12px;border-radius:10px;border:1px solid var(--line);font-size:14px;background:#fff;outline:none;">
                <option>WhatsApp Cloud API Automation</option>
                <option>Instagram DMs &amp; Reel Comments</option>
                <option>Multi-Channel Omnichannel Hub (WhatsApp + IG + FB + Web)</option>
                <option>In-Chat E-Commerce &amp; Razorpay UPI Payments</option>
                <option>Custom Enterprise Integration &amp; Multi-Vendor</option>
              </select>
            </div>

            <div style="grid-column:1/-1;display:grid;gap:6px;">
              <label for="message" style="font-size:12px;font-weight:800;color:#334155;">Briefly Describe Your Current Workflow</label>
              <textarea id="message" name="message" required rows="4" placeholder="How many inquiries do you receive daily? Which systems (Shopify, CRM, ERP) do you use?" style="width:100%;padding:12px;border-radius:10px;border:1px solid var(--line);font-size:14px;outline:none;resize:vertical;"></textarea>
            </div>

            <div style="grid-column:1/-1;">
              <button class="btn btn-primary btn-lg" type="submit" style="width:100%;">
                <span>Submit &amp; Request Demo Prototype →</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </section>
    <?= site_contact_form_ajax_script('form.js-contact-ajax') ?>
    <?php endif; ?>

    <!-- Legal Terms / Privacy Documentation -->
    <?php if (!empty($legal_html)): ?>
    <section style="background:var(--soft);">
      <div class="wrap">
        <div style="max-width:920px;margin:auto;padding:44px;border-radius:24px;background:var(--white);border:1px solid var(--line);box-shadow:var(--shadow-sm);line-height:1.8;">
          <?= $legal_html ?>
        </div>
      </div>
    </section>
    <?php endif; ?>

    <!-- Final Call to Action Banner -->
    <section class="cta-section">
      <div class="wrap">
        <div class="cta-box">
          <div>
            <h2>Ready to Connect Your Channels with Robotic AI?</h2>
            <p>
              Join forward-thinking businesses converting chats into orders 24/7 across WhatsApp, Instagram, and Web.
            </p>
          </div>
          <div class="cta-actions">
            <a class="btn btn-white btn-lg" href="<?= site_url('contact') ?>">
              <span>Start Free 14-Day Trial →</span>
            </a>
            <a class="btn btn-outline-white" href="<?= site_url('pricing') ?>">
              <span>Explore Pricing Plans</span>
            </a>
          </div>
        </div>
      </div>
    </section>
  </main>

  <!-- Futuristic SEO Footer -->
  <footer>
    <div class="wrap">
      <div class="footer-grid">
        <div class="footer-brand">
          <a class="brand" href="<?= base_url() ?>" aria-label="Talk AI Pilot Home">
            <img class="brand-logo-img" src="<?= base_url('assets/images/logo/logo-white.svg') ?>" alt="Talk AI Pilot — Robotic Auto-Reply" height="42">
          </a>
          <p>
            Autonomous Robotic AI Auto-Reply &amp; Omnichannel Customer Engagement Platform. Official Meta WhatsApp Cloud API partner architecture for modern commerce.
          </p>
          <div class="footer-status-pill">
            <span class="pulse-dot"></span>
            All AI Systems Operational · 99.99% Uptime
          </div>
        </div>

        <div>
          <div class="footer-title">Channels</div>
          <div class="footer-links">
            <a href="<?= site_url('channels') ?>">WhatsApp Cloud API</a>
            <a href="<?= site_url('channels') ?>">Instagram DM Automation</a>
            <a href="<?= site_url('channels') ?>">Facebook Messenger</a>
            <a href="<?= site_url('channels') ?>">YouTube Lead Ingestion</a>
            <a href="<?= site_url('channels') ?>">Website Live AI Chat</a>
            <a href="<?= site_url('channels') ?>">Mobile App SDK</a>
          </div>
        </div>

        <div>
          <div class="footer-title">Platform</div>
          <div class="footer-links">
            <a href="<?= site_url('platform') ?>">Autonomous Auto-Reply</a>
            <a href="<?= site_url('platform') ?>">In-Chat Commerce &amp; UPI</a>
            <a href="<?= site_url('platform') ?>">Automated PDF Invoices</a>
            <a href="<?= site_url('platform') ?>">Visual Flow Builder</a>
            <a href="<?= site_url('platform') ?>">Unified Team Inbox</a>
            <a href="<?= site_url('platform') ?>">Customer 360 CRM</a>
          </div>
        </div>

        <div>
          <div class="footer-title">Solutions</div>
          <div class="footer-links">
            <a href="<?= site_url('solutions') ?>">E-Commerce &amp; D2C</a>
            <a href="<?= site_url('solutions') ?>">Consulting &amp; Services</a>
            <a href="<?= site_url('solutions') ?>">Travel &amp; Hospitality</a>
            <a href="<?= site_url('solutions') ?>">Coaching &amp; Education</a>
            <a href="<?= site_url('solutions') ?>">Local Retail &amp; Stores</a>
            <a href="<?= site_url('solutions') ?>">Multi-Branch Enterprise</a>
          </div>
        </div>

        <div>
          <div class="footer-title">Company</div>
          <div class="footer-links">
            <a href="<?= site_url('about') ?>">About Us</a>
            <a href="<?= site_url('services') ?>">Implementation Services</a>
            <a href="<?= site_url('pricing') ?>">Pricing Plans</a>
            <a href="<?= site_url('contact') ?>">Contact Team</a>
            <a href="<?= site_url('admin/vendor/login') ?>">Admin &amp; Vendor Login</a>
            <a href="<?= site_url('privacy') ?>">Privacy Policy</a>
            <a href="<?= site_url('terms') ?>">Terms &amp; Conditions</a>
            <a href="<?= site_url('refund-policy') ?>">Cancellation &amp; Refund Policy</a>
          </div>
        </div>
      </div>

      <div class="footer-compliance-row">
        <div class="compliance-badges">
          <span class="comp-badge">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.477 2 2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.879V14.89h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.989C18.343 21.129 22 16.99 22 12c0-5.523-4.477-10-10-10z"/></svg>
            Official Meta Cloud API
          </span>
          <span class="comp-badge">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
            256-Bit SSL Encrypted
          </span>
          <span class="comp-badge">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
            ISO 27001 Aligned
          </span>
        </div>
        <div>
          Designed for high-growth businesses across India, Southeast Asia &amp; Global.
        </div>
      </div>

      <div class="copyright">
        <span>© <?= date('Y') ?> Talk AI Pilot Technologies. All rights reserved.</span>
        <span>Robotic AI Omnichannel Auto-Reply Engine v4.2.0</span>
      </div>
    </div>
  </footer>

</body>
</html>

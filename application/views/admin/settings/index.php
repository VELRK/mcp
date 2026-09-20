<div class="sk-page-header">
  <h5 class="sk-page-title"><i class="bi bi-gear me-2 text-warning"></i>Settings</h5>
</div>

<form action="<?= site_url('admin/settings/update') ?>" method="POST" enctype="multipart/form-data">
  <input type="hidden" name="settings_tab" id="settingsTabInput" value="general">

  <!-- Contact fields first so phone/email always POST (large settings form can drop late fields). -->
  <div class="card sk-table-card shadow-sm mb-3 border-warning">
    <div class="card-body py-3">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <h6 class="mb-0"><i class="bi bi-telephone me-1 text-warning"></i> Invoice phone / email / address</h6>
        <span class="text-muted small">Saved with Settings — used on every invoice</span>
      </div>
      <div class="row g-2">
        <div class="col-md-4">
          <label class="form-label mb-1">Phone</label>
          <input type="text" name="site_phone" id="site_phone" class="form-control" value="<?= htmlspecialchars($settings['site_phone'] ?? '') ?>" placeholder="03-6242 2232" autocomplete="tel">
        </div>
        <div class="col-md-4">
          <label class="form-label mb-1">Email</label>
          <input type="text" name="site_email" id="site_email" class="form-control" value="<?= htmlspecialchars($settings['site_email'] ?? '') ?>" placeholder="golden2deal@gmail.com" autocomplete="email">
        </div>
        <div class="col-md-4">
          <label class="form-label mb-1">Address</label>
          <textarea name="site_address" id="site_address" class="form-control" rows="1" placeholder="Company address"><?= htmlspecialchars($settings['site_address'] ?? '') ?></textarea>
        </div>
      </div>
    </div>
  </div>

  <!-- Nav Tabs -->
  <ul class="nav nav-tabs mb-3" id="settingsTabs">
    <li class="nav-item"><button type="button" class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-general">General</button></li>
    <?php if (empty($vendor_logged_in)): ?>
    <li class="nav-item"><button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-whatsapp"><i class="bi bi-whatsapp me-1"></i>Order WhatsApp</button></li>
    <li class="nav-item"><button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-wacloud"><i class="bi bi-cloud me-1"></i>WhatsApp Cloud</button></li>
    <li class="nav-item"><button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-invoice">Invoice</button></li>
    <li class="nav-item"><button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-payment">Payment</button></li>
    <li class="nav-item"><button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-email">Email</button></li>
    <li class="nav-item"><button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-sms">SMS OTP (iSMS)</button></li>
    <li class="nav-item"><button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-sql"><i class="bi bi-database me-1"></i>SQL</button></li>
    <li class="nav-item"><button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-seo">SEO</button></li>
    <?php endif; ?>
  </ul>

  <div class="tab-content">

    <!-- General -->
    <div class="tab-pane fade show active" id="tab-general">
      <div class="card sk-table-card shadow-sm">
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Site Name</label>
              <input type="text" name="site_name" class="form-control" value="<?= htmlspecialchars($settings['site_name'] ?? '2DEAL') ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Currency Symbol</label>
              <input type="text" name="currency_symbol" class="form-control" value="<?= htmlspecialchars(sk_currency_symbol($settings)) ?>">
              <div class="form-text">One store currency. Used on prices, invoices, and Razorpay.</div>
            </div>
            <div class="col-md-3">
              <label class="form-label">Currency Code</label>
              <input type="text" name="currency_code" class="form-control" value="<?= htmlspecialchars(sk_currency_code($settings)) ?>" maxlength="3">
              <div class="form-text">ISO 4217 from this setting only (e.g. INR). No other picker.</div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Tax Rate (%)</label>
              <input type="number" name="tax_rate" class="form-control" step="0.01" value="<?= $settings['tax_rate'] ?? '18' ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Shipping Charge (<?= htmlspecialchars(sk_currency_symbol($settings)) ?>)</label>
              <input type="number" name="shipping_charge" class="form-control" value="<?= $settings['shipping_charge'] ?? '50' ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Free Shipping Above (<?= htmlspecialchars(sk_currency_symbol($settings)) ?>)</label>
              <input type="number" name="free_shipping_above" class="form-control" value="<?= $settings['free_shipping_above'] ?? '999' ?>">
            </div>
            <div class="col-12">
              <div class="alert alert-secondary small mb-0 py-2">
                Phone, email and address are edited in the yellow box above this form (always visible).
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Site Logo</label>
              <?php if (!empty($settings['site_logo'])): ?>
                <img src="<?= base_url($settings['site_logo']) ?>" height="40" class="d-block mb-2 rounded">
              <?php endif; ?>
              <input type="file" name="site_logo" class="form-control" accept="image/*">
            </div>
            <div class="col-12">
              <label class="form-label fw-medium">Homepage Popups</label>
              <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" name="newsletter_popup_enabled" id="newsletterPopupToggle" value="1"
                  <?= (!isset($settings['newsletter_popup_enabled']) || $settings['newsletter_popup_enabled'] == '1') ? 'checked' : '' ?>>
                <label class="form-check-label" for="newsletterPopupToggle">
                  Show newsletter subscribe popup on homepage
                </label>
              </div>
            </div>

            <div class="col-12">
              <label class="form-label fw-medium">Top Announcement Bar</label>
              <div class="card bg-light border-0 shadow-none">
                <div class="card-body">
                  <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" name="top_bar_enabled" id="topBarToggle" value="1"
                      <?= (!isset($settings['top_bar_enabled']) || $settings['top_bar_enabled'] == '1') ? 'checked' : '' ?>>
                    <label class="form-check-label" for="topBarToggle">Show Top Bar</label>
                  </div>
                  <input type="text" name="top_bar_text" class="form-control form-control-sm" 
                    value="<?= htmlspecialchars($settings['top_bar_text'] ?? '20% Off – Auto Applied at Checkout – Limited Time Only') ?>"
                    placeholder="Enter announcement text...">
                </div>
              </div>
            </div>

            <div class="col-12">
              <label class="form-label fw-medium">WhatsApp Support</label>
              <div class="card bg-light border-0 shadow-none">
                <div class="card-body">
                  <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" name="whatsapp_enabled" id="waToggle" value="1"
                      <?= (!isset($settings['whatsapp_enabled']) || $settings['whatsapp_enabled'] == '1') ? 'checked' : '' ?>>
                    <label class="form-check-label" for="waToggle">Enable Floating WhatsApp Button</label>
                  </div>
                  <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-whatsapp"></i></span>
                    <input type="text" name="whatsapp_number" class="form-control" 
                      value="<?= htmlspecialchars($settings['whatsapp_number'] ?? '') ?>"
                      placeholder="e.g. 919876543210">
                  </div>
                  <div class="form-text small text-muted">Malaysia country code without + (default 60). Order status alerts are under the <strong>Order WhatsApp</strong> tab.</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Order WhatsApp (Syncr) -->
    <?php if (empty($vendor_logged_in)): ?>
    <div class="tab-pane fade" id="tab-whatsapp">
      <div class="card sk-table-card shadow-sm">
        <div class="card-body">
          <h6 class="mb-1"><i class="bi bi-whatsapp text-success me-1"></i>Order WhatsApp (Syncr)</h6>
          <p class="text-muted small mb-3">Send WhatsApp messages to customers when order status changes (pending, confirmed, processing, shipped, delivered, cancelled). Uses Syncr <code>waadmin.syncr.in</code> for all vendors.</p>
          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" name="askeva_whatsapp_enabled" id="askevaToggle" value="1"
              <?= (!isset($settings['askeva_whatsapp_enabled']) || $settings['askeva_whatsapp_enabled'] == '1') ? 'checked' : '' ?>>
            <label class="form-check-label" for="askevaToggle">Send WhatsApp on every order status change</label>
          </div>
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label">API URL</label>
              <input type="text" name="askeva_api_url" class="form-control"
                value="<?= htmlspecialchars($settings['askeva_api_url'] ?? 'https://waadmin.syncr.in/v1/message/send-message') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Template language</label>
              <input type="text" name="askeva_template_lang" class="form-control"
                value="<?= htmlspecialchars($settings['askeva_template_lang'] ?? 'en') ?>">
            </div>
            <div class="col-12">
              <label class="form-label">API Token</label>
              <input type="text" name="askeva_api_token" class="form-control font-monospace" autocomplete="off"
                value="" placeholder="<?= !empty($settings['askeva_api_token']) ? '•••• saved (leave blank to keep)' : 'Paste Syncr API token' ?>">
              <?php if (!empty($settings['askeva_api_token'])): ?>
                <div class="form-text text-success">
                  Saved in DB (<?= strlen((string)$settings['askeva_api_token']) ?> chars, ends …<?= htmlspecialchars(substr((string)$settings['askeva_api_token'], -6)) ?>).
                </div>
              <?php else: ?>
                <div class="form-text text-warning">No token in database yet — paste and Save Settings.</div>
              <?php endif; ?>
            </div>
            <div class="col-12">
              <label class="form-label">Fallback utility template name (optional)</label>
              <input type="text" name="askeva_order_template" class="form-control"
                value="<?= htmlspecialchars($settings['askeva_order_template'] ?? '') ?>"
                placeholder="order_status_update">
              <div class="form-text">
                Per-status templates are configured in code (<code>application/config/whatsapp.php</code>).
                Create them from <code>database/whatsapp_order_templates.txt</code>
                ({{1}}=name, {{2}}=order no). This field is only an optional fallback.
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Meta WhatsApp Cloud API -->
    <div class="tab-pane fade" id="tab-wacloud">
      <div class="card sk-table-card shadow-sm">
        <div class="card-body">
          <h6 class="mb-1"><i class="bi bi-whatsapp text-success me-1"></i>WhatsApp Cloud API (Meta)</h6>
          <p class="text-muted small mb-3">
            Direct Graph API for inbox chat and template CRUD.
            Register the webhook in Meta Developer → WhatsApp → Configuration.
          </p>
          <div class="alert alert-info small">
            Webhook URL:
            <code class="user-select-all"><?= site_url('shopkart-api/whatsapp/webhook') ?></code>
            · Callback fields: <code>messages</code><br>
            Redirect URI:
            <code class="user-select-all"><?= site_url('admin/meta/callback') ?></code>
            · <a href="<?= site_url('admin/meta') ?>">Open Facebook login</a>
          </div>
          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" name="wa_cloud_enabled" value="1" id="waCloudOn"
              <?= !empty($settings['wa_cloud_enabled']) && $settings['wa_cloud_enabled'] !== '0' ? 'checked' : '' ?>>
            <label class="form-check-label" for="waCloudOn">Enable Meta Cloud inbox / templates</label>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Phone Number ID</label>
              <input type="text" name="wa_cloud_phone_number_id" class="form-control font-monospace"
                     value="<?= htmlspecialchars($settings['wa_cloud_phone_number_id'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">WhatsApp Business Account ID (WABA)</label>
              <input type="text" name="wa_cloud_waba_id" class="form-control font-monospace"
                     value="<?= htmlspecialchars($settings['wa_cloud_waba_id'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Facebook App ID</label>
              <input type="text" name="wa_cloud_app_id" class="form-control font-monospace"
                     value="<?= htmlspecialchars($settings['wa_cloud_app_id'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Embedded Signup config ID (optional)</label>
              <input type="text" name="wa_cloud_config_id" class="form-control font-monospace"
                     value="<?= htmlspecialchars($settings['wa_cloud_config_id'] ?? '') ?>">
            </div>
            <div class="col-12">
              <label class="form-label">Permanent access token</label>
              <input type="password" name="wa_cloud_access_token" class="form-control font-monospace" autocomplete="new-password"
                     value="" placeholder="<?= !empty($settings['wa_cloud_access_token']) ? '•••• saved (leave blank to keep)' : 'Paste Meta system-user token' ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">App secret</label>
              <input type="password" name="wa_cloud_app_secret" class="form-control font-monospace" autocomplete="new-password"
                     value="" placeholder="<?= !empty($settings['wa_cloud_app_secret']) ? '•••• saved (leave blank to keep)' : 'Webhook signature' ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Verify token</label>
              <input type="text" name="wa_cloud_verify_token" class="form-control"
                     value="<?= htmlspecialchars($settings['wa_cloud_verify_token'] ?? 'Velmurugn0071@!!!') ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Graph API version</label>
              <input type="text" name="wa_cloud_api_version" class="form-control"
                     value="<?= htmlspecialchars($settings['wa_cloud_api_version'] ?? 'v21.0') ?>">
            </div>
          </div>

          <hr class="my-4">
          <h6 class="mb-1">MCP bot replies</h6>
          <p class="text-muted small mb-3">
            Incoming WhatsApp messages are posted to your MCP URL. The JSON reply is converted to WhatsApp
            <strong>text</strong>, <strong>reply buttons</strong>, <strong>list menu</strong>, or <strong>link button</strong> and sent back.
            MCP can also push a reply to
            <code class="user-select-all"><?= site_url('shopkart-api/whatsapp/mcp') ?></code>
          </p>
          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" name="wa_mcp_enabled" value="1" id="waMcpOn"
              <?= !empty($settings['wa_mcp_enabled']) && $settings['wa_mcp_enabled'] !== '0' ? 'checked' : '' ?>>
            <label class="form-check-label" for="waMcpOn">Call MCP on inbound WhatsApp messages</label>
          </div>
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">MCP webhook URL</label>
              <input type="url" name="wa_mcp_url" class="form-control font-monospace"
                     value="<?= htmlspecialchars($settings['wa_mcp_url'] ?? '') ?>"
                     placeholder="https://your-mcp-host/whatsapp">
            </div>
            <div class="col-md-8">
              <label class="form-label">MCP token (optional)</label>
              <input type="password" name="wa_mcp_token" class="form-control font-monospace" autocomplete="new-password"
                     value="" placeholder="<?= !empty($settings['wa_mcp_token']) ? '•••• saved (leave blank to keep)' : 'Bearer / X-MCP-Token' ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Timeout (seconds)</label>
              <input type="number" name="wa_mcp_timeout" class="form-control" min="5" max="30"
                     value="<?= htmlspecialchars($settings['wa_mcp_timeout'] ?? '12') ?>">
            </div>
          </div>
          <pre class="small bg-light border rounded p-2 mt-3 mb-0" style="white-space:pre-wrap">MCP reply examples:
{"type":"text","text":"Hello"}
{"type":"buttons","body":"How can we help?","buttons":[{"id":"track","title":"Track order"},{"id":"shop","title":"Shop"}]}
{"type":"list","body":"Pick a category","button":"Categories","sections":[{"title":"Shop","rows":[{"id":"silk","title":"Silk Sarees","description":"Handwoven"}]}]}
{"type":"cta","body":"Open the store","display_text":"Visit 2DEAL","url":"https://2deal.my/"}
{"messages":[ ...several of the above... ]}</pre>
          <div class="mt-3">
            <a href="<?= site_url('shopkart/whatsapp') ?>" class="btn btn-sm btn-success">Open inbox</a>
            <a href="<?= site_url('shopkart/whatsapp/templates') ?>" class="btn btn-sm btn-outline-success">Templates</a>
          </div>

          <hr class="my-4">
          <h6 class="mb-1">SaaS billing</h6>
          <p class="text-muted small mb-3">
            AI usage logging and daily bill rollup.
            Log API: <code class="user-select-all"><?= site_url('shopkart-api/saas/ai-requests') ?></code>
            · Cron: <code>php index.php cron saas_daily_bills</code>
          </p>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">SaaS billing token</label>
              <input type="password" name="saas_billing_token" class="form-control font-monospace" autocomplete="new-password"
                     value="" placeholder="<?= !empty($settings['saas_billing_token']) ? '•••• saved (leave blank to keep)' : 'Bearer / X-SaaS-Token (or use MCP token)' ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Default vendor ID</label>
              <input type="number" name="saas_default_vendor_id" class="form-control" min="1"
                     value="<?= htmlspecialchars($settings['saas_default_vendor_id'] ?? '') ?>"
                     placeholder="For WA sync / API fallback">
            </div>
            <div class="col-md-3">
              <label class="form-label">Cron HTTP key</label>
              <input type="password" name="saas_cron_key" class="form-control font-monospace" autocomplete="new-password"
                     value="" placeholder="<?= !empty($settings['saas_cron_key']) ? '•••• saved (leave blank to keep)' : 'Optional ?key= for HTTP cron' ?>">
            </div>
          </div>
          <div class="mt-3">
            <a href="<?= site_url('admin/saas-billing') ?>" class="btn btn-sm btn-warning">Open SaaS Billing</a>
            <a href="<?= site_url('admin/saas-billing/amounts') ?>" class="btn btn-sm btn-outline-secondary">Client Amounts</a>
          </div>
        </div>
      </div>
    </div>

    <!-- Invoice (platform default) -->
    <div class="tab-pane fade" id="tab-invoice">
      <div class="card sk-table-card shadow-sm">
        <div class="card-body">
          <p class="text-muted small mb-3">Company legal name and tax IDs for invoices. Phone / email / address are in the yellow box at the top of this page.</p>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Legal / Company Name</label>
              <input type="text" name="company_legal_name" class="form-control" value="<?= htmlspecialchars($settings['company_legal_name'] ?? $settings['site_name'] ?? '') ?>" placeholder="GOLDEN 2 DEAL (M) SDN. BHD.">
            </div>
            <div class="col-md-6">
              <label class="form-label">Tax ID</label>
              <input type="text" name="gstin" class="form-control" value="<?= htmlspecialchars($settings['gstin'] ?? '') ?>" placeholder="202101029427">
            </div>
            <div class="col-md-4">
              <label class="form-label">Company No. (SSM)</label>
              <input type="text" name="pan_no" class="form-control" value="<?= htmlspecialchars($settings['pan_no'] ?? '') ?>" placeholder="1429727-A">
            </div>
            <div class="col-md-4">
              <label class="form-label">State Code</label>
              <input type="text" name="state_code" class="form-control" value="<?= htmlspecialchars($settings['state_code'] ?? '') ?>" placeholder="e.g. WP">
            </div>
            <div class="col-md-4">
              <label class="form-label">Invoice Prefix</label>
              <input type="text" name="invoice_prefix" class="form-control" value="<?= htmlspecialchars($settings['invoice_prefix'] ?? 'INV') ?>" maxlength="20">
            </div>
            <div class="col-12">
              <label class="form-label">Invoice Footer Note</label>
              <textarea name="invoice_footer" class="form-control" rows="2" placeholder="Thank you for your business."><?= htmlspecialchars($settings['invoice_footer'] ?? '') ?></textarea>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Payment -->
    <div class="tab-pane fade" id="tab-payment">
      <div class="card sk-table-card shadow-sm">
        <div class="card-body">
          <div class="alert alert-info small">
            <i class="bi bi-info-circle me-1"></i>
            Checkout uses the <strong>Razorpay</strong> Orders API
            (<a href="https://razorpay.com/docs/api/orders/" target="_blank">api.razorpay.com/v1/orders</a>)
            in <strong><?= htmlspecialchars(sk_currency_code($settings)) ?></strong> from Settings → General.
            Get keys from the
            <a href="https://dashboard.razorpay.com/app/website-app-settings/api-keys" target="_blank">Razorpay Dashboard</a>
            (<code>rzp_test_...</code> / <code>rzp_live_...</code>). Prefill needs a valid Indian mobile.
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Razorpay Key ID</label>
              <input type="text" name="razorpay_key_id" class="form-control font-monospace"
                     value="<?= htmlspecialchars($settings['razorpay_key_id'] ?? '') ?>" placeholder="rzp_test_...">
            </div>
            <div class="col-md-6">
              <label class="form-label">Razorpay Key Secret</label>
              <input type="password" name="razorpay_key_secret" class="form-control font-monospace"
                     value="<?= htmlspecialchars($settings['razorpay_key_secret'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Razorpay Webhook Secret</label>
              <input type="password" name="razorpay_webhook_secret" class="form-control font-monospace"
                     value="<?= htmlspecialchars($settings['razorpay_webhook_secret'] ?? '') ?>"
                     placeholder="whsec_...">
              <div class="form-text">
                Webhook URL (Razorpay Dashboard → Webhooks, events: <code>payment.captured</code>, <code>payment.authorized</code>, <code>order.paid</code>):
                <br><code class="user-select-all"><?= site_url('shopkart-api/payment/razorpay-webhook') ?></code>
                <br>Optional return URL (Standard Checkout uses the in-page handler; this is a fallback):
                <br><code class="user-select-all"><?= site_url('shopkart-api/payment/razorpay-return') ?></code>
              </div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Mode</label>
              <select name="razorpay_mode" class="form-select">
                <option value="test" <?= ($settings['razorpay_mode']??'test')==='test'?'selected':'' ?>>Test</option>
                <option value="live" <?= ($settings['razorpay_mode']??'')==='live'?'selected':'' ?>>Live</option>
              </select>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Email -->
    <div class="tab-pane fade" id="tab-email">
      <div class="card sk-table-card shadow-sm">
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">SMTP Host</label>
              <input type="text" name="smtp_host" class="form-control" value="<?= htmlspecialchars($settings['smtp_host'] ?? '') ?>" placeholder="smtp.gmail.com">
            </div>
            <div class="col-md-3">
              <label class="form-label">SMTP Port</label>
              <input type="number" name="smtp_port" class="form-control" value="<?= $settings['smtp_port'] ?? '587' ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">From Name</label>
              <input type="text" name="smtp_from_name" class="form-control" value="<?= htmlspecialchars($settings['smtp_from_name'] ?? '2DEAL') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">SMTP Username</label>
              <input type="text" name="smtp_user" class="form-control" value="<?= htmlspecialchars($settings['smtp_user'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">SMTP Password</label>
              <input type="password" name="smtp_pass" class="form-control" value="<?= htmlspecialchars($settings['smtp_pass'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Admin Email</label>
              <input type="email" name="admin_email" class="form-control" value="<?= htmlspecialchars($settings['admin_email'] ?? '') ?>" placeholder="admin@yourdomain.com">
              <div class="form-text">Every customer mail also notifies this inbox with a separate admin summary.</div>
            </div>
            <div class="col-12">
              <div class="alert alert-info small mb-0">
                Use your mailbox SMTP (e.g. Hostinger: <code>smtp.hostinger.com</code>, port <code>465</code> SSL or <code>587</code> TLS).
                <strong>Site Email</strong> (General tab) must match the mailbox address you send from.
                Set <strong>Admin Email</strong> to receive admin digests for orders, invoices, status, contact, etc.
              </div>
            </div>
            <div class="col-12">
              <button type="submit" formaction="<?= site_url('admin/settings/test_smtp') ?>" formmethod="post" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-envelope-check me-1"></i> Send SMTP test email
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- iSMS Malaysia OTP -->
    <div class="tab-pane fade" id="tab-sms">
      <div class="card sk-table-card shadow-sm">
        <div class="card-body">
          <div class="alert alert-info small">
            <i class="bi bi-phone me-1"></i>
            Login OTP via <a href="https://www.isms.com.my/" target="_blank" rel="noopener">iSMS Malaysia</a>.
            Use sub-account <strong>Username</strong> <code>2DEAL1</code> (or main portal username — not your email) plus either your
            <strong>portal login password</strong> or the <strong>API Key</strong> from your iSMS account page (both are sent as <code>pwd</code> to iSMS).
            If the portal lets you sign in with <em>email</em>, open your iSMS profile and copy the <strong>Username</strong> field for API use.
            Buy SMS credits in the iSMS portal before sending OTPs.
            <br><span class="text-muted">Test phone <code>0180000000</code> always uses OTP <code>1234</code> without sending SMS.</span>
          </div>
          <div class="row g-3">
            <div class="col-md-4">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="isms_enabled" value="1" id="ismsEnabled"
                  <?= !empty($settings['isms_enabled']) && $settings['isms_enabled'] !== '0' ? 'checked' : '' ?>>
                <label class="form-check-label" for="ismsEnabled">Enable iSMS OTP (production)</label>
              </div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Country code</label>
              <input type="text" name="isms_country_code" class="form-control"
                     value="<?= htmlspecialchars($settings['isms_country_code'] ?? '60') ?>" placeholder="60">
            </div>
            <div class="col-md-4">
              <label class="form-label">OTP expiry (minutes)</label>
              <input type="number" name="isms_otp_interval" class="form-control" min="1" max="30"
                     value="<?= htmlspecialchars($settings['isms_otp_interval'] ?? '5') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">iSMS Username</label>
              <input type="text" name="isms_username" class="form-control font-monospace"
                     value="<?= htmlspecialchars($settings['isms_username'] ?? '') ?>" autocomplete="off"
                     placeholder="Sub-account username e.g. 2DEAL1">
              <?php if (!empty($settings['isms_username'])): ?>
                <div class="form-text">Saved: <?= htmlspecialchars(sk_isms_mask_username($settings['isms_username'])) ?> (<?= strlen($settings['isms_username']) ?> chars)</div>
              <?php endif; ?>
            </div>
            <div class="col-md-6">
              <label class="form-label">iSMS Password <span class="text-muted">(portal login)</span></label>
              <input type="password" name="isms_password" class="form-control font-monospace"
                     value="" placeholder="<?= !empty($settings['isms_password']) ? 'Saved — enter only to change' : 'Portal login password' ?>"
                     autocomplete="new-password">
              <?php if (!empty($settings['isms_password'])): ?>
                <div class="form-text text-success">Portal password saved. Leave blank when saving other settings.</div>
              <?php endif; ?>
            </div>
            <div class="col-md-6">
              <label class="form-label">iSMS API Key</label>
              <input type="password" name="isms_api_key" class="form-control font-monospace"
                     value="" placeholder="<?= !empty($settings['isms_api_key']) ? 'Saved — enter only to change' : 'API Key from iSMS account page' ?>"
                     autocomplete="new-password">
              <?php if (!empty($settings['isms_api_key'])): ?>
                <div class="form-text text-success">API key saved. Used as <code>pwd</code> if portal password is empty or fails.</div>
              <?php else: ?>
                <div class="form-text">From iSMS account → API Key. Either password or API key is required.</div>
              <?php endif; ?>
            </div>
            <div class="col-md-6">
              <label class="form-label">Sender ID <span class="text-muted">(optional)</span></label>
              <input type="text" name="isms_sender_id" class="form-control font-monospace" maxlength="11"
                     value="<?= htmlspecialchars($settings['isms_sender_id'] ?? 'GOLDEN2DEAL') ?>" placeholder="GOLDEN2DEAL">
              <div class="form-text">Alphanumeric 3–11 chars. Malaysia shortcode may not show custom sender ID.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">SMS message template</label>
              <input type="text" name="isms_message" class="form-control"
                     value="<?= htmlspecialchars($settings['isms_message'] ?? 'Your OTP is %OTP%. Valid for 5 minutes.') ?>">
              <div class="form-text">Must include <code>%OTP%</code> — replaced with a new random 4-digit code each request (e.g. <em>Your OTP is 4839. Valid for 5 minutes.</em>).</div>
            </div>
            <div class="col-12"><hr class="my-1"><h6 class="text-muted mb-0">Developer testing</h6></div>
            <div class="col-12">
              <div class="alert alert-secondary small mb-0 py-2">
                <strong>Default dev login:</strong> mobile <code>0180000000</code> (or <code>60180000000</code>) + OTP <code>1234</code>.
                Works without iSMS credits. This number never sends real SMS even when iSMS is enabled.
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Test OTP code</label>
              <input type="text" name="isms_test_otp" class="form-control font-monospace" maxlength="4"
                     value="<?= htmlspecialchars($settings['isms_test_otp'] ?? '1234') ?>">
              <div class="form-text">Used for test phone and when iSMS is disabled.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Test mobile number</label>
              <input type="text" name="isms_test_phone" class="form-control font-monospace"
                     value="<?= htmlspecialchars($settings['isms_test_phone'] ?? '60180000000') ?>" placeholder="60180000000">
              <div class="form-text">Always uses test OTP above — no SMS sent for this number.</div>
            </div>
            <div class="col-12">
              <button type="submit" formaction="<?= site_url('admin/settings/save_isms') ?>" formmethod="post" class="btn btn-warning btn-sm">
                <i class="bi bi-shield-lock me-1"></i>Save iSMS credentials
              </button>
              <button type="submit" formaction="<?= site_url('admin/settings/test_isms') ?>" formmethod="post" class="btn btn-outline-primary btn-sm ms-2">
                <i class="bi bi-plug me-1"></i>Test iSMS connection
              </button>
              <span class="text-muted small ms-2">Save credentials first, then test balance (~4,921 credits expected).</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- SQL -->
    <div class="tab-pane fade" id="tab-sql">
      <div class="card sk-table-card shadow-sm">
        <div class="card-body">
          <div class="alert alert-warning small mb-3">
            <i class="bi bi-shield-lock me-1"></i>
            Read-only queries only. The runner allows <strong>SELECT</strong>, <strong>SHOW</strong>, <strong>DESCRIBE</strong>/<strong>DESC</strong>, and <strong>EXPLAIN</strong>.
            No updates, deletes, or multi-statement execution are permitted.
          </div>
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">MySQL query</label>
              <textarea name="sql_query" class="form-control font-monospace" rows="8" placeholder="SELECT * FROM whatsapp_logs ORDER BY id DESC LIMIT 20;"><?= htmlspecialchars($sql_query ?? '') ?></textarea>
              <div class="form-text">Example: <code>SELECT * FROM whatsapp_logs ORDER BY id DESC LIMIT 20;</code></div>
            </div>
            <div class="col-12">
              <button type="button" id="runSqlBtn" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-play-circle me-1"></i> Run SQL
              </button>
            </div>
          </div>

          <div id="sqlResultBox" class="mt-3">
            <?php if (!empty($sql_error)): ?>
              <div class="alert alert-danger small mb-0">
                <?= htmlspecialchars($sql_error) ?>
              </div>
            <?php elseif (!empty($sql_result)): ?>
              <div>
                <h6 class="mb-2">Query result</h6>
                <div class="table-responsive">
                  <table class="table table-sm table-bordered align-middle mb-0">
                    <thead class="table-light">
                      <tr>
                        <?php $sqlColumns = array_keys((array) reset($sql_result)); ?>
                        <?php foreach ($sqlColumns as $column): ?>
                          <th><?= htmlspecialchars((string) $column) ?></th>
                        <?php endforeach; ?>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($sql_result as $row): ?>
                        <tr>
                          <?php foreach ($row as $value): ?>
                            <td><?= htmlspecialchars((string) ($value ?? '')) ?></td>
                          <?php endforeach; ?>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- SEO -->
    <div class="tab-pane fade" id="tab-seo">
      <div class="card sk-table-card shadow-sm">
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Default Meta Title</label>
              <input type="text" name="meta_title" class="form-control" value="<?= htmlspecialchars($settings['meta_title'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Default OG Image URL</label>
              <input type="text" name="seo_og_image" class="form-control" value="<?= htmlspecialchars($settings['seo_og_image'] ?? '') ?>">
            </div>
            <div class="col-12">
              <label class="form-label">Default Meta Description</label>
              <textarea name="meta_desc" class="form-control" rows="3"><?= htmlspecialchars($settings['meta_desc'] ?? '') ?></textarea>
            </div>
            <div class="col-12">
              <label class="form-label">Meta Keywords</label>
              <input type="text" name="meta_keywords" class="form-control" value="<?= htmlspecialchars($settings['meta_keywords'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Google Analytics ID</label>
              <input type="text" name="google_analytics" class="form-control" value="<?= htmlspecialchars($settings['google_analytics'] ?? '') ?>" placeholder="G-XXXXXXXXXX">
            </div>
            <div class="col-md-6">
              <label class="form-label">Upload Default OG Image</label>
              <input type="file" name="seo_og_image_file" class="form-control" accept="image/*">
            </div>
            <div class="col-md-6">
              <label class="form-label">Head Scripts</label>
              <textarea name="head_scripts" class="form-control font-monospace small" rows="4"><?= htmlspecialchars($settings['head_scripts'] ?? '') ?></textarea>
              <div class="form-text">GTM, Facebook Pixel, etc. Injected in &lt;head&gt; on all pages.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Footer Scripts</label>
              <textarea name="footer_scripts" class="form-control font-monospace small" rows="4"><?= htmlspecialchars($settings['footer_scripts'] ?? '') ?></textarea>
            </div>
          </div>
          <div class="alert alert-info small mt-3 mb-0">
            <i class="bi bi-info-circle me-1"></i>
            Manage per-page SEO in <a href="<?= site_url('admin/seo') ?>">SEO Manager</a>.
          </div>
        </div>
      </div>
    </div>

    <?php endif; ?>
  </div><!-- end tab-content -->

  <div class="mt-3">
    <button type="submit" class="btn btn-warning fw-semibold px-4">
      <i class="bi bi-check-lg me-1"></i> Save Settings
    </button>
  </div>

</form>

<script>
(function () {
  var tabInput = document.getElementById('settingsTabInput');
  var params = new URLSearchParams(window.location.search);
  var preferredTab = params.get('tab') || <?php echo json_encode($active_tab ?? 'general'); ?>;
  var tab = preferredTab;
  if (tab) {
    var btn = document.querySelector('[data-bs-target="#tab-' + tab + '"]');
    if (btn && window.bootstrap) {
      bootstrap.Tab.getOrCreateInstance(btn).show();
    }
    if (tabInput) tabInput.value = tab;
  }
  document.querySelectorAll('#settingsTabs [data-bs-toggle="tab"]').forEach(function (el) {
    el.addEventListener('shown.bs.tab', function (e) {
      if (!tabInput) return;
      var target = e.target.getAttribute('data-bs-target') || '';
      tabInput.value = target.replace('#tab-', '');
    });
  });

  var sqlBtn = document.getElementById('runSqlBtn');
  var sqlBox = document.getElementById('sqlResultBox');
  var sqlField = document.querySelector('textarea[name="sql_query"]');
  if (sqlBtn && sqlBox && sqlField) {
    sqlBtn.addEventListener('click', function () {
      var query = (sqlField.value || '').trim();
      if (!query) {
        sqlBox.innerHTML = '<div class="alert alert-warning small mb-0">Enter a read-only MySQL query to run.</div>';
        return;
      }

      sqlBtn.disabled = true;
      sqlBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Running...';
      sqlBox.innerHTML = '<div class="text-muted small">Running query...</div>';

      var formData = new FormData();
      formData.append('sql_query', query);

      fetch('<?= site_url('admin/settings/run_sql') ?>', {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json'
        },
        body: formData,
        credentials: 'same-origin'
      })
      .then(function (response) {
        return response.json().then(function (payload) {
          return { ok: response.ok, payload: payload };
        });
      })
      .then(function (result) {
        if (!result.ok || !result.payload || !result.payload.success) {
          var error = result.payload && result.payload.error ? result.payload.error : 'SQL query failed.';
          sqlBox.innerHTML = '<div class="alert alert-danger small mb-0">' + error + '</div>';
          return;
        }

        var payload = result.payload;
        var rows = Array.isArray(payload.rows) ? payload.rows : [];
        var columns = Array.isArray(payload.columns) && payload.columns.length ? payload.columns : [];
        if (!rows.length) {
          sqlBox.innerHTML = '<div class="alert alert-success small mb-0">Query ran successfully. No rows returned.</div>';
          return;
        }

        var thead = '<tr>' + columns.map(function (col) {
          return '<th>' + String(col) + '</th>';
        }).join('') + '</tr>';

        var tbody = rows.map(function (row) {
          var cells = columns.length ? columns.map(function (col) {
            var value = row[col] === null || row[col] === undefined ? '' : row[col];
            return '<td>' + String(value) + '</td>';
          }).join('') : '<td>' + JSON.stringify(row) + '</td>';
          return '<tr>' + cells + '</tr>';
        }).join('');

        sqlBox.innerHTML = '<div><h6 class="mb-2">Query result</h6><div class="table-responsive"><table class="table table-sm table-bordered align-middle mb-0"><thead class="table-light">' + thead + '</thead><tbody>' + tbody + '</tbody></table></div></div>';
      })
      .catch(function () {
        sqlBox.innerHTML = '<div class="alert alert-danger small mb-0">Query request failed. Please try again.</div>';
      })
      .finally(function () {
        sqlBtn.disabled = false;
        sqlBtn.innerHTML = '<i class="bi bi-play-circle me-1"></i> Run SQL';
      });
    });
  }
})();
</script>

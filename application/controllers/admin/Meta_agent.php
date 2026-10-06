<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'controllers/admin/Sk_Base.php';

/**
 * Admin UI for Meta Business Agent per WhatsApp phone number.
 */
class Meta_agent extends Sk_Base {

    public function __construct() {
        parent::__construct();
        $this->load->helper(['sk_whatsapp_cloud', 'sk_meta_business_agent', 'sk_dotenv']);
        $this->load->model(['Sk_Vendor_whatsapp_account_model', 'Sk_Vendor_meta_agent_model', 'Sk_Vendor_model']);
        sk_wa_cloud_ensure_schema();
        $this->Sk_Vendor_meta_agent_model->ensure_schema();
    }

    public function index() {
        $isVendor = !empty($this->session->userdata('sk_vendor_login'));
        $vendorId = $isVendor ? (int)$this->session->userdata('sk_vendor_id') : 0;

        if ($isVendor) {
            $accounts = $this->Sk_Vendor_whatsapp_account_model->get_for_vendor($vendorId);
        } else {
            $accounts = $this->Sk_Vendor_whatsapp_account_model->list_all('active', 300);
        }

        $rows = [];
        foreach ($accounts as $acct) {
            $phoneId = trim((string)($acct['phone_number_id'] ?? ''));
            if ($phoneId === '') {
                continue;
            }
            $agent = $this->Sk_Vendor_meta_agent_model->get_by_phone($phoneId);
            $vendor = null;
            $vid = (int)($acct['vendor_id'] ?? 0);
            if ($vid > 0) {
                $vendor = $this->Sk_Vendor_model->get_by_id($vid, false);
            }
            $rows[] = [
                'account' => $acct,
                'agent'   => $agent,
                'vendor'  => $vendor,
            ];
        }

        $cfg = sk_meta_ba_config_array();
        $data = [
            'title'           => 'Meta Business Agent',
            'rows'            => $rows,
            'platform_ready'  => sk_meta_ba_is_platform_ready(),
            'cfg'             => [
                'enabled'            => $cfg['enabled'],
                'api_base'           => $cfg['api_base'],
                'api_version'        => $cfg['api_version'],
                'default_audience'   => $cfg['default_audience'],
                'connector_base_url' => $cfg['connector_base_url'],
                'has_system_token'   => $cfg['system_user_token'] !== '',
                'has_connector_key'  => $cfg['connector_api_key'] !== '',
                'soft_handoff'       => empty($cfg['handoff_release_control']),
            ],
            'webhook_uri'     => sk_wa_meta_webhook_uri(),
        ];
        $this->render('meta/agent', $data);
    }

    public function action() {
        if (strtoupper((string)$this->input->server('REQUEST_METHOD')) !== 'POST') {
            return $this->json(['success' => false, 'message' => 'POST required.'], 405);
        }
        $phoneNumberId = trim((string)$this->input->post('phone_number_id', TRUE));
        $op = strtolower(trim((string)$this->input->post('op', TRUE)));
        if ($phoneNumberId === '' || $op === '') {
            return $this->json(['success' => false, 'message' => 'phone_number_id and op are required.']);
        }

        $acct = $this->Sk_Vendor_whatsapp_account_model->get_by_phone($phoneNumberId);
        if (!$acct) {
            return $this->json(['success' => false, 'message' => 'WhatsApp number not found or inactive.']);
        }
        $isVendor = !empty($this->session->userdata('sk_vendor_login'));
        if ($isVendor && (int)$acct['vendor_id'] !== (int)$this->session->userdata('sk_vendor_id')) {
            return $this->json(['success' => false, 'message' => 'Not allowed for this number.'], 403);
        }

        $settings = $this->Sk_Admin_model->get_settings();
        $vendorId = (int)$acct['vendor_id'];
        $shopName = 'Shop';
        if ($vendorId > 0) {
            $v = $this->Sk_Vendor_model->get_by_id($vendorId, false);
            $shopName = trim((string)($v['business_name'] ?? $v['owner_name'] ?? 'Shop')) ?: 'Shop';
        }

        $metaFail = static function (array $res, string $fallback = 'Request failed'): array {
            return [
                'message' => sk_meta_ba_human_error($res) ?: $fallback,
                'data'    => sk_meta_ba_error_data($res),
            ];
        };

        try {
            switch ($op) {
                case 'eligibility':
                    $res = sk_meta_ba_check_eligibility($phoneNumberId, $settings);
                    $eligible = !empty($res['ok']) && (
                        !empty($res['data']['eligible'])
                        || !empty($res['data']['is_eligible'])
                        || (($res['data']['status'] ?? '') === 'eligible')
                        || !empty($res['data']['data']['eligible'])
                    );
                    // If API returns 200 without explicit flag, treat as check completed.
                    if (!empty($res['ok']) && !$eligible && is_array($res['data'])) {
                        $eligible = empty($res['data']['error']);
                    }
                    $this->Sk_Vendor_meta_agent_model->upsert($phoneNumberId, [
                        'vendor_id'        => $vendorId,
                        'eligible'         => $eligible ? 1 : 0,
                        'eligibility_json' => $res['data'],
                        'sync_status'      => $res['ok'] ? 'checked' : 'error',
                        'last_error'       => $res['ok'] ? '' : ($res['error'] ?? 'Eligibility failed'),
                        'last_synced_at'   => date('Y-m-d H:i:s'),
                    ]);
                    $fail = $metaFail($res, 'Eligibility failed');
                    return $this->json([
                        'success'  => !empty($res['ok']),
                        'message'  => $res['ok'] ? ($eligible ? 'Number is eligible.' : 'Eligibility response received.') : $fail['message'],
                        'eligible' => $eligible,
                        'op'       => $op,
                        'data'     => $res['ok'] ? $res['data'] : $fail['data'],
                    ], $res['ok'] ? 200 : 400);

                case 'onboard':
                    $res = sk_meta_ba_onboard($phoneNumberId, $settings);
                    $agentId = '';
                    if (is_array($res['data'])) {
                        $agentId = (string)($res['data']['agent_id'] ?? $res['data']['id'] ?? '');
                        if ($agentId === '' && is_array($res['data']['data'] ?? null)) {
                            $agentId = (string)($res['data']['data']['agent_id'] ?? $res['data']['data']['id'] ?? '');
                        }
                    }
                    $this->Sk_Vendor_meta_agent_model->upsert($phoneNumberId, [
                        'vendor_id'      => $vendorId,
                        'agent_id'       => $agentId,
                        'onboarded'      => !empty($res['ok']) ? 1 : 0,
                        'sync_status'    => $res['ok'] ? 'onboarded' : 'error',
                        'last_error'     => $res['ok'] ? '' : ($res['error'] ?? 'Onboard failed'),
                        'last_synced_at' => date('Y-m-d H:i:s'),
                    ]);
                    $setupNotes = [];
                    if ($res['ok']) {
                        $ins = sk_meta_ba_upsert_instructions(
                            $phoneNumberId,
                            sk_meta_ba_default_instructions($shopName),
                            $settings
                        );
                        if (empty($ins['ok'])) {
                            $setupNotes[] = 'instructions: ' . sk_meta_ba_human_error($ins);
                        }
                        $biz = sk_meta_ba_upsert_business_info($phoneNumberId, [
                            'business_name' => $shopName,
                            'description'   => $shopName . ' WhatsApp commerce assistant powered by Talk AI Pilot.',
                        ], $settings);
                        if (empty($biz['ok'])) {
                            $setupNotes[] = 'business_info: ' . sk_meta_ba_human_error($biz);
                        }
                    }
                    $fail = $metaFail($res, 'Onboard failed');
                    $payload = $res['ok'] ? ($res['data'] ?? []) : $fail['data'];
                    if ($setupNotes) {
                        $payload = is_array($payload) ? $payload : [];
                        $payload['setup_warnings'] = $setupNotes;
                    }
                    return $this->json([
                        'success'  => !empty($res['ok']),
                        'message'  => $res['ok']
                            ? ('Agent onboarded.' . ($setupNotes ? ' Some optional setup steps failed — see data.setup_warnings.' : ''))
                            : $fail['message'],
                        'agent_id' => $agentId,
                        'op'       => $op,
                        'data'     => $payload,
                    ], $res['ok'] ? 200 : 400);

                case 'sync':
                    $res = sk_meta_ba_sync_connector_for_phone($phoneNumberId, $vendorId, $settings);
                    if (!$res['ok']) {
                        $this->Sk_Vendor_meta_agent_model->set_error($phoneNumberId, $res['error'] ?? 'Sync failed');
                    }
                    $fail = $metaFail($res, 'Sync failed');
                    return $this->json([
                        'success' => !empty($res['ok']),
                        'message' => $res['ok'] ? 'Connector and tools synced.' : $fail['message'],
                        'op'      => $op,
                        'data'    => $res['ok'] ? $res['data'] : array_merge($fail['data'], is_array($res['data']) ? $res['data'] : []),
                    ], $res['ok'] ? 200 : 400);

                case 'list_skills':
                    $res = sk_meta_ba_list_skills($phoneNumberId, $settings);
                    $fail = $metaFail($res, 'List skills failed');
                    return $this->json([
                        'success' => !empty($res['ok']),
                        'message' => $res['ok']
                            ? ('Found ' . (int)($res['data']['count'] ?? 0) . ' skill(s) on Meta.')
                            : $fail['message'],
                        'op'      => $op,
                        'data'    => $res['ok'] ? $res['data'] : $fail['data'],
                    ], $res['ok'] ? 200 : 400);

                case 'list_connectors':
                    $res = sk_meta_ba_list_connectors($phoneNumberId, $settings);
                    $fail = $metaFail($res, 'List connectors failed');
                    return $this->json([
                        'success' => !empty($res['ok']),
                        'message' => $res['ok']
                            ? ('Found ' . (int)($res['data']['count'] ?? 0) . ' connector(s) on Meta.')
                            : $fail['message'],
                        'op'      => $op,
                        'data'    => $res['ok'] ? $res['data'] : $fail['data'],
                    ], $res['ok'] ? 200 : 400);

                case 'connector_logs':
                    $row = $this->Sk_Vendor_meta_agent_model->get_by_phone($phoneNumberId);
                    $connectorId = trim((string)($row['connector_id'] ?? ''));
                    if ($connectorId === '') {
                        return $this->json(['success' => false, 'message' => 'No connector_id stored. Run Sync tools first.', 'op' => $op]);
                    }
                    $query = [
                        'limit'         => (int)$this->input->post('limit', TRUE) ?: 50,
                        'include_stats' => filter_var($this->input->post('include_stats', TRUE), FILTER_VALIDATE_BOOLEAN),
                        'summary_only'  => filter_var($this->input->post('summary_only', TRUE), FILTER_VALIDATE_BOOLEAN),
                    ];
                    $res = sk_meta_ba_connector_logs($phoneNumberId, $connectorId, $query, $settings);
                    $fail = $metaFail($res, 'Connector logs failed');
                    return $this->json([
                        'success' => !empty($res['ok']),
                        'message' => $res['ok'] ? 'Connector error logs retrieved (last 7 days max).' : $fail['message'],
                        'op'      => $op,
                        'data'    => $res['ok'] ? $res['data'] : $fail['data'],
                    ], $res['ok'] ? 200 : 400);

                case 'list_ui_skills':
                    $res = sk_meta_ba_list_all_ui_skills($phoneNumberId, $settings);
                    $fail = $metaFail($res, 'List UI skills failed');
                    return $this->json([
                        'success' => !empty($res['ok']),
                        'message' => $res['ok']
                            ? ('Found ' . (int)($res['data']['count'] ?? 0) . ' UI skill(s) on Meta.')
                            : $fail['message'],
                        'op'      => $op,
                        'data'    => $res['ok'] ? $res['data'] : $fail['data'],
                    ], $res['ok'] ? 200 : 400);

                case 'sync_ui_skills':
                    $catalogUrl = trim((string)$this->input->post('catalog_url', TRUE));
                    if ($catalogUrl === '' && $vendorId > 0) {
                        $v = $this->Sk_Vendor_model->get_by_id($vendorId, false);
                        $slug = trim((string)($v['slug'] ?? ''));
                        if ($slug !== '') {
                            $catalogUrl = rtrim(sk_meta_ba_config_array()['connector_base_url'], '/') . '/shop/' . rawurlencode($slug);
                        }
                    }
                    $res = sk_meta_ba_sync_shop_ui_skills($phoneNumberId, $shopName, $catalogUrl, $settings);
                    $fail = $metaFail($res, 'UI skill sync failed');
                    $msg = $res['ok']
                        ? (isset($res['data']['skipped']) ? (string)$res['data']['skipped'] : 'Shop UI skills synced.')
                        : $fail['message'];
                    return $this->json([
                        'success' => !empty($res['ok']),
                        'message' => $msg,
                        'op'      => $op,
                        'data'    => $res['ok'] ? $res['data'] : $fail['data'],
                    ], $res['ok'] ? 200 : 400);

                case 'sync_skills':
                    $res = sk_meta_ba_sync_sales_skills(
                        $phoneNumberId,
                        sk_meta_ba_default_instructions($shopName),
                        $settings
                    );
                    $fail = $metaFail($res, 'Skill sync failed');
                    $msg = $res['ok'] ? 'Sales skills synced (typos + product/price answers).' : $fail['message'];
                    if ($res['ok'] && is_array($res['data']['warnings'] ?? null) && $res['data']['warnings']) {
                        $msg .= ' Warnings: ' . implode(' ', $res['data']['warnings']);
                    }
                    return $this->json([
                        'success' => !empty($res['ok']),
                        'message' => $msg,
                        'op'      => $op,
                        'data'    => $res['ok'] ? $res['data'] : $fail['data'],
                    ], $res['ok'] ? 200 : 400);

                case 'test':
                    @set_time_limit(120);
                    $message = trim((string)$this->input->post('message', TRUE));
                    if ($message === '') {
                        $message = 'Hi, what products do you have?';
                    }
                    $res = sk_meta_ba_agent_test($phoneNumberId, $message, null, $settings);
                    $fail = $metaFail($res, 'Test failed');
                    $msg = $res['ok']
                        ? trim((string)($res['data']['agent_response'] ?? 'Test message sent to Meta Agent.'))
                        : $fail['message'];
                    if (!$res['ok'] && (int)($res['http'] ?? 0) >= 500) {
                        $msg .= ' Tip: try Test with just "Hi" first. Product questions call connectors — Sync tools, then retry.';
                    }
                    return $this->json([
                        'success' => !empty($res['ok']),
                        'message' => $msg,
                        'op'      => $op,
                        'data'    => $res['ok'] ? $res['data'] : $fail['data'],
                    ], $res['ok'] ? 200 : 400);

                case 'enable_allowlist':
                case 'enable_live':
                case 'disable':
                    $cfg = sk_meta_ba_config_array();
                    if ($op === 'disable') {
                        $body = [
                            'rollout' => ['enabled' => false],
                        ];
                        $audience = null;
                        $enabled = 0;
                    } elseif ($op === 'enable_allowlist') {
                        // handoff.enabled=false => soft handoff: Meta BA keeps thread control and keeps chatting.
                        $body = [
                            'ai_audience' => 'ALLOWLISTED_ONLY',
                            'rollout' => ['enabled' => true],
                            'handoff' => [
                                'enabled' => !empty($cfg['handoff_release_control']),
                                'message_selection' => 'CUSTOM',
                                'message' => $cfg['handoff_message'],
                            ],
                        ];
                        $audience = 'ALLOWLISTED_ONLY';
                        $enabled = 1;
                    } else {
                        $body = [
                            'ai_audience' => 'EVERYONE',
                            'rollout' => ['enabled' => true],
                            'handoff' => [
                                'enabled' => !empty($cfg['handoff_release_control']),
                                'message_selection' => 'CUSTOM',
                                'message' => $cfg['handoff_message'],
                            ],
                        ];
                        $audience = 'EVERYONE';
                        $enabled = 1;
                    }
                    $res = sk_meta_ba_put_settings($phoneNumberId, $body, $settings);
                    $skillNote = '';
                    if ($res['ok']) {
                        $upd = [
                            'vendor_id'      => $vendorId,
                            'agent_enabled'  => $enabled,
                            'sync_status'    => 'configured',
                            'last_error'     => '',
                            'last_synced_at' => date('Y-m-d H:i:s'),
                        ];
                        if ($audience) {
                            $upd['ai_audience'] = $audience;
                        }
                        $this->Sk_Vendor_meta_agent_model->upsert($phoneNumberId, $upd);
                        if ($enabled) {
                            $skills = sk_meta_ba_sync_sales_skills(
                                $phoneNumberId,
                                sk_meta_ba_default_instructions($shopName),
                                $settings
                            );
                            if (empty($skills['ok'])) {
                                $skillNote = ' Skills warning: ' . sk_meta_ba_human_error($skills);
                            }
                        }
                    } else {
                        $this->Sk_Vendor_meta_agent_model->set_error($phoneNumberId, $res['error'] ?? 'Settings update failed');
                    }
                    $fail = $metaFail($res, 'Settings update failed');
                    return $this->json([
                        'success' => !empty($res['ok']),
                        'message' => $res['ok'] ? ('Agent settings updated.' . $skillNote) : $fail['message'],
                        'op'      => $op,
                        'data'    => $res['ok'] ? $res['data'] : $fail['data'],
                    ], $res['ok'] ? 200 : 400);

                case 'release':
                case 'take':
                    $to = trim((string)$this->input->post('to', TRUE));
                    $conversationId = (int)$this->input->post('conversation_id');
                    if ($to === '' && $conversationId > 0) {
                        $this->load->model('Sk_Whatsapp_cloud_model');
                        $conv = $this->Sk_Whatsapp_cloud_model->get_conversation($conversationId);
                        $to = (string)($conv['phone'] ?? '');
                    }
                    if ($to === '') {
                        return $this->json(['success' => false, 'message' => 'Consumer phone (to) is required.']);
                    }
                    $res = sk_meta_ba_thread_control($phoneNumberId, $to, $op, $settings);
                    if ($res['ok'] && $conversationId > 0) {
                        $owner = $op === 'release' ? 'meta_agent' : 'app';
                        $this->Sk_Vendor_meta_agent_model->set_conversation_owner($conversationId, $owner, $op);
                    }
                    $fail = $metaFail($res, 'Thread control failed');
                    return $this->json([
                        'success' => !empty($res['ok']),
                        'message' => $res['ok'] ? ('Thread control: ' . $op) : $fail['message'],
                        'op'      => $op,
                        'data'    => $res['ok'] ? $res['data'] : $fail['data'],
                    ], $res['ok'] ? 200 : 400);

                default:
                    return $this->json(['success' => false, 'message' => 'Unknown op.']);
            }
        } catch (Throwable $e) {
            log_message('error', 'Meta_agent::action ' . $e->getMessage());
            $this->Sk_Vendor_meta_agent_model->set_error($phoneNumberId, $e->getMessage());
            return $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}

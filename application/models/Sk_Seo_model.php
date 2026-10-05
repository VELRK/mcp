<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Sk_Seo_model extends CI_Model {

    protected $pages_table = 'seo_pages';

    public function table_exists(): bool {
        return $this->db->table_exists($this->pages_table);
    }

    public function get_all_pages(): array {
        if (!$this->table_exists()) return [];
        return $this->db->order_by('page_label')->get($this->pages_table)->result_array();
    }

    public function get_page_by_key(string $key): ?array {
        if (!$this->table_exists()) return null;
        return $this->db->where('page_key', $key)->get($this->pages_table)->row_array() ?: null;
    }

    public function get_page_by_id(int $id): ?array {
        if (!$this->table_exists()) return null;
        return $this->db->where('id', $id)->get($this->pages_table)->row_array() ?: null;
    }

    public function get_page_by_route(string $route): ?array {
        if (!$this->table_exists()) return null;
        $route = '/' . trim($route, '/');
        if ($route === '/') {
            return $this->get_page_by_key('home');
        }
        $row = $this->db->where('route_path', $route)->get($this->pages_table)->row_array();
        return $row ?: null;
    }

    public function save_page(int $id, array $data): bool {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->db->where('id', $id)->update($this->pages_table, $data);
    }

    /** Normalize SEO payload for API / frontend. */
    public function format_page(array $row, ?array $globals = null): array {
        $globals = $globals ?? [];
        $site    = $globals['site_name'] ?? 'Talk AI Pilot';
        $title   = trim($row['meta_title'] ?? '') ?: trim($row['page_label'] ?? $site);
        $desc    = trim($row['meta_description'] ?? '');
        $ogTitle = trim($row['og_title'] ?? '') ?: $title;
        $ogDesc  = trim($row['og_description'] ?? '') ?: $desc;
        $ogImage = trim($row['og_image'] ?? '') ?: trim($globals['seo_og_image'] ?? '');

        return [
            'page_key'         => $row['page_key'] ?? '',
            'route_path'       => $row['route_path'] ?? '',
            'meta_title'       => $title,
            'meta_description' => $desc,
            'meta_keywords'    => trim($row['meta_keywords'] ?? ''),
            'og_title'         => $ogTitle,
            'og_description'   => $ogDesc,
            'og_image'         => $ogImage ? $this->_abs_url($ogImage) : '',
            'canonical_url'    => trim($row['canonical_url'] ?? ''),
            'robots'           => trim($row['robots'] ?? 'index,follow'),
            'head_scripts'     => $row['head_scripts'] ?? '',
            'footer_scripts'   => $row['footer_scripts'] ?? '',
        ];
    }

    public function format_entity(array $row, string $type, ?array $globals = null): array {
        $globals = $globals ?? [];
        $site    = $globals['site_name'] ?? 'Talk AI Pilot';
        $name    = $row['title'] ?? $row['name'] ?? $site;
        $fallbackDesc = $row['excerpt'] ?? $row['short_desc'] ?? strip_tags($row['description'] ?? '');
        $fallbackDesc = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags($fallbackDesc))), 0, 160);

        $title   = trim($row['meta_title'] ?? '') ?: ($name . ' | ' . $site);
        $desc    = trim($row['meta_desc'] ?? $row['meta_description'] ?? '') ?: $fallbackDesc;
        $ogImage = trim($row['og_image'] ?? '');
        if (!$ogImage && !empty($row['image'])) {
            $ogImage = $row['image'];
        } elseif (!$ogImage && !empty($row['thumbnail'])) {
            $ogImage = $row['thumbnail'];
        }

        return [
            'entity_type'      => $type,
            'meta_title'       => $title,
            'meta_description' => $desc,
            'meta_keywords'    => trim($row['meta_keywords'] ?? ''),
            'og_title'         => $title,
            'og_description'   => $desc,
            'og_image'         => $ogImage ? $this->_abs_url($ogImage) : (trim($globals['seo_og_image'] ?? '') ? $this->_abs_url($globals['seo_og_image']) : ''),
            'robots'           => 'index,follow',
        ];
    }

    public function get_global_seo(): array {
        $keys = ['site_name', 'meta_title', 'meta_desc', 'meta_keywords', 'seo_og_image', 'head_scripts', 'footer_scripts', 'google_analytics'];
        $rows = $this->db->where_in('key', $keys)->get('settings')->result_array();
        $map  = [];
        foreach ($rows as $r) {
            $map[$r['key']] = $r['value'];
        }
        $site  = $map['site_name'] ?? 'Talk AI Pilot';
        $title = trim($map['meta_title'] ?? '') ?: $site;
        $desc  = trim($map['meta_desc'] ?? '');

        return [
            'site_name'        => $site,
            'meta_title'       => $title,
            'meta_description' => $desc,
            'meta_keywords'    => trim($map['meta_keywords'] ?? ''),
            'og_image'         => !empty($map['seo_og_image']) ? $this->_abs_url($map['seo_og_image']) : '',
            'head_scripts'     => $map['head_scripts'] ?? '',
            'footer_scripts'   => $map['footer_scripts'] ?? '',
            'google_analytics' => $map['google_analytics'] ?? '',
        ];
    }

    public function audit_summary(): array {
        $summary = ['pages' => 0, 'pages_missing' => 0, 'products_missing' => 0, 'blogs_missing' => 0];

        if ($this->table_exists()) {
            $pages = $this->get_all_pages();
            $summary['pages'] = count($pages);
            foreach ($pages as $p) {
                if (empty($p['meta_title']) || empty($p['meta_description'])) {
                    $summary['pages_missing']++;
                }
            }
        }

        if ($this->db->table_exists('products')) {
            $summary['products_missing'] = (int)$this->db
                ->where('status', 'active')
                ->group_start()
                    ->where('meta_title IS NULL', null, false)
                    ->or_where('meta_title', '')
                    ->or_where('meta_desc IS NULL', null, false)
                    ->or_where('meta_desc', '')
                ->group_end()
                ->count_all_results('products');
        }

        if ($this->db->table_exists('blogs')) {
            $summary['blogs_missing'] = (int)$this->db
                ->where('status', 1)
                ->group_start()
                    ->where('meta_title IS NULL', null, false)
                    ->or_where('meta_title', '')
                ->group_end()
                ->count_all_results('blogs');
        }

        return $summary;
    }

    protected function _abs_url(string $path): string {
        if (preg_match('#^https?://#i', $path)) return $path;
        return rtrim(base_url(), '/') . '/' . ltrim($path, '/');
    }
}

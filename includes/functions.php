<?php
require_once __DIR__ . '/config.php';

// ── Settings ──────────────────────────────────────────────────────
function getSetting(string $key, string $default = ''): string {
    static $cache = null;
    if ($cache === null) {
        try {
            $rows  = siteDB()->query('SELECT `key`,`value` FROM site_settings')->fetchAll();
            $cache = array_column($rows, 'value', 'key');
        } catch (Exception $e) { $cache = []; }
    }
    return $cache[$key] ?? $default;
}

// ── Sliders ───────────────────────────────────────────────────────
function getSliders(): array {
    try {
        return siteDB()->query(
            'SELECT * FROM site_sliders WHERE is_active=1 ORDER BY sort_order, id'
        )->fetchAll();
    } catch (Exception $e) { return []; }
}

// ── News ──────────────────────────────────────────────────────────
function getNews(int $limit = 10, bool $featuredOnly = false, string $category = ''): array {
    try {
        $where  = 'WHERE is_published=1';
        $params = [];
        if ($featuredOnly) { $where .= ' AND is_featured=1'; }
        if ($category)     { $where .= ' AND category=?'; $params[] = $category; }
        $st = siteDB()->prepare(
            "SELECT * FROM site_news $where ORDER BY published_at DESC, created_at DESC LIMIT ?"
        );
        $params[] = $limit;
        $st->execute($params);
        return $st->fetchAll();
    } catch (Exception $e) { return []; }
}

function getNewsById(int $id): ?array {
    try {
        $st = siteDB()->prepare('SELECT * FROM site_news WHERE id=? AND is_published=1');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    } catch (Exception $e) { return null; }
}

// ── Events ────────────────────────────────────────────────────────
function getEvents(int $limit = 10, bool $upcomingOnly = false): array {
    try {
        $where = 'WHERE is_published=1';
        if ($upcomingOnly) $where .= ' AND event_date >= CURDATE()';
        $st = siteDB()->prepare(
            "SELECT * FROM site_events $where ORDER BY event_date ASC LIMIT ?"
        );
        $st->execute([$limit]);
        return $st->fetchAll();
    } catch (Exception $e) { return []; }
}

// ── Notices ───────────────────────────────────────────────────────
function getNotices(int $limit = 20, string $category = ''): array {
    try {
        $where  = 'WHERE is_published=1 AND (expires_at IS NULL OR expires_at >= CURDATE())';
        $params = [];
        if ($category) { $where .= ' AND category=?'; $params[] = $category; }
        $params[] = $limit;
        $st = siteDB()->prepare(
            "SELECT * FROM site_notices $where ORDER BY priority DESC, created_at DESC LIMIT ?"
        );
        $st->execute($params);
        return $st->fetchAll();
    } catch (Exception $e) { return []; }
}

// ── Faculty ───────────────────────────────────────────────────────
function getFaculty(int $deptId = 0, int $limit = 100): array {
    try {
        $where  = 'WHERE f.is_active=1';
        $params = [];
        if ($deptId) { $where .= ' AND f.department_id=?'; $params[] = $deptId; }
        $params[] = $limit;
        $st = siteDB()->prepare(
            "SELECT f.*, d.name AS dept_name FROM site_faculty f
             LEFT JOIN site_departments d ON d.id=f.department_id
             $where ORDER BY f.sort_order, f.name LIMIT ?"
        );
        $st->execute($params);
        return $st->fetchAll();
    } catch (Exception $e) { return []; }
}

// ── Departments ───────────────────────────────────────────────────
function getDepartments(): array {
    try {
        return siteDB()->query(
            'SELECT d.*, COUNT(f.id) AS faculty_count
             FROM site_departments d
             LEFT JOIN site_faculty f ON f.department_id=d.id AND f.is_active=1
             WHERE d.is_active=1 GROUP BY d.id ORDER BY d.sort_order, d.name'
        )->fetchAll();
    } catch (Exception $e) { return []; }
}

// ── Programs ──────────────────────────────────────────────────────
function getPrograms(int $deptId = 0): array {
    try {
        $where  = 'WHERE p.is_active=1';
        $params = [];
        if ($deptId) { $where .= ' AND p.department_id=?'; $params[] = $deptId; }
        $st = siteDB()->prepare(
            "SELECT p.*, d.name AS dept_name FROM site_programs p
             LEFT JOIN site_departments d ON d.id=p.department_id
             $where ORDER BY d.sort_order, p.name"
        );
        $st->execute($params);
        return $st->fetchAll();
    } catch (Exception $e) { return []; }
}

// ── Gallery ───────────────────────────────────────────────────────
function getGalleryAlbums(): array {
    try {
        return siteDB()->query(
            'SELECT a.*, COUNT(g.id) AS photo_count
             FROM site_albums a
             LEFT JOIN site_gallery g ON g.album_id=a.id AND g.is_active=1
             WHERE a.is_active=1 GROUP BY a.id ORDER BY a.created_at DESC'
        )->fetchAll();
    } catch (Exception $e) { return []; }
}

function getGalleryPhotos(int $albumId = 0, int $limit = 50): array {
    try {
        $where  = 'WHERE g.is_active=1';
        $params = [];
        if ($albumId) { $where .= ' AND g.album_id=?'; $params[] = $albumId; }
        $params[] = $limit;
        $st = siteDB()->prepare(
            "SELECT g.*, a.name AS album_name FROM site_gallery g
             LEFT JOIN site_albums a ON a.id=g.album_id
             $where ORDER BY g.sort_order, g.created_at DESC LIMIT ?"
        );
        $st->execute($params);
        return $st->fetchAll();
    } catch (Exception $e) { return []; }
}

// ── Videos ────────────────────────────────────────────────────────
function getVideos(int $limit = 12): array {
    try {
        $st = siteDB()->prepare('SELECT * FROM site_videos WHERE is_active=1 ORDER BY created_at DESC LIMIT ?');
        $st->execute([$limit]);
        return $st->fetchAll();
    } catch (Exception $e) { return []; }
}

// ── Downloads ─────────────────────────────────────────────────────
function getDownloads(string $category = ''): array {
    try {
        $where  = 'WHERE is_active=1';
        $params = [];
        if ($category) { $where .= ' AND category=?'; $params[] = $category; }
        $st = siteDB()->prepare("SELECT * FROM site_downloads $where ORDER BY category, created_at DESC");
        $st->execute($params);
        return $st->fetchAll();
    } catch (Exception $e) { return []; }
}

function getAdmissionForms(): array {
    try {
        return siteDB()->query(
            'SELECT * FROM site_admission_forms WHERE is_active=1 ORDER BY created_at DESC'
        )->fetchAll();
    } catch (Exception $e) { return []; }
}

// ── Testimonials ──────────────────────────────────────────────────
function getTestimonials(int $limit = 6): array {
    try {
        $st = siteDB()->prepare('SELECT * FROM site_testimonials WHERE is_active=1 ORDER BY id LIMIT ?');
        $st->execute([$limit]);
        return $st->fetchAll();
    } catch (Exception $e) { return []; }
}

// ── Stats ─────────────────────────────────────────────────────────
function getStats(): array {
    try {
        return siteDB()->query(
            'SELECT * FROM site_stats ORDER BY sort_order'
        )->fetchAll();
    } catch (Exception $e) {
        return [
            ['label'=>'Students Enrolled','value'=>4500,'icon'=>'fa-user-graduate','suffix'=>'+'],
            ['label'=>'Expert Faculty',   'value'=>185, 'icon'=>'fa-chalkboard-teacher','suffix'=>'+'],
            ['label'=>'Years of Excellence','value'=>30,'icon'=>'fa-award','suffix'=>'+'],
            ['label'=>'Programs Offered', 'value'=>24,  'icon'=>'fa-book-open','suffix'=>'+'],
        ];
    }
}

// ── Partners ──────────────────────────────────────────────────────
function getPartners(): array {
    try {
        return siteDB()->query('SELECT * FROM site_partners WHERE is_active=1 ORDER BY sort_order')->fetchAll();
    } catch (Exception $e) { return []; }
}

// ── Careers ───────────────────────────────────────────────────────
function getCareers(): array {
    try {
        return siteDB()->query(
            'SELECT * FROM site_careers WHERE is_published=1 AND (deadline IS NULL OR deadline >= CURDATE())
             ORDER BY created_at DESC'
        )->fetchAll();
    } catch (Exception $e) { return []; }
}

// ── Search ────────────────────────────────────────────────────────
function globalSearch(string $q, int $limit = 30): array {
    $q     = '%' . $q . '%';
    $db    = siteDB();
    $results = [];
    try {
        // News
        $st = $db->prepare('SELECT id,"news" AS type,title,excerpt AS snippet,published_at AS date FROM site_news WHERE is_published=1 AND (title LIKE ? OR content LIKE ? OR excerpt LIKE ?) LIMIT 10');
        $st->execute([$q,$q,$q]);
        $results = array_merge($results, $st->fetchAll());
        // Notices
        $st = $db->prepare('SELECT id,"notice" AS type,title,content AS snippet,created_at AS date FROM site_notices WHERE is_published=1 AND (title LIKE ? OR content LIKE ?) LIMIT 10');
        $st->execute([$q,$q]);
        $results = array_merge($results, $st->fetchAll());
        // Faculty
        $st = $db->prepare('SELECT id,"faculty" AS type,name AS title,designation AS snippet,NULL AS date FROM site_faculty WHERE is_active=1 AND (name LIKE ? OR designation LIKE ? OR bio LIKE ?) LIMIT 10');
        $st->execute([$q,$q,$q]);
        $results = array_merge($results, $st->fetchAll());
        // Events
        $st = $db->prepare('SELECT id,"event" AS type,title,description AS snippet,event_date AS date FROM site_events WHERE is_published=1 AND (title LIKE ? OR description LIKE ?) LIMIT 10');
        $st->execute([$q,$q]);
        $results = array_merge($results, $st->fetchAll());
    } catch (Exception $e) {}
    return $results;
}

// ── Utility ───────────────────────────────────────────────────────
function sh(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function siteUrl(string $path): string { return SITE_URL . $path; }

function portalUrl(string $path): string { return BASE_URL . $path; }

function truncateText(string $text, int $len = 150): string {
    $text = strip_tags($text);
    return strlen($text) <= $len ? $text : substr($text, 0, $len) . '…';
}

function siteDate(?string $d): string {
    if (!$d) return '';
    return date('d M Y', strtotime($d));
}

function siteDatetime(?string $d): string {
    if (!$d) return '';
    return date('d M Y, g:i a', strtotime($d));
}

function uploadUrl(string $folder, string $file): string {
    return SITE_URL . '/assets/uploads/' . $folder . '/' . $file;
}

function slugify(string $text): string {
    return preg_replace('/[^a-z0-9-]/', '', strtolower(preg_replace('/[\s_]+/', '-', $text)));
}

function priorityBadge(string $priority): string {
    return match($priority) {
        'urgent'    => '<span class="badge bg-danger">Urgent</span>',
        'important' => '<span class="badge bg-warning text-dark">Important</span>',
        default     => '<span class="badge bg-secondary">Notice</span>',
    };
}

function getYoutubeId(string $url): string {
    preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\s]+)/', $url, $m);
    return $m[1] ?? '';
}

function getYoutubeThumbnail(string $url): string {
    $id = getYoutubeId($url);
    return $id ? "https://img.youtube.com/vi/{$id}/maxresdefault.jpg" : '';
}

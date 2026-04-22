<?php
// ============================================================
// data/db.php — Softaliza v2
// ============================================================

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = getenv("DB_HOST") ?: "localhost";
    $port = getenv("DB_PORT") ?: "";
    $name = getenv("DB_NAME") ?: "u289267434_softalizapages";
    $user = getenv("DB_USER") ?: "u289267434_softalizapages";
    $pass = getenv("DB_PASS") ?: "Softaliza@123";
    $dsn  = "mysql:host={$host};" . ($port !== "" ? "port={$port};" : "") . "dbname={$name};charset=utf8mb4";

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    runMigrations($pdo);

    return $pdo;
}

// ── Sistema de Migrations ─────────────────────────────────────

/**
 * Lista de migrations em ordem.
 * Para adicionar uma nova migration:
 *   1. Incremente a versão (próximo número sequencial)
 *   2. Adicione o SQL no array abaixo
 * As migrations rodam automaticamente na primeira conexão se ainda não foram aplicadas.
 */
function migrations(): array
{
    return [
        1 => "create table if not exists associations (
                id char(36) not null,
                name text not null,
                contact_email text,
                created_at timestamp not null default current_timestamp,
                primary key (id)
              ) engine=InnoDB charset=utf8mb4",

        2 => "create table if not exists templates (
                id char(36) not null,
                name text not null,
                description text,
                default_pages json not null,
                created_at timestamp not null default current_timestamp,
                primary key (id),
                unique key templates_name_unique (name)
              ) engine=InnoDB charset=utf8mb4",

        3 => "create table if not exists form_requests (
                id char(36) not null,
                association_name text not null,
                contact_email text,
                contact_phone text,
                contact_address text,
                site_name text not null,
                domain text,
                template_id char(36),
                notes text,
                logo_url text,
                event_date date null,
                event_location text,
                primary_color varchar(20),
                secondary_color varchar(20),
                accent_color varchar(20),
                speakers json,
                scientific_committee json,
                organizing_committee json,
                social_links json,
                pages_requested json,
                pages_content json,
                inspiration_site_id char(36),
                status text not null default 'novo',
                created_at timestamp not null default current_timestamp,
                converted_at timestamp null,
                primary key (id)
              ) engine=InnoDB charset=utf8mb4",

        4 => "create table if not exists sites (
                id char(36) not null,
                association_id char(36) not null,
                template_id char(36),
                name text not null,
                status text not null default 'rascunho',
                plan text not null default 'basico',
                domain text,
                notes text,
                logo_url text,
                primary_color varchar(20),
                secondary_color varchar(20),
                accent_color varchar(20),
                created_at timestamp not null default current_timestamp,
                primary key (id)
              ) engine=InnoDB charset=utf8mb4",

        5 => "create table if not exists site_pages (
                id char(36) not null,
                site_id char(36) not null,
                title text not null,
                file text not null,
                status text not null default 'rascunho',
                sort_order int not null default 0,
                created_at timestamp not null default current_timestamp,
                primary key (id),
                key site_pages_site_id_idx (site_id)
              ) engine=InnoDB charset=utf8mb4",

        6 => "create table if not exists users (
                id char(36) not null,
                name varchar(255) not null,
                email varchar(255) not null,
                password varchar(255) not null,
                email_verified_at timestamp null,
                verification_token varchar(100) null,
                reset_token varchar(100) null,
                reset_token_expires_at timestamp null,
                created_at timestamp not null default current_timestamp,
                primary key (id),
                unique key users_email_unique (email)
              ) engine=InnoDB charset=utf8mb4",

        // Adiciona coluna is_inspiration se ainda não existe
        7 => "alter table sites add column if not exists is_inspiration tinyint(1) not null default 0",

        // Adiciona campos extras no form_requests para inspiration_link
        8 => "alter table form_requests add column if not exists inspiration_link text null",
    ];
}

function runMigrations(PDO $pdo): void
{
    // Garante que a tabela de controle existe
    $pdo->exec("create table if not exists schema_migrations (
        version int not null,
        applied_at timestamp not null default current_timestamp,
        primary key (version)
    ) engine=InnoDB");

    // Descobre quais já foram aplicadas
    $applied = $pdo->query("select version from schema_migrations order by version")
                   ->fetchAll(PDO::FETCH_COLUMN);
    $applied = array_flip($applied);

    foreach (migrations() as $version => $sql) {
        if (isset($applied[$version])) continue;

        try {
            $pdo->exec($sql);
            $pdo->prepare("insert into schema_migrations (version) values (?)")->execute([$version]);
        } catch (Throwable $e) {
            error_log("Migration {$version} falhou: " . $e->getMessage());
            // Não bloqueia a aplicação por migrations antigas (tabelas podem já existir)
        }
    }
}

// ── Utilitários ──────────────────────────────────────────────

function generateUuid(): string
{
    $data    = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf("%s%s-%s-%s-%s-%s%s%s", str_split(bin2hex($data), 4));
}

function sitesRoot(): string
{
    return dirname(__DIR__) . "/sites";
}

function ensureSiteFolder(string $siteId): string
{
    $dir = sitesRoot() . "/" . $siteId;
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    return $dir;
}

function siteSharedDir(string $siteId): string
{
    $dir = ensureSiteFolder($siteId) . "/_shared";
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    return $dir;
}

function ensureSiteSharedDefaults(string $siteId): void
{
    $dir        = siteSharedDir($siteId);
    $headerPath = $dir . "/header.html";
    $footerPath = $dir . "/footer.html";

    if (!file_exists($headerPath)) {
        $header = "<header class=\"site-header\">\n" .
            "  <div class=\"container\">\n" .
            "    <div class=\"logo\">Softaliza</div>\n" .
            "    <nav class=\"menu\">\n" .
            "      <a href=\"#\">Home</a>\n" .
            "      <a href=\"#\">Sobre</a>\n" .
            "      <a href=\"#\">Contato</a>\n" .
            "    </nav>\n" .
            "  </div>\n" .
            "</header>\n";
        file_put_contents($headerPath, $header);
    }

    if (!file_exists($footerPath)) {
        $footer = "<footer class=\"site-footer\">\n" .
            "  <div class=\"container\">\n" .
            "    <div>Softaliza • Todos os direitos reservados</div>\n" .
            "  </div>\n" .
            "</footer>\n";
        file_put_contents($footerPath, $footer);
    }
}

function normalizeFileName(string $title): string
{
    $map = [
        'á'=>'a','à'=>'a','ã'=>'a','â'=>'a','ä'=>'a',
        'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
        'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
        'ó'=>'o','ò'=>'o','õ'=>'o','ô'=>'o','ö'=>'o',
        'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u',
        'ç'=>'c','ñ'=>'n',
        'Á'=>'a','À'=>'a','Ã'=>'a','Â'=>'a','Ä'=>'a',
        'É'=>'e','È'=>'e','Ê'=>'e','Ë'=>'e',
        'Í'=>'i','Ì'=>'i','Î'=>'i','Ï'=>'i',
        'Ó'=>'o','Ò'=>'o','Õ'=>'o','Ô'=>'o','Ö'=>'o',
        'Ú'=>'u','Ù'=>'u','Û'=>'u','Ü'=>'u',
        'Ç'=>'c','Ñ'=>'n',
    ];
    $title = strtr($title, $map);
    $title = strtolower($title);
    $title = preg_replace('/[^a-z0-9]+/', '-', $title);
    $title = trim($title, '-');
    return $title . ".html";
}

function ensureUniqueFile(string $dir, string $filename): string
{
    $base = pathinfo($filename, PATHINFO_FILENAME);
    $ext  = pathinfo($filename, PATHINFO_EXTENSION);
    $i    = 1;
    $name = $filename;
    while (file_exists($dir . "/" . $name)) {
        $name = $base . "-" . $i . "." . $ext;
        $i++;
    }
    return $name;
}

function buildPageHtmlForSite(string $siteId, string $title, string $bodyContent): string
{
    ensureSiteSharedDefaults($siteId);
    return "<!DOCTYPE html>\n<html lang=\"pt-br\">\n<head>\n" .
        "  <meta charset=\"utf-8\" />\n" .
        "  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\" />\n" .
        "  <title>" . htmlspecialchars($title, ENT_QUOTES) . "</title>\n" .
        "  <script src=\"/shared/site-layout.js\"></script>\n" .
        "  <style>\n" .
        "    body{margin:0;font-family:Arial,sans-serif;}\n" .
        "    .container{max-width:1100px;margin:0 auto;padding:0 20px;}\n" .
        "    .site-header{background:#0b1f3a;color:#fff;padding:16px 0;}\n" .
        "    .site-header .logo{font-weight:700;font-size:20px;}\n" .
        "    .menu a{color:#dbe7ff;text-decoration:none;margin-left:20px;}\n" .
        "    .site-footer{background:#0b1f3a;color:#dbe7ff;padding:16px 0;text-align:center;font-size:13px;margin-top:40px;}\n" .
        "    .main-content{padding:40px 0;}\n" .
        "  </style>\n" .
        "</head>\n<body>\n" .
        "  <div data-site-part=\"header\"></div>\n" .
        "  <div class=\"main-content\">\n    <div class=\"container\">\n      " .
        $bodyContent . "\n    </div>\n  </div>\n" .
        "  <div data-site-part=\"footer\"></div>\n" .
        "</body>\n</html>\n";
}

function deleteDirectory(string $dir): void
{
    if (!is_dir($dir)) return;
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === "." || $item === "..") continue;
        $path = $dir . "/" . $item;
        if (is_dir($path)) {
            deleteDirectory($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}

// ── Queries de leitura ───────────────────────────────────────

function fetchStats(): array
{
    try {
        $stmt = db()->query(
            "select
                (select count(*) from associations) as associations,
                (select count(*) from site_pages)   as pages,
                (select count(*) from sites where status = 'ativo') as active,
                (select count(*) from form_requests where status = 'novo') as pending_forms"
        );
        $row = $stmt->fetch() ?: [];
        return [
            "organizacoes"  => (int) ($row["associations"]   ?? 0),
            "eventos"       => (int) ($row["pages"]          ?? 0),
            "ativos"        => (int) ($row["active"]         ?? 0),
            "pending_forms" => (int) ($row["pending_forms"]  ?? 0),
        ];
    } catch (Throwable $e) {
        error_log("fetchStats failed: " . $e->getMessage());
        return ["organizacoes" => 0, "eventos" => 0, "ativos" => 0, "pending_forms" => 0];
    }
}

function fetchSites(): array
{
    $sql = "select
                s.id,
                s.name,
                s.status,
                s.plan,
                s.created_at,
                a.name  as owner,
                t.name  as template,
                (select count(*) from site_pages sp where sp.site_id = s.id) as page_count
            from sites s
            join associations a on a.id = s.association_id
            left join templates t on t.id = s.template_id
            order by s.created_at desc, s.name";
    return db()->query($sql)->fetchAll();
}

function fetchTemplates(): array
{
    return db()->query("select id, name, description from templates order by name")->fetchAll();
}

function fetchSite(string $id): ?array
{
    $stmt = db()->prepare(
        "select
            s.id,
            s.name,
            s.status,
            s.plan,
            s.domain,
            s.notes,
            s.logo_url,
            s.primary_color,
            s.secondary_color,
            s.accent_color,
            s.created_at,
            a.name          as owner,
            a.contact_email as contact_email,
            t.name          as template
        from sites s
        join associations a on a.id = s.association_id
        left join templates t on t.id = s.template_id
        where s.id = :id"
    );
    $stmt->execute(["id" => $id]);
    return $stmt->fetch() ?: null;
}

function fetchSitePages(string $siteId): array
{
    $stmt = db()->prepare(
        "select id, title, file, status
        from site_pages
        where site_id = :site_id
        order by sort_order, title"
    );
    $stmt->execute(["site_id" => $siteId]);
    return $stmt->fetchAll();
}

function fetchSitePage(string $pageId): ?array
{
    $stmt = db()->prepare(
        "select id, site_id, title, file, status
        from site_pages where id = :id"
    );
    $stmt->execute(["id" => $pageId]);
    return $stmt->fetch() ?: null;
}

function fetchForms(): array
{
    $sql = "select
                f.id,
                f.association_name,
                f.site_name,
                f.contact_email,
                f.contact_phone,
                f.domain,
                f.event_date,
                f.event_location,
                f.primary_color,
                f.logo_url,
                f.pages_requested,
                f.status,
                f.created_at,
                t.name as template,
                si.name as inspiration_site_name
            from form_requests f
            left join templates t  on t.id  = f.template_id
            left join sites     si on si.id = f.inspiration_site_id
            order by f.created_at desc";
    return db()->query($sql)->fetchAll();
}

function fetchForm(string $id): ?array
{
    $stmt = db()->prepare(
        "select
            f.id,
            f.association_name,
            f.contact_email,
            f.contact_phone,
            f.contact_address,
            f.site_name,
            f.domain,
            f.template_id,
            f.notes,
            f.logo_url,
            f.event_date,
            f.event_location,
            f.primary_color,
            f.secondary_color,
            f.accent_color,
            f.speakers,
            f.scientific_committee,
            f.organizing_committee,
            f.social_links,
            f.pages_requested,
            f.pages_content,
            f.inspiration_site_id,
            f.status,
            f.created_at,
            t.name  as template,
            si.name as inspiration_site_name
        from form_requests f
        left join templates t  on t.id  = f.template_id
        left join sites     si on si.id = f.inspiration_site_id
        where f.id = :id"
    );
    $stmt->execute(["id" => $id]);
    $row = $stmt->fetch();
    if (!$row) return null;

    // Decodifica JSON automaticamente
    foreach (["speakers","scientific_committee","organizing_committee","social_links","pages_requested","pages_content"] as $col) {
        if (isset($row[$col]) && is_string($row[$col])) {
            $row[$col] = json_decode($row[$col], true) ?: [];
        }
    }
    return $row;
}

// ── Criação ──────────────────────────────────────────────────

function createFormRequest(array $data): string
{
    $id   = generateUuid();
    $stmt = db()->prepare(
        "insert into form_requests
            (id, association_name, contact_email, contact_phone, contact_address,
             site_name, domain, template_id, notes,
             logo_url, event_date, event_location,
             primary_color, secondary_color, accent_color,
             speakers, scientific_committee, organizing_committee,
             social_links, pages_requested, pages_content, inspiration_site_id)
        values
            (:id, :association_name, :contact_email, :contact_phone, :contact_address,
             :site_name, :domain, :template_id, :notes,
             :logo_url, :event_date, :event_location,
             :primary_color, :secondary_color, :accent_color,
             :speakers, :scientific_committee, :organizing_committee,
             :social_links, :pages_requested, :pages_content, :inspiration_site_id)"
    );
    $stmt->execute([
        "id"                    => $id,
        "association_name"      => $data["association_name"],
        "contact_email"         => $data["contact_email"]          ?? null,
        "contact_phone"         => $data["contact_phone"]          ?? null,
        "contact_address"       => $data["contact_address"]        ?? null,
        "site_name"             => $data["site_name"],
        "domain"                => $data["domain"]                 ?? null,
        "template_id"           => $data["template_id"]            ?? null,
        "notes"                 => $data["notes"]                  ?? null,
        "logo_url"              => $data["logo_url"]               ?? null,
        "event_date"            => $data["event_date"]             ?? null,
        "event_location"        => $data["event_location"]         ?? null,
        "primary_color"         => $data["primary_color"]          ?? null,
        "secondary_color"       => $data["secondary_color"]        ?? null,
        "accent_color"          => $data["accent_color"]           ?? null,
        "speakers"              => isset($data["speakers"])            ? json_encode($data["speakers"],            JSON_UNESCAPED_UNICODE) : null,
        "scientific_committee"  => isset($data["scientific_committee"]) ? json_encode($data["scientific_committee"],JSON_UNESCAPED_UNICODE) : null,
        "organizing_committee"  => isset($data["organizing_committee"]) ? json_encode($data["organizing_committee"],JSON_UNESCAPED_UNICODE) : null,
        "social_links"          => isset($data["social_links"])         ? json_encode($data["social_links"],         JSON_UNESCAPED_UNICODE) : null,
        "pages_requested"       => isset($data["pages_requested"])      ? json_encode($data["pages_requested"],      JSON_UNESCAPED_UNICODE) : null,
        "pages_content"         => isset($data["pages_content"])        ? json_encode($data["pages_content"],        JSON_UNESCAPED_UNICODE) : null,
        "inspiration_site_id"   => $data["inspiration_site_id"]    ?? null,
    ]);
    return $id;
}

function findOrCreateAssociation(string $name, ?string $email): string
{
    $pdo = db();
    if ($email !== null && $email !== "") {
        $stmt = $pdo->prepare("select id from associations where name = :name and contact_email = :email");
        $stmt->execute(["name" => $name, "email" => $email]);
    } else {
        $stmt = $pdo->prepare("select id from associations where name = :name and contact_email is null");
        $stmt->execute(["name" => $name]);
    }
    $id = $stmt->fetchColumn();
    if ($id) return $id;

    $id     = generateUuid();
    $insert = $pdo->prepare("insert into associations (id, name, contact_email) values (:id, :name, :email)");
    $insert->execute(["id" => $id, "name" => $name, "email" => $email ?: null]);
    return $id;
}

function createSiteFromForm(string $formId): ?string
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $form = fetchForm($formId);
        if (!$form || $form["status"] === "convertido") {
            $pdo->rollBack();
            return null;
        }

        $associationId = findOrCreateAssociation($form["association_name"], $form["contact_email"] ?? null);

        $siteId   = generateUuid();
        $siteStmt = $pdo->prepare(
            "insert into sites (id, association_id, template_id, name, status, plan, domain, notes, logo_url, primary_color, secondary_color, accent_color)
            values (:id, :association_id, :template_id, :name, 'rascunho', 'basico', :domain, :notes, :logo_url, :primary_color, :secondary_color, :accent_color)"
        );
        $siteStmt->execute([
            "id"              => $siteId,
            "association_id"  => $associationId,
            "template_id"     => $form["template_id"],
            "name"            => $form["site_name"],
            "domain"          => $form["domain"],
            "notes"           => $form["notes"],
            "logo_url"        => $form["logo_url"]         ?? null,
            "primary_color"   => $form["primary_color"]    ?? null,
            "secondary_color" => $form["secondary_color"]  ?? null,
            "accent_color"    => $form["accent_color"]     ?? null,
        ]);

        // Cria páginas a partir do que o cliente solicitou
        $pagesRequested = is_array($form["pages_requested"]) ? $form["pages_requested"] : [];
        $pagesContent   = is_array($form["pages_content"])   ? $form["pages_content"]   : [];

        $pageSlugToTitle = [
            "home"           => "Home",
            "sobre"          => "Sobre",
            "programacao"    => "Programação",
            "palestrantes"   => "Palestrantes",
            "comissoes"      => "Comissões",
            "trabalhos"      => "Submissão de Trabalhos",
            "inscricoes"     => "Inscrições",
            "hospedagem"     => "Hospedagem",
            "patrocinadores" => "Patrocinadores",
            "contato"        => "Contato",
            "faq"            => "FAQ",
            "galeria"        => "Galeria",
            "noticias"       => "Notícias",
            "transmissao"    => "Transmissão ao Vivo",
        ];

        $pageStmt = $pdo->prepare(
            "insert into site_pages (id, site_id, title, file, status, sort_order)
            values (:id, :site_id, :title, :file, :status, :sort_order)"
        );

        if (!empty($pagesRequested)) {
            foreach ($pagesRequested as $idx => $slug) {
                $title    = $pageSlugToTitle[$slug] ?? ucfirst($slug);
                $filename = $slug === "home" ? "index.html" : normalizeFileName($title);
                $bodyContent = "<h1>" . htmlspecialchars($title, ENT_QUOTES) . "</h1>";
                if (!empty($pagesContent[$slug])) {
                    $bodyContent .= "\n<p>" . nl2br(htmlspecialchars($pagesContent[$slug], ENT_QUOTES)) . "</p>";
                }
                file_put_contents(ensureSiteFolder($siteId) . "/" . $filename, buildPageHtmlForSite($siteId, $title, $bodyContent));
                $pageStmt->execute([
                    "id"         => generateUuid(),
                    "site_id"    => $siteId,
                    "title"      => $title,
                    "file"       => $filename,
                    "status"     => "rascunho",
                    "sort_order" => $idx,
                ]);
            }
        } else {
            // Fallback: cria apenas Home
            $pageStmt->execute([
                "id"         => generateUuid(),
                "site_id"    => $siteId,
                "title"      => "Home",
                "file"       => "index.html",
                "status"     => "rascunho",
                "sort_order" => 0,
            ]);
            createDefaultHomeFile($siteId, $form["site_name"]);
        }

        $pdo->prepare("update form_requests set status = 'convertido', converted_at = now() where id = :id")
            ->execute(["id" => $formId]);

        $pdo->commit();
        return $siteId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function createSiteFromTemplate(
    string  $associationName,
    ?string $contactEmail,
    string  $siteName,
    ?string $domain,
    ?string $templateId,
    ?string $notes
): string {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $associationId = findOrCreateAssociation($associationName, $contactEmail);
        $templateStmt  = $pdo->prepare("select default_pages from templates where id = :id");
        $templateStmt->execute(["id" => $templateId]);
        $pagesJson = $templateStmt->fetchColumn();

        $siteId   = generateUuid();
        $siteStmt = $pdo->prepare(
            "insert into sites (id, association_id, template_id, name, status, plan, domain, notes)
            values (:id, :association_id, :template_id, :name, 'rascunho', 'basico', :domain, :notes)"
        );
        $siteStmt->execute([
            "id"             => $siteId,
            "association_id" => $associationId,
            "template_id"    => $templateId,
            "name"           => $siteName,
            "domain"         => $domain ?: null,
            "notes"          => $notes  ?: null,
        ]);

        $pages    = json_decode($pagesJson ?: "[]", true);
        if (!is_array($pages)) $pages = [];
        $pageStmt = $pdo->prepare(
            "insert into site_pages (id, site_id, title, file, sort_order)
            values (:id, :site_id, :title, :file, :sort_order)"
        );
        foreach ($pages as $index => $page) {
            $pageStmt->execute([
                "id"         => generateUuid(),
                "site_id"    => $siteId,
                "title"      => $page["title"],
                "file"       => $page["file"],
                "sort_order" => $index,
            ]);
        }
        $pdo->commit();
        return $siteId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function createPageForSite(string $siteId, string $title, string $status): void
{
    $filename = createPageFile($siteId, $title);
    $stmt     = db()->prepare(
        "insert into site_pages (id, site_id, title, file, status, sort_order)
        values (:id, :site_id, :title, :file, :status, :sort_order)"
    );
    $stmt->execute([
        "id"         => generateUuid(),
        "site_id"    => $siteId,
        "title"      => $title,
        "file"       => $filename,
        "status"     => $status,
        "sort_order" => 0,
    ]);
}

// ── Edição ───────────────────────────────────────────────────

function updateSiteName(string $siteId, string $name): void
{
    $stmt = db()->prepare("update sites set name = :name where id = :id");
    $stmt->execute(["name" => $name, "id" => $siteId]);
}

function updateSiteStatus(string $siteId, string $status): void
{
    $stmt = db()->prepare("update sites set status = :status where id = :id");
    $stmt->execute(["status" => $status, "id" => $siteId]);
}

function updatePageStatus(string $pageId, string $status): void
{
    $stmt = db()->prepare("update site_pages set status = :status where id = :id");
    $stmt->execute(["status" => $status, "id" => $pageId]);
}

function updatePageTitle(string $pageId, string $title): void
{
    $stmt = db()->prepare("update site_pages set title = :title where id = :id");
    $stmt->execute(["title" => $title, "id" => $pageId]);
}

// ── Duplicação & exclusão ────────────────────────────────────

function duplicatePage(string $pageId): string
{
    $page   = fetchSitePage($pageId);
    if (!$page) return "";
    $siteId = $page["site_id"];
    $dir    = ensureSiteFolder($siteId);

    $sourceFile  = $dir . "/" . basename($page["file"]);
    $newTitle    = $page["title"] . " - cópia";
    $newFileName = normalizeFileName($newTitle);
    $newFileName = ensureUniqueFile($dir, $newFileName);
    $newFile     = $dir . "/" . $newFileName;

    if (file_exists($sourceFile)) {
        copy($sourceFile, $newFile);
    } else {
        file_put_contents($newFile, buildPageHtmlForSite($siteId, $newTitle, "<h1>" . htmlspecialchars($newTitle, ENT_QUOTES) . "</h1>"));
    }

    $newId = generateUuid();
    db()->prepare(
        "insert into site_pages (id, site_id, title, file, status, sort_order)
        values (:id, :site_id, :title, :file, :status, :sort_order)"
    )->execute([
        "id"         => $newId,
        "site_id"    => $siteId,
        "title"      => $newTitle,
        "file"       => $newFileName,
        "status"     => $page["status"],
        "sort_order" => 0,
    ]);
    return $newId;
}

function deletePage(string $pageId): void
{
    $page = fetchSitePage($pageId);
    if (!$page) return;
    $path = ensureSiteFolder($page["site_id"]) . "/" . basename($page["file"]);
    if (file_exists($path)) unlink($path);
    db()->prepare("delete from site_pages where id = :id")->execute(["id" => $pageId]);
}

function deleteSite(string $siteId): void
{
    db()->prepare("delete from sites where id = :id")->execute(["id" => $siteId]);
    deleteDirectory(sitesRoot() . "/" . $siteId);
}

function deleteForm(string $formId): void
{
    db()->prepare("delete from form_requests where id = :id")->execute(["id" => $formId]);
}

// ── Criação de arquivos ──────────────────────────────────────

function createDefaultHomeFile(string $siteId, string $siteName): void
{
    $dir  = ensureSiteFolder($siteId);
    $path = $dir . "/index.html";
    if (file_exists($path)) return;
    file_put_contents($path, buildPageHtmlForSite($siteId, $siteName, "<h1>" . htmlspecialchars($siteName, ENT_QUOTES) . "</h1>"));
}

function createPageFile(string $siteId, string $title): string
{
    $dir      = ensureSiteFolder($siteId);
    $filename = normalizeFileName($title);
    $filename = ensureUniqueFile($dir, $filename);
    file_put_contents($dir . "/" . $filename, buildPageHtmlForSite($siteId, $title, "<h1>" . htmlspecialchars($title, ENT_QUOTES) . "</h1>"));
    return $filename;
}

function fetchSiteByDomain(string $host): ?array
{
    $pdo = db();

    // Match exato
    $stmt = $pdo->prepare(
        "select
            s.id, s.name, s.status, s.domain,
            s.logo_url, s.primary_color, s.secondary_color, s.accent_color,
            a.name as owner
         from sites s
         join associations a on a.id = s.association_id
         where s.domain = :domain
           and s.status = 'ativo'
         limit 1"
    );
    $stmt->execute(['domain' => $host]);
    $row = $stmt->fetch();
    if ($row) return $row;

    // Tenta sem www (ex: www.congresso.org.br → congresso.org.br)
    $withoutWww = preg_replace('/^www\./i', '', $host);
    if ($withoutWww !== $host) {
        $stmt->execute(['domain' => $withoutWww]);
        $row = $stmt->fetch();
        if ($row) return $row;
    }

    // Tenta com www (ex: congresso.org.br → www.congresso.org.br)
    $withWww = 'www.' . ltrim($host, 'w.');
    if ($withWww !== $host) {
        $stmt->execute(['domain' => $withWww]);
        $row = $stmt->fetch();
        if ($row) return $row;
    }

    return null;
}

// ── Autenticação de usuários ─────────────────────────────────

function findUserByEmail(string $email): ?array
{
    $stmt = db()->prepare("select * from users where email = :email limit 1");
    $stmt->execute(["email" => $email]);
    return $stmt->fetch() ?: null;
}

function findUserByToken(string $field, string $token): ?array
{
    $allowed = ["verification_token", "reset_token"];
    if (!in_array($field, $allowed, true)) return null;
    $stmt = db()->prepare("select * from users where {$field} = :token limit 1");
    $stmt->execute(["token" => $token]);
    return $stmt->fetch() ?: null;
}

function findUserById(string $id): ?array
{
    $stmt = db()->prepare("select id, name, email, email_verified_at, created_at from users where id = :id limit 1");
    $stmt->execute(["id" => $id]);
    return $stmt->fetch() ?: null;
}

function createUser(string $name, string $email, string $password): array
{
    $id    = generateUuid();
    $token = bin2hex(random_bytes(32));
    $hash  = password_hash($password, PASSWORD_DEFAULT);
    db()->prepare(
        "insert into users (id, name, email, password, verification_token) values (:id, :name, :email, :password, :token)"
    )->execute(["id" => $id, "name" => $name, "email" => $email, "password" => $hash, "token" => $token]);
    return ["id" => $id, "name" => $name, "email" => $email, "verification_token" => $token];
}

function verifyUserEmail(string $token): bool
{
    $user = findUserByToken("verification_token", $token);
    if (!$user) return false;
    db()->prepare(
        "update users set email_verified_at = now(), verification_token = null where id = :id"
    )->execute(["id" => $user["id"]]);
    return true;
}

function generatePasswordResetToken(string $email): ?string
{
    $user = findUserByEmail($email);
    if (!$user) return null;
    $token = bin2hex(random_bytes(32));
    db()->prepare(
        "update users set reset_token = :token, reset_token_expires_at = date_add(now(), interval 2 hour) where id = :id"
    )->execute(["token" => $token, "id" => $user["id"]]);
    return $token;
}

function resetUserPassword(string $token, string $newPassword): bool
{
    $user = findUserByToken("reset_token", $token);
    if (!$user) return false;
    if ($user["reset_token_expires_at"] && strtotime($user["reset_token_expires_at"]) < time()) return false;
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    db()->prepare(
        "update users set password = :password, reset_token = null, reset_token_expires_at = null where id = :id"
    )->execute(["password" => $hash, "id" => $user["id"]]);
    return true;
}

function changeUserPassword(string $userId, string $currentPassword, string $newPassword): bool
{
    $stmt = db()->prepare("select password from users where id = :id");
    $stmt->execute(["id" => $userId]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($currentPassword, $row["password"])) return false;
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    db()->prepare("update users set password = :password where id = :id")
        ->execute(["password" => $hash, "id" => $userId]);
    return true;
}

/**
 * Atualiza o domínio de um site.
 * Remove http://, https://, www. e trailing slash automaticamente.
 */
function updateSiteDomain(string $siteId, string $domain): void
{
    // Limpa a URL
    $domain = preg_replace('#^https?://#i', '', $domain);
    $domain = preg_replace('#^www\.#i', '', $domain);
    $domain = rtrim($domain, '/');
    $domain = strtolower(trim($domain));

    $stmt = db()->prepare("update sites set domain = :domain where id = :id");
    $stmt->execute(['domain' => $domain ?: null, 'id' => $siteId]);
}

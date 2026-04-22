<?php
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
    $dsn = "mysql:host={$host};" . ($port !== "" ? "port={$port};" : "") . "dbname={$name};charset=utf8mb4";

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    ensureAllTables($pdo);

    return $pdo;
}

function ensureAllTables(PDO $pdo): void
{
    $statements = [
        "create table if not exists associations (
            id char(36) not null,
            name text not null,
            contact_email text,
            created_at timestamp not null default current_timestamp,
            primary key (id)
        ) engine=InnoDB charset=utf8mb4",

        "create table if not exists templates (
            id char(36) not null,
            name text not null,
            description text,
            default_pages json not null,
            created_at timestamp not null default current_timestamp,
            primary key (id)
        ) engine=InnoDB charset=utf8mb4",

        "create table if not exists sites (
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
            is_inspiration tinyint(1) not null default 0,
            created_at timestamp not null default current_timestamp,
            primary key (id)
        ) engine=InnoDB charset=utf8mb4",

        "create table if not exists site_pages (
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

        "create table if not exists form_requests (
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
            inspiration_link text,
            status text not null default 'novo',
            created_at timestamp not null default current_timestamp,
            converted_at timestamp null,
            primary key (id)
        ) engine=InnoDB charset=utf8mb4",

        "create table if not exists users (
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
    ];

    foreach ($statements as $sql) {
        try {
            $pdo->exec($sql);
        } catch (Throwable $e) {
            error_log("ensureAllTables failed: " . $e->getMessage());
        }
    }
}

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

function generateUuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

    return vsprintf("%s%s-%s-%s-%s-%s%s%s", str_split(bin2hex($data), 4));
}

function sitesRoot(): string
{
    return dirname(__DIR__) . "/sites";
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
    $dir = siteSharedDir($siteId);
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

function buildPageHtmlForSite(string $siteId, string $title, string $bodyContent): string
{
    ensureSiteSharedDefaults($siteId);

    return "<!DOCTYPE html>\n" .
        "<html lang=\"pt-br\">\n" .
        "<head>\n" .
        "  <meta charset=\"utf-8\" />\n" .
        "  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\" />\n" .
        "  <title>" . htmlspecialchars($title, ENT_QUOTES) . "</title>\n" .
        "  <style>\n" .
        "    body{margin:0;font-family:Arial,sans-serif;}\n" .
        "    .container{max-width:1100px;margin:0 auto;padding:0 20px;}\n" .
        "    .site-header{background:#0b1f3a;color:#fff;padding:16px 0;}\n" .
        "    .site-header .menu a{color:#fff;text-decoration:none;margin-left:12px;}\n" .
        "    .site-footer{background:#f1f5f9;color:#0f172a;padding:16px 0;margin-top:40px;}\n" .
        "    main{padding:28px 0;}\n" .
        "  </style>\n" .
        "</head>\n" .
        "<body>\n" .
        "  <main class=\"container\">\n" .
        "    " . $bodyContent . "\n" .
        "  </main>\n" .
        "</body>\n" .
        "</html>\n";
}

function normalizeFileName(string $name): string
{
    $name = strtolower(trim($name));
    $name = preg_replace("/[^a-z0-9]+/", "-", $name);
    $name = trim($name, "-");

    if ($name === "") {
        $name = "pagina";
    }

    if (substr($name, -5) !== ".html") {
        $name .= ".html";
    }

    return $name;
}

function ensureUniqueFile(string $dir, string $filename): string
{
    $base = $filename;
    $ext = "";

    if (substr($filename, -5) === ".html") {
        $base = substr($filename, 0, -5);
        $ext = ".html";
    }

    $candidate = $base . $ext;
    $counter = 2;

    while (file_exists($dir . "/" . $candidate)) {
        $candidate = $base . "-" . $counter . $ext;
        $counter++;
    }

    return $candidate;
}

function ensureSiteFolder(string $siteId): string
{
    $dir = sitesRoot() . "/" . $siteId;
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    return $dir;
}

function deleteDirectory(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $items = scandir($dir);
    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === "." || $item === "..") {
            continue;
        }
        $path = $dir . "/" . $item;
        if (is_dir($path)) {
            deleteDirectory($path);
        } else {
            unlink($path);
        }
    }

    rmdir($dir);
}

function createDefaultHomeFile(string $siteId, string $siteName): void
{
    $dir = ensureSiteFolder($siteId);
    $path = $dir . "/index.html";

    if (file_exists($path)) {
        return;
    }

    $content = buildPageHtmlForSite($siteId, $siteName, "<h1>" . htmlspecialchars($siteName, ENT_QUOTES) . "</h1>");

    file_put_contents($path, $content);
}

function createPageFile(string $siteId, string $title): string
{
    $dir = ensureSiteFolder($siteId);
    $filename = normalizeFileName($title);
    $filename = ensureUniqueFile($dir, $filename);
    $path = $dir . "/" . $filename;

    $content = buildPageHtmlForSite($siteId, $title, "<h1>" . htmlspecialchars($title, ENT_QUOTES) . "</h1>");

    file_put_contents($path, $content);

    return $filename;
}

function fetchStats(): array
{
    try {
        $stmt = db()->query(
            "select\n            (select count(*) from associations) as associations,\n            (select count(*) from site_pages) as pages,\n            (select count(*) from sites where status = 'ativo') as active"
        );
        $row = $stmt->fetch() ?: ["associations" => 0, "pages" => 0, "active" => 0];

        return [
            "organizacoes" => (int) $row["associations"],
            "eventos" => (int) $row["pages"],
            "ativos" => (int) $row["active"],
        ];
    } catch (Throwable $e) {
        error_log("fetchStats failed: " . $e->getMessage());

        return [
            "organizacoes" => 0,
            "eventos" => 0,
            "ativos" => 0,
        ];
    }
}

function fetchSites(): array
{
    $sql = "select\n            s.id,\n            s.name,\n            s.status,\n            s.plan,\n            s.created_at,\n            a.name as owner,\n            t.name as template,\n            (select count(*) from site_pages sp where sp.site_id = s.id) as page_count\n        from sites s\n        join associations a on a.id = s.association_id\n        left join templates t on t.id = s.template_id\n        order by s.created_at desc, s.name";

    return db()->query($sql)->fetchAll();
}

function fetchTemplates(): array
{
    return db()->query("select id, name, description from templates order by name")->fetchAll();
}

function fetchSite(string $id): ?array
{
    $stmt = db()->prepare(
        "select\n            s.id,\n            s.name,\n            s.status,\n            s.plan,\n            s.domain,\n            s.notes,\n            s.created_at,\n            a.name as owner,\n            a.contact_email,\n            t.name as template\n        from sites s\n        join associations a on a.id = s.association_id\n        left join templates t on t.id = s.template_id\n        where s.id = :id"
    );
    $stmt->execute(["id" => $id]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function fetchSitePages(string $siteId): array
{
    $stmt = db()->prepare(
        "select id, title, file, status\n        from site_pages\n        where site_id = :site_id\n        order by sort_order, title"
    );
    $stmt->execute(["site_id" => $siteId]);

    return $stmt->fetchAll();
}

function fetchSitePage(string $pageId): ?array
{
    $stmt = db()->prepare(
        "select id, site_id, title, file, status\n        from site_pages\n        where id = :id"
    );
    $stmt->execute(["id" => $pageId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function fetchForms(): array
{
    $sql = "select\n            f.id,\n            f.association_name,\n            f.site_name,\n            f.contact_email,\n            f.contact_phone,\n            f.domain,\n            f.event_date,\n            f.event_location,\n            f.primary_color,\n            f.logo_url,\n            f.pages_requested,\n            f.inspiration_link,\n            f.status,\n            f.created_at,\n            t.name as template,\n            si.name as inspiration_site_name\n        from form_requests f\n        left join templates t on t.id = f.template_id\n        left join sites si on si.id = f.inspiration_site_id\n        order by f.created_at desc";

    return db()->query($sql)->fetchAll();
}

function fetchForm(string $id): ?array
{
    $stmt = db()->prepare(
        "select\n            f.id,\n            f.association_name,\n            f.contact_email,\n            f.contact_phone,\n            f.contact_address,\n            f.site_name,\n            f.domain,\n            f.template_id,\n            f.notes,\n            f.logo_url,\n            f.event_date,\n            f.event_location,\n            f.primary_color,\n            f.secondary_color,\n            f.accent_color,\n            f.speakers,\n            f.scientific_committee,\n            f.organizing_committee,\n            f.social_links,\n            f.pages_requested,\n            f.pages_content,\n            f.inspiration_site_id,\n            f.inspiration_link,\n            f.status,\n            f.created_at,\n            t.name as template,\n            si.name as inspiration_site_name\n        from form_requests f\n        left join templates t on t.id = f.template_id\n        left join sites si on si.id = f.inspiration_site_id\n        where f.id = :id"
    );
    $stmt->execute(["id" => $id]);
    $row = $stmt->fetch();

    if (!$row) {
        return null;
    }

    foreach (["speakers", "scientific_committee", "organizing_committee", "social_links", "pages_requested", "pages_content"] as $col) {
        if (isset($row[$col]) && is_string($row[$col])) {
            $row[$col] = json_decode($row[$col], true) ?: [];
        }
    }

    return $row;
}

function createFormRequest(array $data): string
{
    $id = generateUuid();
    $stmt = db()->prepare(
        "insert into form_requests\n            (id, association_name, contact_email, contact_phone, contact_address,\n             site_name, domain, template_id, notes,\n             logo_url, event_date, event_location,\n             primary_color, secondary_color, accent_color,\n             speakers, scientific_committee, organizing_committee,\n             social_links, pages_requested, pages_content, inspiration_site_id, inspiration_link)\n        values\n            (:id, :association_name, :contact_email, :contact_phone, :contact_address,\n             :site_name, :domain, :template_id, :notes,\n             :logo_url, :event_date, :event_location,\n             :primary_color, :secondary_color, :accent_color,\n             :speakers, :scientific_committee, :organizing_committee,\n             :social_links, :pages_requested, :pages_content, :inspiration_site_id, :inspiration_link)"
    );
    $stmt->execute([
        "id" => $id,
        "association_name" => $data["association_name"],
        "contact_email" => $data["contact_email"] ?? null,
        "contact_phone" => $data["contact_phone"] ?? null,
        "contact_address" => $data["contact_address"] ?? null,
        "site_name" => $data["site_name"],
        "domain" => $data["domain"] ?? null,
        "template_id" => $data["template_id"] ?? null,
        "notes" => $data["notes"] ?? null,
        "logo_url" => $data["logo_url"] ?? null,
        "event_date" => $data["event_date"] ?? null,
        "event_location" => $data["event_location"] ?? null,
        "primary_color" => $data["primary_color"] ?? null,
        "secondary_color" => $data["secondary_color"] ?? null,
        "accent_color" => $data["accent_color"] ?? null,
        "speakers" => isset($data["speakers"]) ? json_encode($data["speakers"], JSON_UNESCAPED_UNICODE) : null,
        "scientific_committee" => isset($data["scientific_committee"]) ? json_encode($data["scientific_committee"], JSON_UNESCAPED_UNICODE) : null,
        "organizing_committee" => isset($data["organizing_committee"]) ? json_encode($data["organizing_committee"], JSON_UNESCAPED_UNICODE) : null,
        "social_links" => isset($data["social_links"]) ? json_encode($data["social_links"], JSON_UNESCAPED_UNICODE) : null,
        "pages_requested" => isset($data["pages_requested"]) ? json_encode($data["pages_requested"], JSON_UNESCAPED_UNICODE) : null,
        "pages_content" => isset($data["pages_content"]) ? json_encode($data["pages_content"], JSON_UNESCAPED_UNICODE) : null,
        "inspiration_site_id" => $data["inspiration_site_id"] ?? null,
        "inspiration_link" => $data["inspiration_link"] ?? null,
    ]);

    return $id;
}

function updateSiteStatus(string $siteId, string $status): void
{
    $stmt = db()->prepare("update sites set status = :status where id = :id");
    $stmt->execute(["status" => $status, "id" => $siteId]);
}

function updateSiteName(string $siteId, string $name): void
{
    $stmt = db()->prepare("update sites set name = :name where id = :id");
    $stmt->execute(["name" => $name, "id" => $siteId]);
}

function updatePageStatus(string $pageId, string $status): void
{
    $stmt = db()->prepare("update site_pages set status = :status where id = :id");
    $stmt->execute(["status" => $status, "id" => $pageId]);
}

function renamePage(string $pageId, string $newTitle): ?array
{
    $page = fetchSitePage($pageId);
    if (!$page) {
        return null;
    }

    $siteId = $page["site_id"];
    $dir = ensureSiteFolder($siteId);
    $currentFile = $dir . "/" . basename($page["file"]);
    $newFileName = normalizeFileName($newTitle);
    $newFileName = ensureUniqueFile($dir, $newFileName);
    $newFile = $dir . "/" . $newFileName;

    if ($newFileName !== $page["file"] && file_exists($currentFile)) {
        rename($currentFile, $newFile);
    }

    $stmt = db()->prepare("update site_pages set title = :title, file = :file where id = :id");
    $stmt->execute([
        "title" => $newTitle,
        "file" => $newFileName,
        "id" => $pageId,
    ]);

    return fetchSitePage($pageId);
}

function duplicatePage(string $pageId): ?string
{
    $page = fetchSitePage($pageId);
    if (!$page) {
        return null;
    }

    $siteId = $page["site_id"];
    $dir = ensureSiteFolder($siteId);
    $sourceFile = $dir . "/" . basename($page["file"]);

    $newTitle = $page["title"] . " - copia";
    $newFileName = normalizeFileName($newTitle);
    $newFileName = ensureUniqueFile($dir, $newFileName);
    $newFile = $dir . "/" . $newFileName;

    if (file_exists($sourceFile)) {
        copy($sourceFile, $newFile);
    } else {
        $content = buildPageHtmlForSite($siteId, $newTitle, "<h1>" . htmlspecialchars($newTitle, ENT_QUOTES) . "</h1>");
        file_put_contents($newFile, $content);
    }

    $newId = generateUuid();
    $stmt = db()->prepare(
        "insert into site_pages (id, site_id, title, file, status, sort_order)\n        values (:id, :site_id, :title, :file, :status, :sort_order)"
    );
    $stmt->execute([
        "id" => $newId,
        "site_id" => $siteId,
        "title" => $newTitle,
        "file" => $newFileName,
        "status" => $page["status"],
        "sort_order" => 0,
    ]);

    return $newId;
}

function deletePage(string $pageId): void
{
    $page = fetchSitePage($pageId);
    if (!$page) {
        return;
    }

    $dir = ensureSiteFolder($page["site_id"]);
    $path = $dir . "/" . basename($page["file"]);
    if (file_exists($path)) {
        unlink($path);
    }

    $stmt = db()->prepare("delete from site_pages where id = :id");
    $stmt->execute(["id" => $pageId]);
}

function deleteSite(string $siteId): void
{
    $stmt = db()->prepare("delete from sites where id = :id");
    $stmt->execute(["id" => $siteId]);

    $dir = sitesRoot() . "/" . $siteId;
    deleteDirectory($dir);
}

function deleteForm(string $formId): void
{
    $stmt = db()->prepare("delete from form_requests where id = :id");
    $stmt->execute(["id" => $formId]);
}

function createSiteFromForm(string $formId): ?string
{
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $form = fetchForm($formId);
        if (!$form) {
            $pdo->rollBack();
            return null;
        }

        if ($form["status"] === "convertido") {
            $pdo->rollBack();
            return null;
        }

        $associationId = findOrCreateAssociation($form["association_name"], $form["contact_email"] ?? null);

        $siteId = generateUuid();
        $siteStmt = $pdo->prepare(
            "insert into sites (id, association_id, template_id, name, status, plan, domain, notes)\n            values (:id, :association_id, :template_id, :name, 'rascunho', 'basico', :domain, :notes)"
        );
        $siteStmt->execute([
            "id" => $siteId,
            "association_id" => $associationId,
            "template_id" => $form["template_id"],
            "name" => $form["site_name"],
            "domain" => $form["domain"],
            "notes" => $form["notes"],
        ]);

        $pageStmt = $pdo->prepare(
            "insert into site_pages (id, site_id, title, file, status, sort_order)\n            values (:id, :site_id, :title, :file, :status, :sort_order)"
        );
        $pageStmt->execute([
            "id" => generateUuid(),
            "site_id" => $siteId,
            "title" => "Home",
            "file" => "index.html",
            "status" => "rascunho",
            "sort_order" => 0,
        ]);

        $update = $pdo->prepare("update form_requests set status = 'convertido', converted_at = now() where id = :id");
        $update->execute(["id" => $formId]);

        $pdo->commit();

        createDefaultHomeFile($siteId, $form["site_name"]);

        return $siteId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function createPageForSite(string $siteId, string $title, string $status): void
{
    $filename = createPageFile($siteId, $title);

    $stmt = db()->prepare(
        "insert into site_pages (id, site_id, title, file, status, sort_order)\n        values (:id, :site_id, :title, :file, :status, :sort_order)"
    );
    $stmt->execute([
        "id" => generateUuid(),
        "site_id" => $siteId,
        "title" => $title,
        "file" => $filename,
        "status" => $status,
        "sort_order" => 0,
    ]);
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
    if ($id) {
        return $id;
    }

    $id = generateUuid();
    $insert = $pdo->prepare("insert into associations (id, name, contact_email) values (:id, :name, :email)");
    $insert->execute(["id" => $id, "name" => $name, "email" => $email ?: null]);

    return $id;
}

function createSiteFromTemplate(
    string $associationName,
    ?string $contactEmail,
    string $siteName,
    ?string $domain,
    ?string $templateId,
    ?string $notes
): string {
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $associationId = findOrCreateAssociation($associationName, $contactEmail);

        $templateStmt = $pdo->prepare("select default_pages from templates where id = :id");
        $templateStmt->execute(["id" => $templateId]);
        $pagesJson = $templateStmt->fetchColumn();

        $siteId = generateUuid();
        $siteStmt = $pdo->prepare(
            "insert into sites (id, association_id, template_id, name, status, plan, domain, notes)\n            values (:id, :association_id, :template_id, :name, 'rascunho', 'basico', :domain, :notes)"
        );
        $siteStmt->execute([
            "id" => $siteId,
            "association_id" => $associationId,
            "template_id" => $templateId,
            "name" => $siteName,
            "domain" => $domain ?: null,
            "notes" => $notes ?: null,
        ]);

        $pages = json_decode($pagesJson ?: "[]", true);
        if (!is_array($pages)) {
            $pages = [];
        }

        $pageStmt = $pdo->prepare(
            "insert into site_pages (id, site_id, title, file, sort_order)\n            values (:id, :site_id, :title, :file, :sort_order)"
        );
        foreach ($pages as $index => $page) {
            $pageStmt->execute([
                "id" => generateUuid(),
                "site_id" => $siteId,
                "title" => $page["title"],
                "file" => $page["file"],
                "sort_order" => $index,
            ]);
        }

        $pdo->commit();

        return $siteId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

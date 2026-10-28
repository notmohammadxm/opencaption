<?php
declare(strict_types=1);

/**
 * OpenCaption — cPanel-compatible starter application.
 * Requires PHP 8.1+, PDO MySQL, Fileinfo, and MySQL/MariaDB.
 */
$configFile = __DIR__ . '/config.php';
if (!is_file($configFile)) {
    http_response_code(503);
    ?><!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>راه‌اندازی OpenCaption</title><style>body{margin:0;background:#101010;color:#f8f5f0;font:16px/1.9 system-ui,sans-serif;display:grid;place-items:center;min-height:100vh;padding:24px}.card{max-width:700px;background:#1c1b19;border:1px solid #39342e;border-radius:24px;padding:32px;box-shadow:0 18px 0 #090909}h1{color:#ff9c45}code{direction:ltr;display:inline-block;background:#090909;padding:3px 8px;border-radius:6px}</style><main class="card"><h1>راه‌اندازی OpenCaption</h1><p>فایل پیکربندی هنوز ساخته نشده است. این مراحل را در cPanel انجام دهید:</p><ol><li>یک دیتابیس MySQL و کاربر دیتابیس بسازید و دسترسی کامل را به کاربر بدهید.</li><li>فایل <code>database/schema.sql</code> را در phpMyAdmin وارد کنید.</li><li>از <code>config.example.php</code> یک کپی با نام <code>config.php</code> بسازید و مشخصات دیتابیس را وارد کنید.</li><li>اطمینان حاصل کنید پوشه <code>storage/uploads</code> برای PHP قابل نوشتن است.</li></ol><p>تا قبل از تکمیل این تنظیمات، برنامه هیچ اتصال یا عملیات دیتابیسی انجام نمی‌دهد.</p></main></html><?php
    exit;
}
$config = require $configFile;
date_default_timezone_set((string)($config['timezone'] ?? 'Asia/Tehran'));
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_name((string)($config['session_name'] ?? 'opencaption_session'));
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$isHttps,'httponly'=>true,'samesite'=>'Lax']);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

function db(): PDO {
    static $pdo = null;
    global $config;
    if ($pdo instanceof PDO) return $pdo;
    $d = $config['database'];
    $dsn = 'mysql:host='.$d['host'].';dbname='.$d['name'].';charset='.($d['charset'] ?? 'utf8mb4');
    $pdo = new PDO($dsn, (string)$d['user'], (string)$d['password'], [
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>false,
    ]);
    return $pdo;
}
function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function go(string $url): never { header('Location: '.$url); exit; }
function csrf(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return (string)$_SESSION['csrf']; }
function verify_csrf(): void {
    $given = (string)($_POST['_csrf'] ?? '');
    if ($given === '' || !hash_equals((string)($_SESSION['csrf'] ?? ''), $given)) {
        http_response_code(419); exit('درخواست منقضی یا نامعتبر است. صفحه را تازه‌سازی کنید.');
    }
}
function current_user(): ?array { return isset($_SESSION['user']) && is_array($_SESSION['user']) ? $_SESSION['user'] : null; }
function require_user(): array { $u=current_user(); if (!$u) go('?page=login'); return $u; }
function flash(string $message, string $type='info'): void { $_SESSION['flash']=['message'=>$message,'type'=>$type]; }
function take_flash(): ?array { $f=$_SESSION['flash'] ?? null; unset($_SESSION['flash']); return is_array($f)?$f:null; }
function owned_project(int $id, int $userId): array {
    $q=db()->prepare('SELECT * FROM projects WHERE id=? AND user_id=?'); $q->execute([$id,$userId]); $p=$q->fetch();
    if (!$p) { http_response_code(404); exit('پروژه پیدا نشد.'); }
    return $p;
}
function fmt_time(float $seconds): string { $s=max(0,(int)floor($seconds)); return sprintf('%02d:%02d:%02d',$s/3600,($s%3600)/60,$s%60); }
function srt_time(float $seconds): string { $ms=(int)round(max(0,$seconds)*1000); return sprintf('%02d:%02d:%02d,%03d',intdiv($ms,3600000),intdiv($ms%3600000,60000),intdiv($ms%60000,1000),$ms%1000); }
function layout_start(string $title, string $active='projects'): void {
    $u=current_user(); $f=take_flash(); ?>
<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#11100f"><title><?=e($title)?> — OpenCaption</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap" rel="stylesheet"><style>
:root{color-scheme:dark;--bg:#11100f;--panel:#1b1917;--panel2:#211e1b;--line:#37312b;--text:#f6f1ea;--muted:#a49a90;--orange:#ff963d;--orange2:#d96b1d;--green:#8ed6a4;--red:#ff8b7d;--shadow:0 5px 0 #080808,0 12px 30px #0005}*{box-sizing:border-box}body{margin:0;background:radial-gradient(ellipse at 90% -20%,#4a29151c,transparent 38%),var(--bg);color:var(--text);font-family:Vazirmatn,system-ui,sans-serif;font-size:14px;line-height:1.8}a{color:inherit;text-decoration:none}button,input,select,textarea{font:inherit}button,.btn{border:1px solid #59402c;background:linear-gradient(#ffad5d,#f18a32);color:#201309;padding:10px 16px;border-radius:12px;font-weight:800;cursor:pointer;box-shadow:0 4px 0 #874316,0 8px 18px #0003;transition:transform .16s,filter .16s,box-shadow .16s;display:inline-flex;align-items:center;justify-content:center;gap:8px}button:hover,.btn:hover{filter:brightness(1.08);transform:translateY(-2px);box-shadow:0 6px 0 #874316,0 12px 22px #0004}button:active,.btn:active{transform:translateY(2px);box-shadow:0 1px 0 #874316}button.secondary,.btn.secondary{background:linear-gradient(#302c28,#211f1c);color:var(--text);border-color:#47403a;box-shadow:0 4px 0 #080808}button.danger{background:linear-gradient(#67302a,#4a201c);color:#ffe9e4;border-color:#8d4035;box-shadow:0 4px 0 #1e0b09}.shell{max-width:1440px;margin:auto;padding:24px;display:grid;grid-template-columns:238px minmax(0,1fr);gap:24px;min-height:100vh}.sidebar{background:linear-gradient(145deg,#211e1b,#171614);border:1px solid var(--line);border-radius:22px;padding:20px;height:calc(100vh - 48px);position:sticky;top:24px;display:flex;flex-direction:column;box-shadow:var(--shadow)}.brand{display:flex;align-items:center;gap:12px;font-weight:800;font-size:18px;margin-bottom:30px}.brandmark{width:40px;height:40px;border-radius:13px;display:grid;place-items:center;color:#211207;background:linear-gradient(145deg,#ffbd76,#ef7923);box-shadow:inset 0 2px 1px #fff8,0 4px 0 #874316;font-size:21px}.navlabel{color:#776d63;font-size:11px;font-weight:800;margin:14px 10px 7px}.nav a{display:flex;align-items:center;gap:12px;color:#b7aca1;padding:11px 12px;border-radius:11px;margin:4px 0;transition:.15s}.nav a:hover,.nav a.active{background:#ff983d18;color:#ffad62;border:1px solid #ff983d30}.nav .ico{width:20px;text-align:center;font-size:17px}.sidebottom{margin-top:auto;border-top:1px solid var(--line);padding-top:16px}.profile{display:flex;align-items:center;gap:10px;padding:10px}.avatar{width:36px;height:36px;display:grid;place-items:center;border-radius:12px;background:#50311e;color:#ffc184;font-weight:800}.profile small{display:block;color:var(--muted)}main{min-width:0}.topbar{display:flex;align-items:center;justify-content:space-between;gap:14px;margin:0 0 28px}.crumb{color:var(--muted);font-size:12px}.topbar h1{font-size:25px;margin:4px 0 0;letter-spacing:-.5px}.topactions{display:flex;gap:10px;align-items:center}.iconbtn{width:42px;height:42px;padding:0;background:linear-gradient(#2a2723,#1d1b18);color:var(--text);border:1px solid var(--line);box-shadow:0 3px 0 #070707}.panel{background:linear-gradient(145deg,#201d1a,#191715);border:1px solid var(--line);border-radius:18px;padding:22px;box-shadow:0 12px 28px #0002}.grid{display:grid;gap:16px}.stats{grid-template-columns:repeat(3,minmax(0,1fr));margin-bottom:22px}.stat{display:flex;align-items:center;gap:14px}.staticon{width:46px;height:46px;border-radius:14px;background:#ff9b3b18;border:1px solid #ff9b3b30;display:grid;place-items:center;font-size:20px}.stat strong{display:block;font-size:24px;line-height:1.3}.muted{color:var(--muted)}.sectionhead{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:16px}.sectionhead h2{font-size:17px;margin:0}.projects{grid-template-columns:repeat(2,minmax(0,1fr))}.projectcard{overflow:hidden;padding:0}.thumb{height:150px;background:radial-gradient(circle at 70% 20%,#b35c2c55,transparent 35%),linear-gradient(135deg,#30241b,#121110);display:grid;place-items:center;border-bottom:1px solid var(--line);position:relative}.playmark{width:48px;height:48px;border-radius:50%;display:grid;place-items:center;background:#ff9b3d20;border:1px solid #ffb36b65;color:#ffb36b;font-size:19px}.status{font-size:11px;border-radius:999px;padding:4px 9px;background:#ffffff0c;border:1px solid var(--line);color:#cfc5ba}.thumb .status{position:absolute;top:12px;right:12px}.projectbody{padding:17px}.projectbody h3{margin:0 0 5px;font-size:15px}.meta{display:flex;gap:14px;flex-wrap:wrap;color:var(--muted);font-size:12px}.projectfoot{display:flex;align-items:center;justify-content:space-between;margin-top:16px;gap:10px}.field{display:grid;gap:7px;margin-bottom:16px}.field label{color:#c9beb2;font-size:13px;font-weight:600}.field input,.field select,.field textarea,input,select,textarea{width:100%;background:#11100f;color:var(--text);border:1px solid #403a34;border-radius:11px;padding:11px 13px;outline:none}.field input:focus,.field select:focus,.field textarea:focus,input:focus,select:focus,textarea:focus{border-color:#b86c30;box-shadow:0 0 0 3px #ff963d18}.field textarea{min-height:92px;resize:vertical}.formgrid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 16px}.formgrid .full{grid-column:1/-1}.notice{padding:12px 15px;border-radius:12px;margin-bottom:18px;border:1px solid #6a4b30;background:#ff963d10;color:#ffd2a5}.notice.error{border-color:#7c3931;background:#ff65550d;color:#ffb2a8}.notice.success{border-color:#2d6340;background:#4bc8750d;color:#a7e7b7}.empty{padding:40px 20px;text-align:center;color:var(--muted)}.empty .big{font-size:38px;margin-bottom:8px}.authwrap{max-width:560px;margin:6vh auto;padding:24px}.authbrand{display:flex;justify-content:center;margin-bottom:20px}.authpanel{padding:28px}.authpanel h1{margin:0 0 4px;font-size:24px}.authpanel .sub{color:var(--muted);margin-bottom:24px}.tabs{display:flex;padding:4px;background:#11100f;border:1px solid var(--line);border-radius:12px;margin-bottom:22px}.tabs a{flex:1;text-align:center;padding:8px;border-radius:8px;color:var(--muted)}.tabs a.active{background:#34271c;color:#ffb36b}.filebox{border:1px dashed #765437;background:#ff963d08;border-radius:15px;padding:24px;text-align:center;cursor:pointer}.filebox input{border:0;background:transparent;padding:10px}.editorgrid{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(280px,1fr);gap:16px}.videobox{background:#090909;border:1px solid #37312b;border-radius:14px;overflow:hidden;min-height:220px;display:grid;place-items:center}.videobox video{width:100%;max-height:440px;background:#050505}.segment{display:grid;grid-template-columns:90px minmax(0,1fr) auto;gap:10px;align-items:start;padding:12px 0;border-bottom:1px solid #332e29}.segment:last-child{border-bottom:0}.segment textarea{min-height:56px}.timeinput{font-size:12px;padding:8px!important}.timeline{height:44px;background:#100f0e;border:1px solid var(--line);border-radius:9px;margin:12px 0;position:relative;overflow:hidden}.timeline:before{content:'';position:absolute;inset:10px 8px;background:repeating-linear-gradient(90deg,#ff9b3d44 0 9%,transparent 9% 10%);border-radius:5px}.footerhint{font-size:12px;color:#80766c;margin-top:20px}.inline{display:flex;gap:10px;align-items:center;flex-wrap:wrap}.inline input{flex:1;min-width:120px}.small{font-size:12px}.mobilemenu{display:none}
@media(max-width:960px){.shell{grid-template-columns:1fr;padding:14px}.sidebar{position:fixed;z-index:20;top:0;right:0;bottom:0;height:100vh;width:270px;border-radius:0 0 0 18px;transform:translateX(110%);transition:transform .2s}.sidebar.open{transform:translateX(0)}.mobilemenu{display:inline-flex}.stats{grid-template-columns:1fr}.projects{grid-template-columns:repeat(2,minmax(0,1fr))}.editorgrid{grid-template-columns:1fr}.topbar h1{font-size:21px}}
@media(max-width:580px){.projects,.formgrid{grid-template-columns:1fr}.formgrid .full{grid-column:auto}.topbar{align-items:flex-start}.topactions .btn{font-size:12px;padding:8px 10px}.panel{padding:16px}.projectfoot{align-items:flex-start;flex-direction:column}.segment{grid-template-columns:74px minmax(0,1fr)}.segment form:last-child{grid-column:2}.authwrap{padding:12px}.authpanel{padding:20px}}
</style></head><body>
<?php if ($u): ?><div class="shell"><aside class="sidebar" id="sidebar"><a class="brand" href="?page=dashboard"><span class="brandmark">◈</span><span>OpenCaption<small style="display:block;color:#9d8e7d;font-size:10px;font-weight:500">استودیوی زیرنویس</small></span></a><div class="navlabel">فضای کاری</div><nav class="nav"><a class="<?=$active==='projects'?'active':''?>" href="?page=dashboard"><span class="ico">▦</span>پروژه‌های من</a><a class="<?=$active==='new'?'active':''?>" href="?page=new"><span class="ico">＋</span>پروژه جدید</a><a class="<?=$active==='settings'?'active':''?>" href="?page=settings"><span class="ico">⚙</span>تنظیمات</a></nav><div class="sidebottom"><div class="profile"><div class="avatar"><?=e(mb_substr($u['name'],0,1,'UTF-8'))?></div><div><?=e($u['name'])?><small><?=e($u['email'])?></small></div></div><a class="nav" style="display:block;padding:10px;color:#a79b90" href="?page=logout">↪ خروج از حساب</a></div></aside><main><header class="topbar"><div class="inline"><button class="iconbtn mobilemenu" type="button" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button><div><div class="crumb">OpenCaption / <?=e($title)?></div><h1><?=e($title)?></h1></div></div><div class="topactions"><a class="btn" href="?page=new">＋ پروژه جدید</a></div></header>
<?php if ($f): ?><div class="notice <?=e($f['type'])?>"><?=e($f['message'])?></div><?php endif; ?>
<?php else: ?><div class="authwrap"><div class="authbrand"><a class="brand" href="?page=login"><span class="brandmark">◈</span><span>OpenCaption</span></a></div><?php if ($f): ?><div class="notice <?=e($f['type'])?>"><?=e($f['message'])?></div><?php endif; ?><?php endif;
}
function layout_end(): void { if (current_user()) echo '</main></div>'; else echo '</div>'; } ?>
<script>document.querySelectorAll('[data-confirm]').forEach(function(el){el.addEventListener('click',function(ev){if(!confirm(el.getAttribute('data-confirm')))ev.preventDefault()})});document.querySelectorAll('input[type=file][data-filename]').forEach(function(el){el.addEventListener('change',function(){var out=document.getElementById(el.dataset.filename);if(out)out.textContent=el.files.length?el.files[0].name:'فایلی انتخاب نشده است'})});</script></body></html>
<?php
$page = (string)($_GET['page'] ?? (current_user() ? 'dashboard' : 'login'));
$action = (string)($_POST['action'] ?? '');
try {
    // Private media is only streamed to the owning, authenticated user.
    if (($_GET['action'] ?? '') === 'media') {
        $u=require_user(); $p=owned_project((int)($_GET['id'] ?? 0),(int)$u['id']);
        if (empty($p['stored_filename'])) { http_response_code(404); exit('فایل ویدئو وجود ندارد.'); }
        $base=realpath((string)$config['uploads']['directory']); $file=realpath((string)$config['uploads']['directory'].'/'.$p['stored_filename']);
        if (!$base || !$file || !str_starts_with($file,$base.DIRECTORY_SEPARATOR) || !is_file($file)) { http_response_code(404); exit('فایل پیدا نشد.'); }
        $size=filesize($file); $start=0; $end=$size-1;
        header('Content-Type: '.($p['mime_type'] ?: 'application/octet-stream')); header('Accept-Ranges: bytes'); header('X-Content-Type-Options: nosniff'); header('Cache-Control: private, no-store');
        if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/',$_SERVER['HTTP_RANGE'],$m)) {
            if ($m[1] !== '') $start=(int)$m[1];
            if ($m[2] !== '') $end=min($end,(int)$m[2]);
            if ($start>$end || $start>=$size) { header('Content-Range: bytes */'.$size); http_response_code(416); exit; }
            http_response_code(206); header("Content-Range: bytes $start-$end/$size");
        }
        header('Content-Length: '.($end-$start+1)); $fp=fopen($file,'rb'); fseek($fp,$start); $remaining=$end-$start+1;
        while ($remaining>0 && !feof($fp)) { $chunk=fread($fp,min(8192,$remaining)); if ($chunk===false || $chunk==='') break; echo $chunk; $remaining-=strlen($chunk); }
        fclose($fp); exit;
    }
    if ($page==='logout') { $_SESSION=[]; if (ini_get('session.use_cookies')) { $p=session_get_cookie_params(); setcookie(session_name(),'',['expires'=>time()-42000,'path'=>$p['path'],'domain'=>$p['domain'],'secure'=>$p['secure'],'httponly'=>$p['httponly'],'samesite'=>'Lax']); } session_destroy(); go('?page=login'); }
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        verify_csrf();
        if ($action==='register') {
            $name=trim((string)($_POST['name']??'')); $email=strtolower(trim((string)($_POST['email']??''))); $phone=trim((string)($_POST['phone']??'')); $pass=(string)($_POST['password']??''); $confirm=(string)($_POST['confirm_password']??'');
            if (mb_strlen($name)<2 || !filter_var($email,FILTER_VALIDATE_EMAIL) || $phone==='' || strlen($pass)<10 || $pass!==$confirm) { flash('اطلاعات را بررسی کنید؛ رمز باید حداقل ۱۰ کاراکتر باشد و با تکرار آن تطابق داشته باشد.','error'); go('?page=register'); }
            $q=db()->prepare('SELECT id FROM users WHERE email=? OR phone=?'); $q->execute([$email,$phone]);
            if ($q->fetch()) { flash('این ایمیل یا شماره تلفن قبلاً ثبت شده است.','error'); go('?page=register'); }
            $q=db()->prepare('INSERT INTO users(name,email,phone,password_hash) VALUES(?,?,?,?)'); $q->execute([$name,$email,$phone,password_hash($pass,PASSWORD_DEFAULT)]);
            session_regenerate_id(true); $_SESSION['user']=['id'=>(int)db()->lastInsertId(),'name'=>$name,'email'=>$email]; $_SESSION['csrf']=bin2hex(random_bytes(32)); flash('حساب شما ساخته شد. حالا می‌توانید پروژه ایجاد کنید.','success'); go('?page=dashboard');
        }
        if ($action==='login') {
            $identity=trim((string)($_POST['identity']??'')); $pass=(string)($_POST['password']??'');
            $q=db()->prepare('SELECT id,name,email,phone,password_hash FROM users WHERE email=? OR phone=? LIMIT 1'); $q->execute([strtolower($identity),$identity]); $row=$q->fetch();
            if (!$row || !password_verify($pass,$row['password_hash'])) { flash('اطلاعات ورود صحیح نیست.','error'); go('?page=login'); }
            session_regenerate_id(true); $_SESSION['user']=['id'=>(int)$row['id'],'name'=>$row['name'],'email'=>$row['email']]; $_SESSION['csrf']=bin2hex(random_bytes(32)); go('?page=dashboard');
        }
        $u=require_user();
        if ($action==='create_project') {
            $title=trim((string)($_POST['title']??'')); $language=(string)($_POST['language']??'fa');
            if ($title==='') { flash('برای پروژه یک عنوان وارد کنید.','error'); go('?page=new'); }
            if (!in_array($language,['fa','en','ar','de','hi','tr','fr','es','auto'],true)) $language='fa';
            $q=db()->prepare('INSERT INTO projects(user_id,title,language,status) VALUES(?,?,?,?)'); $q->execute([$u['id'],mb_substr($title,0,180),$language,'draft']);
            flash('پروژه ساخته شد. برای شروع، فایل ویدئو را در صفحه پروژه اضافه کنید.','success'); go('?page=project&id='.(int)db()->lastInsertId());
        }
        if ($action==='upload_video') {
            $title=trim((string)($_POST['title']??'')); $language=(string)($_POST['language']??'fa'); $f=$_FILES['video']??null;
            $allowed=['mp4'=>'video/mp4','webm'=>'video/webm','mov'=>'video/quicktime','m4v'=>'video/x-m4v','ogv'=>'video/ogg'];
            if (!in_array($language,['fa','en','ar','de','hi','tr','fr','es','auto'],true)) $language='fa';
            if (!$f || !isset($f['error']) || $f['error']!==UPLOAD_ERR_OK) { flash('آپلود کامل نشد. محدودیت حجم PHP و هاست را بررسی کنید.','error'); go('?page=new'); }
            $ext=strtolower(pathinfo((string)$f['name'],PATHINFO_EXTENSION)); $size=(int)$f['size']; $limit=(int)($config['uploads']['max_bytes']??314572800);
            if (!isset($allowed[$ext]) || $size<1 || $size>$limit) { flash('فرمت فایل پشتیبانی نمی‌شود یا حجم آن از محدودیت تنظیم‌شده بیشتر است.','error'); go('?page=new'); }
            $fi=new finfo(FILEINFO_MIME_TYPE); $mime=$fi->file((string)$f['tmp_name']);
            $mimeAllowed=['video/mp4','video/webm','video/quicktime','video/x-m4v','video/ogg','application/ogg'];
            if (!in_array($mime,$mimeAllowed,true)) { flash('نوع فایل ویدئویی معتبر تشخیص داده نشد.','error'); go('?page=new'); }
            $dir=(string)$config['uploads']['directory']; if (!is_dir($dir) && !mkdir($dir,0750,true) && !is_dir($dir)) throw new RuntimeException('Upload directory could not be created.');
            $stored=bin2hex(random_bytes(24)).'.'.$ext; if (!move_uploaded_file((string)$f['tmp_name'],$dir.'/'.$stored)) throw new RuntimeException('Upload move failed.');
            $title=$title!==''?$title:pathinfo((string)$f['name'],PATHINFO_FILENAME);
            $q=db()->prepare('INSERT INTO projects(user_id,title,language,original_filename,stored_filename,mime_type,file_size,status) VALUES(?,?,?,?,?,?,?,?)');
            $q->execute([$u['id'],mb_substr($title,0,180),$language,mb_substr(basename((string)$f['name']),0,255),$stored,$mime,$size,'uploaded']);
            flash('ویدئو بارگذاری شد. در این نسخه هنوز رونویسی خودکار متصل نشده است.','success'); go('?page=project&id='.(int)db()->lastInsertId());
        }
        if ($action==='add_segment') {
            $pid=(int)($_POST['project_id']??0); $p=owned_project($pid,(int)$u['id']); $start=filter_var($_POST['start_seconds']??null,FILTER_VALIDATE_FLOAT); $end=filter_var($_POST['end_seconds']??null,FILTER_VALIDATE_FLOAT); $text=trim((string)($_POST['text']??''));
            if ($start===false || $end===false || $start<0 || $end<=$start || $text==='') { flash('زمان شروع، پایان و متن زیرنویس را بررسی کنید.','error'); go('?page=project&id='.$pid); }
            $q=db()->prepare('SELECT COALESCE(MAX(sort_order),0)+1 FROM subtitle_segments WHERE project_id=?'); $q->execute([$pid]); $order=(int)$q->fetchColumn();
            $q=db()->prepare('INSERT INTO subtitle_segments(project_id,start_seconds,end_seconds,text,sort_order) VALUES(?,?,?,?,?)'); $q->execute([$pid,$start,$end,mb_substr($text,0,4000),$order]);
            flash('سگمنت زیرنویس ذخیره شد.','success'); go('?page=project&id='.$pid);
        }
        if ($action==='delete_segment') {
            $pid=(int)($_POST['project_id']??0); owned_project($pid,(int)$u['id']); $q=db()->prepare('DELETE FROM subtitle_segments WHERE id=? AND project_id=?'); $q->execute([(int)($_POST['segment_id']??0),$pid]); flash('سگمنت حذف شد.','success'); go('?page=project&id='.$pid);
        }
        if ($action==='save_segment') {
            $pid=(int)($_POST['project_id']??0); owned_project($pid,(int)$u['id']); $sid=(int)($_POST['segment_id']??0); $start=filter_var($_POST['start_seconds']??null,FILTER_VALIDATE_FLOAT); $end=filter_var($_POST['end_seconds']??null,FILTER_VALIDATE_FLOAT); $text=trim((string)($_POST['text']??''));
            if ($start===false || $end===false || $start<0 || $end<=$start || $text==='') { flash('مقادیر سگمنت معتبر نیستند.','error'); go('?page=project&id='.$pid); }
            $q=db()->prepare('UPDATE subtitle_segments SET start_seconds=?,end_seconds=?,text=? WHERE id=? AND project_id=?'); $q->execute([$start,$end,mb_substr($text,0,4000),$sid,$pid]); flash('تغییرات زیرنویس ذخیره شد.','success'); go('?page=project&id='.$pid);
        }
    }
    if ($page==='export_srt') {
        $u=require_user(); $p=owned_project((int)($_GET['id']??0),(int)$u['id']); $q=db()->prepare('SELECT * FROM subtitle_segments WHERE project_id=? ORDER BY start_seconds,sort_order'); $q->execute([$p['id']]); $rows=$q->fetchAll();
        header('Content-Type: application/x-subrip; charset=UTF-8'); header('Content-Disposition: attachment; filename="opencaption-project-'.$p['id'].'.srt"'); echo "\xEF\xBB\xBF";
        foreach($rows as $i=>$r) echo ($i+1)."\r\n".srt_time((float)$r['start_seconds']).' --> '.srt_time((float)$r['end_seconds'])."\r\n".$r['text']."\r\n\r\n";
        exit;
    }
    if ($page==='register' || $page==='login') {
        if (current_user()) go('?page=dashboard');
        layout_start($page==='register'?'ساخت حساب':'ورود به حساب');
        ?><section class="panel authpanel"><h1><?=$page==='register'?'ساخت حساب کاربری':'خوش برگشتی'?></h1><div class="sub"><?=$page==='register'?'برای مدیریت پروژه‌های زیرنویس ثبت‌نام کنید.':'وارد فضای کاری OpenCaption شوید.'?></div><div class="tabs"><a class="<?=$page==='login'?'active':''?>" href="?page=login">ورود</a><a class="<?=$page==='register'?'active':''?>" href="?page=register">ثبت‌نام</a></div><form method="post" autocomplete="on"><input type="hidden" name="_csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="<?=$page==='register'?'register':'login'?>"><?php if($page==='register'): ?><div class="field"><label>نام و نام خانوادگی</label><input name="name" required minlength="2" maxlength="120" autocomplete="name" placeholder="نام شما"></div><div class="field"><label>شماره تلفن</label><input name="phone" required type="tel" autocomplete="tel" placeholder="09xxxxxxxxx"></div><div class="field"><label>ایمیل</label><input name="email" required type="email" autocomplete="email" placeholder="you@example.com"></div><?php else: ?><div class="field"><label>ایمیل یا شماره تلفن</label><input name="identity" required autocomplete="username" placeholder="ایمیل یا شماره تلفن"></div><?php endif; ?><div class="field"><label>رمز عبور</label><input name="password" type="password" required <?php if($page==='register') echo 'minlength="10" autocomplete="new-password"'; else echo 'autocomplete="current-password"'; ?> placeholder="<?=$page==='register'?'حداقل ۱۰ کاراکتر':'رمز عبور'?>"></div><?php if($page==='register'): ?><div class="field"><label>تکرار رمز عبور</label><input name="confirm_password" type="password" required minlength="10" autocomplete="new-password" placeholder="رمز را دوباره وارد کنید"></div><?php endif; ?><button type="submit" style="width:100%"><?=$page==='register'?'ساخت حساب':'ورود به داشبورد'?> ←</button></form><p class="footerhint">این نسخه اولیه است؛ بازیابی رمز و تأیید ایمیل/شماره هنوز پیاده‌سازی نشده‌اند.</p></section><?php layout_end(); exit;
    }
    $u=require_user();
    if ($page==='dashboard' || $page==='projects') {
        $q=db()->prepare("SELECT * FROM projects WHERE user_id=? ORDER BY updated_at DESC LIMIT 100"); $q->execute([$u['id']]); $projects=$q->fetchAll();
        $total=count($projects); $uploaded=count(array_filter($projects,fn($p)=>!empty($p['stored_filename']))); $segmentsQ=db()->prepare('SELECT COUNT(*) FROM subtitle_segments s JOIN projects p ON p.id=s.project_id WHERE p.user_id=?'); $segmentsQ->execute([$u['id']]); $segmentCount=(int)$segmentsQ->fetchColumn();
        layout_start('پروژه‌های من','projects'); ?>
        <div class="grid stats"><section class="panel stat"><div class="staticon">▦</div><div><span class="muted">همه پروژه‌ها</span><strong><?=$total?></strong></div></section><section class="panel stat"><div class="staticon">▶</div><div><span class="muted">ویدئوهای آپلودشده</span><strong><?=$uploaded?></strong></div></section><section class="panel stat"><div class="staticon">文</div><div><span class="muted">سگمنت‌های زیرنویس</span><strong><?=$segmentCount?></strong></div></section></div>
        <section class="panel"><div class="sectionhead"><div><h2>فضای کاری شما</h2><div class="muted small">پروژه‌ها در حساب شما ذخیره می‌شوند.</div></div><a class="btn" href="?page=new">＋ ایجاد پروژه</a></div>
        <?php if(!$projects): ?><div class="empty"><div class="big">▧</div><h3>هنوز پروژه‌ای ندارید</h3><p>یک پروژه بسازید و ویدئوی خود را اضافه کنید تا ویرایش زیرنویس را شروع کنید.</p><a class="btn" href="?page=new">ساخت اولین پروژه</a></div><?php else: ?><div class="grid projects"><?php foreach($projects as $p): ?><article class="panel projectcard"><a class="thumb" href="?page=project&id=<?=(int)$p['id']?>"><span class="playmark">▶</span><span class="status"><?=e(['draft'=>'پیش‌نویس','uploaded'=>'ویدئو آماده','queued'=>'در صف','processing'=>'در حال پردازش','ready'=>'آماده','failed'=>'خطا'][$p['status']]??$p['status'])?></span></a><div class="projectbody"><h3><a href="?page=project&id=<?=(int)$p['id']?>"><?=e($p['title'])?></a></h3><div class="meta"><span>◷ <?=e(date('Y/m/d',strtotime($p['created_at'])))?></span><span>◉ <?=e(strtoupper($p['language']))?></span><span><?=!empty($p['file_size'])?e(number_format((int)$p['file_size']/1048576,1).' MB'):'بدون فایل'?></span></div><div class="projectfoot"><span class="muted small"><?=!empty($p['original_filename'])?e($p['original_filename']):'فایل ویدئو اضافه نشده'?></span><a class="btn secondary" href="?page=project&id=<?=(int)$p['id']?>">باز کردن ←</a></div></div></article><?php endforeach; ?></div><?php endif; ?></section>
        <?php layout_end(); exit;
    }
    if ($page==='new') {
        layout_start('پروژه جدید','new'); ?>
        <section class="panel" style="max-width:850px"><div class="sectionhead"><div><h2>ساخت پروژه زیرنویس</h2><p class="muted">ویدئو را انتخاب کنید یا ابتدا یک پروژه خالی بسازید.</p></div><span class="status">مرحله ۱ از ۳</span></div><form method="post" enctype="multipart/form-data"><input type="hidden" name="_csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="upload_video"><div class="formgrid"><div class="field"><label>عنوان پروژه</label><input name="title" maxlength="180" placeholder="مثلاً مصاحبه قسمت اول"></div><div class="field"><label>زبان گفتار</label><select name="language"><option value="fa">فارسی</option><option value="en">English</option><option value="ar">العربية</option><option value="de">Deutsch</option><option value="hi">हिन्दी</option><option value="tr">Türkçe</option><option value="fr">Français</option><option value="es">Español</option><option value="auto">تشخیص خودکار (بعداً)</option></select></div><div class="field full"><label>فایل ویدئو</label><div class="filebox"><div style="font-size:28px;color:#ffad62">⇧</div><strong>ویدئو را انتخاب کنید</strong><p class="muted small">MP4، WebM، MOV، M4V یا OGV — حداکثر <?=e((int)($config['uploads']['max_bytes']??314572800)/1048576)?> مگابایت</p><input type="file" name="video" data-filename="selected-file" accept="video/mp4,video/webm,video/quicktime,video/ogg,.mp4,.webm,.mov,.m4v,.ogv" required><div id="selected-file" class="muted small">فایلی انتخاب نشده است</div></div></div></div><button type="submit">آپلود و ساخت پروژه ←</button> <a class="btn secondary" href="?page=new&blank=1">ساخت پروژه خالی</a></form><?php if(isset($_GET['blank'])): ?><hr style="border-color:var(--line);margin:24px 0"><form method="post"><input type="hidden" name="_csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="create_project"><div class="field"><label>عنوان پروژه خالی</label><input name="title" required maxlength="180" placeholder="نام پروژه"></div><div class="field"><label>زبان</label><select name="language"><option value="fa">فارسی</option><option value="en">English</option><option value="ar">العربية</option></select></div><button type="submit">ایجاد پروژه خالی</button></form><?php endif; ?><p class="footerhint">در این نسخه، آپلود و ذخیره پروژه واقعی است؛ تشخیص گفتار و پردازش AI هنوز به سرویس پردازش متصل نشده است. سقف آپلود واقعی تابع تنظیمات PHP و هاست هم هست.</p></section>
        <?php layout_end(); exit;
    }
    if ($page==='project') {
        $pid=(int)($_GET['id']??0); $p=owned_project($pid,(int)$u['id']); $q=db()->prepare('SELECT * FROM subtitle_segments WHERE project_id=? ORDER BY start_seconds,sort_order'); $q->execute([$pid]); $segments=$q->fetchAll();
        layout_start('ویرایش پروژه','projects'); ?>
        <div class="sectionhead"><div><h2><?=e($p['title'])?></h2><div class="muted small">زبان: <?=e(strtoupper($p['language']))?> · وضعیت: <?=e($p['status'])?></div></div><div class="inline"><a class="btn secondary" href="?page=dashboard">← پروژه‌ها</a><a class="btn" href="?page=export_srt&id=<?=$pid?>">دانلود SRT</a><?php if(!empty($p['stored_filename'])): ?><button class="secondary" type="button" id="export-webm" <?=count($segments)===0?'disabled':''?>>ساخت WebM با زیرنویس</button><?php endif; ?></div></div>
        <?php if(!empty($p['stored_filename'])): ?><section class="panel" style="margin-bottom:16px"><div class="inline"><span id="webm-status" class="muted small"><?=count($segments)===0?'برای خروجی گرفتن ابتدا یک سگمنت زیرنویس اضافه کنید.':'خروجی WebM در مرورگر شما ساخته می‌شود؛ این کار ممکن است به‌اندازه مدت ویدئو زمان ببرد.'?></span></div><progress id="webm-progress" max="100" value="0" style="width:100%;height:8px;margin-top:12px;accent-color:#ff963d"></progress><canvas id="webm-canvas" hidden></canvas></section><?php endif; ?>
        <div class="editorgrid"><section class="panel"><div class="sectionhead"><h2>پیش‌نمایش ویدئو</h2><span class="status"><?=!empty($p['stored_filename'])?'ویدئو بارگذاری شده':'ویدئویی ثبت نشده'?></span></div><?php if(!empty($p['stored_filename'])): ?><div class="videobox"><video controls preload="metadata" src="?action=media&id=<?=$pid?>"></video></div><?php else: ?><div class="videobox"><div class="empty"><div class="big">▶</div><p>برای نمایش پیش‌نمایش، یک ویدئو به پروژه اضافه کنید.</p><a class="btn secondary" href="?page=new">آپلود ویدئو</a></div></div><?php endif; ?><div class="timeline" title="نمایش مفهومی تایم‌لاین"></div><div class="muted small">تایم‌لاین پایه — همگام‌سازی و ویرایش بصری پیشرفته در مرحله بعد توسعه داده می‌شود.</div></section>
        <section class="panel"><div class="sectionhead"><h2>افزودن زیرنویس</h2><span class="status"><?=count($segments)?> سگمنت</span></div><form method="post"><input type="hidden" name="_csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="add_segment"><input type="hidden" name="project_id" value="<?=$pid?>"><div class="formgrid"><div class="field"><label>شروع (ثانیه)</label><input type="number" step="0.001" min="0" name="start_seconds" value="0" required></div><div class="field"><label>پایان (ثانیه)</label><input type="number" step="0.001" min="0.001" name="end_seconds" value="3" required></div><div class="field full"><label>متن زیرنویس</label><textarea name="text" required maxlength="4000" placeholder="متن زیرنویس را وارد کنید..."></textarea></div></div><button type="submit">＋ افزودن سگمنت</button></form></section></div>
        <section class="panel" style="margin-top:16px"><div class="sectionhead"><div><h2>سگمنت‌های زیرنویس</h2><div class="muted small">هر سگمنت را می‌توانید ویرایش یا حذف کنید.</div></div><a class="btn secondary" href="?page=export_srt&id=<?=$pid?>">خروجی SRT</a></div><?php if(!$segments): ?><div class="empty"><div class="big">文</div><p>هنوز زیرنویسی وجود ندارد. می‌توانید دستی سگمنت اضافه کنید.</p></div><?php else: foreach($segments as $s): ?><div class="segment"><div class="muted small">#<?= (int)$s['sort_order'] ?><br><?=e(fmt_time((float)$s['start_seconds']))?></div><form method="post" style="display:grid;gap:8px"><input type="hidden" name="_csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="save_segment"><input type="hidden" name="project_id" value="<?=$pid?>"><input type="hidden" name="segment_id" value="<?=(int)$s['id']?>"><div class="inline"><label class="small muted">شروع</label><input class="timeinput" style="max-width:100px" type="number" min="0" step=".001" name="start_seconds" value="<?=e($s['start_seconds'])?>"><label class="small muted">پایان</label><input class="timeinput" style="max-width:100px" type="number" min=".001" step=".001" name="end_seconds" value="<?=e($s['end_seconds'])?>"></div><textarea name="text" required maxlength="4000"><?=e($s['text'])?></textarea><button class="secondary" type="submit">ذخیره تغییرات</button></form><form method="post"><input type="hidden" name="_csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="delete_segment"><input type="hidden" name="project_id" value="<?=$pid?>"><input type="hidden" name="segment_id" value="<?=(int)$s['id']?>"><button class="danger" type="submit" data-confirm="این سگمنت حذف شود؟">حذف</button></form></div><?php endforeach; endif; ?></section>
        <?php if(!empty($p['stored_filename'])): ?>
        <script>
        (function () {
          const button = document.getElementById('export-webm');
          const video = document.querySelector('.videobox video');
          const canvas = document.getElementById('webm-canvas');
          const status = document.getElementById('webm-status');
          const progress = document.getElementById('webm-progress');
          const cues = <?=json_encode(array_map(static fn(array $s): array => ['start'=>(float)$s['start_seconds'],'end'=>(float)$s['end_seconds'],'text'=>(string)$s['text']], $segments), JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
          if (!button || !video || !canvas || !status || !progress || button.disabled) return;
          const say = (message) => { status.textContent = message; };
          function drawCaption(ctx, text, width, height) {
            const size = Math.max(26, Math.min(62, width * 0.043));
            const lineHeight = size * 1.32;
            ctx.font = '700 ' + size + 'px Vazirmatn, sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            if ('direction' in ctx) ctx.direction = 'rtl';
            const maxWidth = width * 0.86;
            const words = text.split(/\s+/);
            const lines = [];
            let line = '';
            words.forEach((word) => {
              const test = line ? line + ' ' + word : word;
              if (line && ctx.measureText(test).width > maxWidth) {
                lines.push(line);
                line = word;
              } else line = test;
            });
            if (line) lines.push(line);
            const visible = lines.slice(-3);
            const boxWidth = Math.min(maxWidth + size, Math.max(...visible.map(lineText => ctx.measureText(lineText).width)) + size * 1.1);
            const boxHeight = visible.length * lineHeight + size * 0.48;
            const x = (width - boxWidth) / 2;
            const y = height - height * 0.075 - boxHeight;
            ctx.fillStyle = 'rgba(0,0,0,.68)';
            ctx.fillRect(x, y, boxWidth, boxHeight);
            ctx.fillStyle = '#fff';
            visible.forEach((lineText, i) => ctx.fillText(lineText, width / 2, y + size * 0.24 + lineHeight * (i + 0.5)));
          }
          function renderFrame(ctx) {
            if (video.videoWidth && video.videoHeight) {
              ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
              const now = video.currentTime;
              const cue = cues.find(item => now >= item.start && now < item.end);
              if (cue) drawCaption(ctx, cue.text, canvas.width, canvas.height);
            }
            if (!video.paused && !video.ended) {
              progress.value = video.duration ? Math.min(99, Math.round(video.currentTime / video.duration * 100)) : 0;
              requestAnimationFrame(() => renderFrame(ctx));
            }
          }
          button.addEventListener('click', async function () {
            if (!window.MediaRecorder || !canvas.captureStream || !(video.captureStream || video.mozCaptureStream)) {
              say('این مرورگر ضبط WebM را پشتیبانی نمی‌کند. از نسخه جدید Chrome یا Firefox استفاده کنید.');
              return;
            }
            button.disabled = true;
            progress.value = 0;
            let recorder;
            let stream;
            let audioStream;
            try {
              say('در حال آماده‌سازی ویدئو و زیرنویس‌ها…');
              if (document.fonts && document.fonts.ready) await document.fonts.ready;
              if (video.readyState < 2) await new Promise((resolve, reject) => {
                video.addEventListener('loadeddata', resolve, {once:true});
                video.addEventListener('error', reject, {once:true});
              });
              canvas.width = video.videoWidth;
              canvas.height = video.videoHeight;
              if (!canvas.width || !canvas.height) throw new Error('ابعاد ویدئو قابل خواندن نیست.');
              video.pause();
              if (video.currentTime !== 0) await new Promise(resolve => {
                video.addEventListener('seeked', resolve, {once:true});
                video.currentTime = 0;
              });
              const ctx = canvas.getContext('2d', {alpha:false});
              ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
              stream = canvas.captureStream(30);
              audioStream = (video.captureStream || video.mozCaptureStream).call(video);
              const audioTracks = audioStream.getAudioTracks();
              audioTracks.forEach(track => stream.addTrack(track));
              const types = ['video/webm;codecs=vp9,opus','video/webm;codecs=vp8,opus','video/webm'];
              const mimeType = types.find(type => MediaRecorder.isTypeSupported(type)) || '';
              const chunks = [];
              recorder = new MediaRecorder(stream, mimeType ? {mimeType} : undefined);
              recorder.ondataavailable = event => { if (event.data && event.data.size) chunks.push(event.data); };
              recorder.onerror = () => say('هنگام ساخت فایل WebM خطایی رخ داد. فضای آزاد و پشتیبانی مرورگر را بررسی کنید.');
              recorder.onstop = () => {
                if (chunks.length) {
                  const blob = new Blob(chunks, {type: recorder.mimeType || 'video/webm'});
                  const url = URL.createObjectURL(blob);
                  const link = document.createElement('a');
                  link.href = url;
                  link.download = 'opencaption-' + <?=json_encode((string)$pid)?> + '.webm';
                  document.body.appendChild(link);
                  link.click();
                  link.remove();
                  setTimeout(() => URL.revokeObjectURL(url), 60000);
                  progress.value = 100;
                  say('فایل WebM ساخته شد.' + (audioTracks.length ? ' صدا نیز ضبط شده است.' : ' هشدار: مرورگر ترک صدای قابل ضبط ارائه نکرد؛ فایل ممکن است بی‌صدا باشد.'));
                } else say('فایل خروجی ساخته نشد. دوباره تلاش کنید.');
                stream && stream.getTracks().forEach(track => track.stop());
                audioStream && audioStream.getTracks().forEach(track => track.stop());
                button.disabled = false;
              };
              recorder.start(1000);
              say('در حال رندر در مرورگر… ویدئو را تا پایان پخش نگه دارید.');
              requestAnimationFrame(() => renderFrame(ctx));
              video.addEventListener('ended', () => { if (recorder && recorder.state !== 'inactive') recorder.stop(); }, {once:true});
              await video.play();
            } catch (error) {
              if (recorder && recorder.state !== 'inactive') recorder.stop();
              else {
                stream && stream.getTracks().forEach(track => track.stop());
                audioStream && audioStream.getTracks().forEach(track => track.stop());
                button.disabled = false;
              }
              say('ساخت WebM ناموفق بود: ' + (error && error.message ? error.message : 'خطای مرورگر'));
            }
          });
        })();
        </script>
        <?php endif; ?>
        <?php layout_end(); exit;
    }
    if ($page==='settings') {
        layout_start('تنظیمات','settings'); ?><section class="panel" style="max-width:800px"><h2>حساب کاربری</h2><div class="field"><label>نام</label><input value="<?=e($u['name'])?>" readonly></div><div class="field"><label>ایمیل</label><input value="<?=e($u['email'])?>" readonly></div><div class="notice">ویرایش پروفایل، تغییر رمز، مدیریت فونت‌ها و اتصال ارائه‌دهندگان AI هنوز در نقشه توسعه هستند.</div><h2>وضعیت قابلیت‌ها</h2><div class="grid"><div class="inline"><span class="status">فعال</span><span>حساب کاربری و ذخیره پروژه‌ها</span></div><div class="inline"><span class="status">فعال</span><span>آپلود خصوصی ویدئو</span></div><div class="inline"><span class="status">فعال</span><span>ویرایش دستی سگمنت‌ها و خروجی SRT</span></div><div class="inline"><span class="status">در دست توسعه</span><span>رونویسی AI، خروجی WebM/MP4، TTS و کلیدهای API</span></div></div></section><?php layout_end(); exit;
    }
    go('?page=dashboard');
} catch (Throwable $ex) {
    error_log('[OpenCaption] '.$ex->getMessage());
    http_response_code(500);
    layout_start('خطای برنامه');
    ?><section class="panel"><h2>برنامه نتوانست درخواست را تکمیل کند.</h2><p class="muted">اتصال دیتابیس، ساختار جداول و دسترسی پوشه‌ها را بررسی کنید. جزئیات فنی در لاگ سرور ثبت شده است.</p><a class="btn secondary" href="?page=dashboard">بازگشت</a></section><?php layout_end();
}

<?php
declare(strict_types=1);
$config = require __DIR__.'/config.php';
if (strlen($config['secret'] ?? '') < 32 || empty(password_get_info($config['admin_password_hash'] ?? '')['algo'])) { throw new RuntimeException('CRM configuration incomplete.'); }
$db = new PDO($config['dsn'], $config['db_user'] ?? '', $config['db_password'] ?? '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
if ($db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite') $db->exec('PRAGMA foreign_keys=ON');
const STATUSES = ['New','Contacted','Quoted','Completed'];
const SOURCES = ['Website','WhatsApp','Phone','Walk-in','Email','Other'];
const SERVICES = ['Corporate uniforms','Tailoring & alterations','Made-to-measure clothing','Garment repairs','Other / please advise'];
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function query(string $sql, array $args=[]): PDOStatement { global $db; $q=$db->prepare($sql);$q->execute($args);return $q; }
function now(): string { return gmdate('Y-m-d H:i:s'); }
function field(array $d,string $key,int $max,bool $required=false): string { $v=$d[$key]??'';if(!is_string($v))throw new InvalidArgumentException('Invalid '.$key.'.');$v=trim($v);if(strlen($v)>$max||($required&&$v===''))throw new InvalidArgumentException('Please provide a valid '.$key.'.');return $v; }
function phone(string $p): string { $p=preg_replace('/[ ()-]/','',trim($p));if(str_starts_with($p,'00'))$p='+'.substr($p,2);if(preg_match('/^05[0-9]{8}$/D',$p))$p='+971'.substr($p,1);if(preg_match('/^971[0-9]{9}$/D',$p))$p='+'.$p;if(!preg_match('/^\+[1-9][0-9]{7,14}$/D',$p))throw new InvalidArgumentException('Please enter a valid mobile number with country code.');return $p; }
function customerData(array $d): array { $service=field($d,'service',80,true);if(!in_array($service,SERVICES,true))throw new InvalidArgumentException('Choose a valid service.');$email=field($d,'email',254);if($email&&!filter_var($email,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Please enter a valid email.');return ['name'=>field($d,'name',120,true),'phone'=>phone(field($d,'phone',24,true)),'email'=>$email,'company'=>field($d,'company',160,$service==='Corporate uniforms'),'area'=>field($d,'area',160,true),'service'=>$service,'details'=>field($d,'details',18000,true),'marketing'=>($d['marketing']??false)===true||($d['marketing']??'')==='yes']; }
function throttle(string $kind,int $limit,int $seconds): void { global $config; $key=hash_hmac('sha256',$kind.'|'.($_SERVER['REMOTE_ADDR']??'unknown'),$config['secret']);query('DELETE FROM rate_limits WHERE happened_at < ?', [time()-86400]);$n=query('SELECT COUNT(*) FROM rate_limits WHERE key_hash=? AND happened_at>?',[$key,time()-$seconds])->fetchColumn();if((int)$n >= $limit)throw new OverflowException('Too many attempts. Please try again later.');query('INSERT INTO rate_limits(key_hash,happened_at) VALUES(?,?)',[$key,time()]); }
function saveEnquiry(array $d,string $source,string $submissionId): array { global $db;
 $c=customerData($d);if(!in_array($source,SOURCES,true))throw new InvalidArgumentException('Invalid source.');if(!preg_match('/^[a-f0-9-]{36}$/D',$submissionId))throw new InvalidArgumentException('Invalid submission reference.');
 $db->beginTransaction();try{
 $existing=query('SELECT id,customer_id FROM enquiries WHERE submission_id=?',[$submissionId])->fetch();if($existing){$db->commit();return $existing;}
 $person=query('SELECT id FROM customers WHERE phone=?',[$c['phone']])->fetch();$t=now();
 if($person){$id=(int)$person['id'];query('UPDATE customers SET name=?,email=CASE WHEN ?=\'\' THEN email ELSE ? END,company=CASE WHEN ?=\'\' THEN company ELSE ? END,area=?,marketing=?,updated_at=? WHERE id=?',[$c['name'],$c['email'],$c['email'],$c['company'],$c['company'],$c['area'],(int)$c['marketing'],$t,$id]);}
 else{query('INSERT INTO customers(name,phone,email,company,area,marketing,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?)',[$c['name'],$c['phone'],$c['email'],$c['company'],$c['area'],(int)$c['marketing'],$t,$t]);$id=(int)$db->lastInsertId();}
 query('INSERT INTO enquiries(customer_id,submission_id,service,details,snapshot_json,source,status,staff_notes,marketing,created_at,updated_at) VALUES(?,?,?,?,?,?,?,\'\',?,?,?)',[$id,$submissionId,$c['service'],$c['details'],json_encode($c,JSON_THROW_ON_ERROR),$source,'New',(int)$c['marketing'],$t,$t]);$enquiryId=(int)$db->lastInsertId();$db->commit();return ['id'=>$enquiryId,'customer_id'=>$id];
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function securityHeaders(): void { header('Cache-Control: no-store, private');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: no-referrer');header("Content-Security-Policy: default-src 'none'; style-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'"); }
function startAdminSession(): void { global $config; $dir=__DIR__.'/sessions';if(!is_dir($dir))mkdir($dir,0700,true);session_save_path($dir);session_name('unique_crm');session_set_cookie_params(['lifetime'=>0,'path'=>'/customer-register/','secure'=>$config['secure_cookies']??true,'httponly'=>true,'samesite'=>'Strict']);ini_set('session.use_strict_mode','1');session_start();if(isset($_SESSION['last'])&&time()-$_SESSION['last']>1800){$_SESSION=[];session_regenerate_id(true);}$_SESSION['last']=time();if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(32)); }
function csrf(): string { return '<input type="hidden" name="csrf" value="'.h($_SESSION['csrf']).'">'; }
function checkCsrf(): void { if(!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['csrf'],$_POST['csrf']))throw new InvalidArgumentException('Session check failed. Refresh the page and try again.'); }
function csvCell($v): string { $v=(string)$v;return preg_match('/^[\s]*[=+\-@]/u',$v)||strpbrk(substr($v,0,1),"\t\r\n")!==false?"'".$v:$v; }

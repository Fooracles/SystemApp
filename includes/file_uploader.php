<?php
/**
 * Centralized Secure File Upload Handler
 * 
 * SECURITY FEATURES:
 * - Server-side MIME validation using finfo (not client-spoofable $_FILES['type'])
 * - Extension whitelist per upload context
 * - Blocked dangerous extensions (PHP, EXE, etc.)
 * - Double extension detection (e.g., evil.php.jpg)
 * - PHP code injection scanning in uploaded files
 * - Null byte / directory traversal protection
 * - File size limits per context
 * - Upload audit logging to database
 * - Safe filename generation (no user input in stored names)
 * 
 * USAGE:
 *   $result = secureUpload($conn, $_FILES['file'], 'task_attachment');
 *   if ($result['success']) {
 *       $savedPath = $result['relative_path'];
 *   } else {
 *       $error = $result['error'];
 *   }
 */

if (defined('FILE_UPLOADER_LOADED')) { return; }
define('FILE_UPLOADER_LOADED', true);

define('UPLOAD_CONTEXTS', [
    'profile_photo'     => ['allowed_extensions' => ['jpg','jpeg','png','gif'],                                                                                   'max_size' => 5*1024*1024,  'upload_dir' => 'assets/uploads/profile_photos/'],
    'task_attachment'    => ['allowed_extensions' => ['pdf','doc','docx','xls','xlsx','txt','jpg','jpeg','png','gif','mp4','avi','mov','mp3','wav'],                'max_size' => 50*1024*1024, 'upload_dir' => 'uploads/task_ticket/'],
    'report'            => ['allowed_extensions' => ['pdf','ppt','pptx','doc','docx','xls','xlsx','csv','txt'],                                                            'max_size' => 50*1024*1024, 'upload_dir' => 'uploads/reports/'],
    'update_attachment'  => ['allowed_extensions' => ['pdf','doc','docx','xls','xlsx','txt','jpg','jpeg','png','gif','mp4','avi','mov','mp3','wav','webm'],         'max_size' => 50*1024*1024, 'upload_dir' => 'uploads/updates/'],
    'voice_recording'   => ['allowed_extensions' => ['webm','ogg','mp3','wav'],                                                                                   'max_size' => 20*1024*1024, 'upload_dir' => 'uploads/updates/voice/'],
    'holiday_csv'       => ['allowed_extensions' => ['csv','xlsx'],                                                                                                'max_size' => 10*1024*1024, 'upload_dir' => 'uploads/holidays/'],
]);

define('UPLOAD_MIME_MAP', [
    'jpg'=>['image/jpeg'],'jpeg'=>['image/jpeg'],'png'=>['image/png'],'gif'=>['image/gif'],
    'pdf'=>['application/pdf'],
    'doc'=>['application/msword','application/octet-stream'],'docx'=>['application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/zip','application/octet-stream'],
    'xls'=>['application/vnd.ms-excel','application/octet-stream'],'xlsx'=>['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','application/zip','application/octet-stream'],
    'ppt'=>['application/vnd.ms-powerpoint','application/octet-stream'],'pptx'=>['application/vnd.openxmlformats-officedocument.presentationml.presentation','application/zip','application/octet-stream'],
    'txt'=>['text/plain'],'csv'=>['text/csv','text/plain','application/csv','application/octet-stream'],
    'mp4'=>['video/mp4','application/octet-stream'],'avi'=>['video/x-msvideo','application/octet-stream'],'mov'=>['video/quicktime','application/octet-stream'],
    'mp3'=>['audio/mpeg','audio/mp3','application/octet-stream'],'wav'=>['audio/wav','audio/x-wav','application/octet-stream'],
    'webm'=>['video/webm','audio/webm','application/octet-stream'],'ogg'=>['audio/ogg','application/ogg','application/octet-stream'],
]);

define('BLOCKED_EXTENSIONS', [
    'php','phtml','php3','php4','php5','php7','php8','phar','cgi','pl','py','rb','sh','bash',
    'bat','cmd','com','exe','msi','scr','vbs','vbe','js','wsh','wsf',
    'htaccess','htpasswd','ini','env','asp','aspx','jsp','jspx',
]);

function secureUpload($conn, $file, $context, $options = []) {
    $userId = $_SESSION['id'] ?? $_SESSION['user_id'] ?? 0;
    $ip = function_exists('getClientIpAddress') ? getClientIpAddress() : ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    $config = _getContextConfig($context, $options);
    if (!$config) return _uploadFailure($conn,$userId,$ip,$file['name']??'unknown',0,null,$context,"Unknown upload context: {$context}");
    $originalName = _sanitizeFilename($file['name'] ?? 'unknown');
    $fileSize = $file['size'] ?? 0;
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) return _uploadFailure($conn,$userId,$ip,$originalName,$fileSize,null,$context,_uploadErrorMessage($file['error']));
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (in_array($ext, BLOCKED_EXTENSIONS)) return _uploadFailure($conn,$userId,$ip,$originalName,$fileSize,null,$context,"Blocked file type: .{$ext}");
    if (!in_array($ext, $config['allowed_extensions'])) return _uploadFailure($conn,$userId,$ip,$originalName,$fileSize,null,$context,"File type .{$ext} not allowed. Allowed: ".implode(', ',$config['allowed_extensions']));
    if (_hasDoubleExtension($originalName)) return _uploadFailure($conn,$userId,$ip,$originalName,$fileSize,null,$context,"Double extension detected");
    if ($fileSize > $config['max_size']) return _uploadFailure($conn,$userId,$ip,$originalName,$fileSize,null,$context,"File exceeds ".round($config['max_size']/(1024*1024),1)."MB limit");
    $detectedMime = null;
    if (function_exists('finfo_open') && !empty($file['tmp_name']) && file_exists($file['tmp_name'])) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE); $detectedMime = finfo_file($finfo, $file['tmp_name']); finfo_close($finfo);
        if (!_isAllowedMime($ext, $detectedMime)) return _uploadFailure($conn,$userId,$ip,$originalName,$fileSize,$detectedMime,$context,"MIME mismatch: content is '{$detectedMime}' but extension is .{$ext}");
    }
    if (!empty($file['tmp_name']) && file_exists($file['tmp_name']) && _containsPhpCode($file['tmp_name'])) return _uploadFailure($conn,$userId,$ip,$originalName,$fileSize,$detectedMime,$context,"File contains embedded script code");
    $newFilename = _generateSafeFilename($ext);
    $absDir = _getAbsoluteUploadDir($config['upload_dir']);
    if (!_ensureDirectoryExists($absDir)) return _uploadFailure($conn,$userId,$ip,$originalName,$fileSize,$detectedMime,$context,"Failed to create upload directory");
    if (!move_uploaded_file($file['tmp_name'], $absDir.$newFilename)) return _uploadFailure($conn,$userId,$ip,$originalName,$fileSize,$detectedMime,$context,"Failed to save file");
    @chmod($absDir.$newFilename, 0644);
    _logAudit($conn,$userId,$ip,$originalName,$newFilename,$fileSize,$detectedMime,$context,'accepted');
    return ['success'=>true,'filename'=>$newFilename,'original_name'=>$originalName,'relative_path'=>$config['upload_dir'].$newFilename,'file_size'=>$fileSize,'mime_type'=>$detectedMime??($file['type']??'application/octet-stream'),'error'=>null];
}

function secureBase64Upload($conn, $base64Data, $originalName, $context, $options = []) {
    $userId = $_SESSION['id'] ?? $_SESSION['user_id'] ?? 0;
    $ip = function_exists('getClientIpAddress') ? getClientIpAddress() : ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    $originalName = _sanitizeFilename($originalName);
    $config = _getContextConfig($context, $options);
    if (!$config) return _uploadFailure($conn,$userId,$ip,$originalName,0,null,$context,"Unknown upload context: {$context}");
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (in_array($ext, BLOCKED_EXTENSIONS)) return _uploadFailure($conn,$userId,$ip,$originalName,0,null,$context,"Blocked file type: .{$ext}");
    if (!in_array($ext, $config['allowed_extensions'])) return _uploadFailure($conn,$userId,$ip,$originalName,0,null,$context,"File type .{$ext} not allowed");
    if (_hasDoubleExtension($originalName)) return _uploadFailure($conn,$userId,$ip,$originalName,0,null,$context,"Double extension detected");
    $fileData = base64_decode($base64Data, true);
    if ($fileData === false) return _uploadFailure($conn,$userId,$ip,$originalName,0,null,$context,"Invalid base64 data");
    $fileSize = strlen($fileData);
    if ($fileSize > $config['max_size']) return _uploadFailure($conn,$userId,$ip,$originalName,$fileSize,null,$context,"File exceeds limit");
    $tmpFile = tempnam(sys_get_temp_dir(), 'upload_');
    if (!$tmpFile || !file_put_contents($tmpFile, $fileData)) return _uploadFailure($conn,$userId,$ip,$originalName,$fileSize,null,$context,"Failed to process upload");
    $detectedMime = null;
    if (function_exists('finfo_open')) { $fi = finfo_open(FILEINFO_MIME_TYPE); $detectedMime = finfo_file($fi,$tmpFile); finfo_close($fi);
        if (!_isAllowedMime($ext,$detectedMime)) { @unlink($tmpFile); return _uploadFailure($conn,$userId,$ip,$originalName,$fileSize,$detectedMime,$context,"MIME mismatch"); }
    }
    if (_containsPhpCode($tmpFile)) { @unlink($tmpFile); return _uploadFailure($conn,$userId,$ip,$originalName,$fileSize,$detectedMime,$context,"File contains embedded script code"); }
    $newFilename = _generateSafeFilename($ext);
    $absDir = _getAbsoluteUploadDir($config['upload_dir']);
    if (!_ensureDirectoryExists($absDir)) { @unlink($tmpFile); return _uploadFailure($conn,$userId,$ip,$originalName,$fileSize,$detectedMime,$context,"Failed to create upload directory"); }
    if (!rename($tmpFile, $absDir.$newFilename)) { if (!copy($tmpFile,$absDir.$newFilename)) { @unlink($tmpFile); return _uploadFailure($conn,$userId,$ip,$originalName,$fileSize,$detectedMime,$context,"Failed to save file"); } @unlink($tmpFile); }
    @chmod($absDir.$newFilename, 0644);
    _logAudit($conn,$userId,$ip,$originalName,$newFilename,$fileSize,$detectedMime,$context,'accepted');
    return ['success'=>true,'filename'=>$newFilename,'original_name'=>$originalName,'relative_path'=>$config['upload_dir'].$newFilename,'file_size'=>$fileSize,'mime_type'=>$detectedMime??'application/octet-stream','error'=>null];
}

function getUploadContextConfig($context) { $c = UPLOAD_CONTEXTS; return $c[$context] ?? null; }

function _getContextConfig($ctx, $opt = []) { $c = UPLOAD_CONTEXTS; if (!isset($c[$ctx])) return null; $cfg = $c[$ctx];
    if (!empty($opt['allowed_extensions'])) $cfg['allowed_extensions'] = $opt['allowed_extensions'];
    if (!empty($opt['max_size'])) $cfg['max_size'] = $opt['max_size'];
    if (!empty($opt['upload_dir'])) $cfg['upload_dir'] = $opt['upload_dir']; return $cfg; }

function _sanitizeFilename($n) { $n=str_replace("\0",'',$n); $n=str_replace(['/','\\','..'],'',$n); $n=preg_replace('/[\x00-\x1F\x7F]/','',$n); return trim($n)?:'unnamed_file'; }
function _hasDoubleExtension($f) { $p=explode('.',$f); if(count($p)<=2) return false; $b=BLOCKED_EXTENSIONS; for($i=0;$i<count($p)-1;$i++) if(in_array(strtolower($p[$i]),$b)) return true; return false; }
function _isAllowedMime($ext,$mime) { $m=UPLOAD_MIME_MAP; if(!isset($m[$ext])) return true; return in_array($mime,$m[$ext]); }
function _containsPhpCode($path) { $s=@filesize($path); if($s===false||$s>10*1024*1024) return false; $c=@file_get_contents($path); if($c===false) return false;
    foreach(['/<\?php/i','/<\?=/','/<\?[\s\n\r]/','\/<%[\s\n\r]/','\/eval\s*\(/i','\/base64_decode\s*\(/i','\/system\s*\(/i','\/exec\s*\(/i','\/passthru\s*\(/i','\/shell_exec\s*\(/i','\/proc_open\s*\(/i'] as $p) if(preg_match($p,$c)) { error_log("[File Upload Security] Script code in: ".basename($path)); return true; } return false; }
function _generateSafeFilename($ext) { return uniqid('',true).'_'.time().'.'.$ext; }
function _getAbsoluteUploadDir($rel) { $root=realpath(__DIR__.'/..') ?: dirname(__DIR__); return rtrim($root.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$rel),DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR; }
function _ensureDirectoryExists($d) { if(is_dir($d)) return true; return @mkdir($d,0755,true); }
function _uploadErrorMessage($c) { $m=[UPLOAD_ERR_INI_SIZE=>'File exceeds server limit',UPLOAD_ERR_FORM_SIZE=>'File exceeds form limit',UPLOAD_ERR_PARTIAL=>'Partially uploaded',UPLOAD_ERR_NO_FILE=>'No file uploaded',UPLOAD_ERR_NO_TMP_DIR=>'Missing temp dir',UPLOAD_ERR_CANT_WRITE=>'Write failed',UPLOAD_ERR_EXTENSION=>'Stopped by extension']; return $m[$c]??'Unknown error'; }
function _uploadFailure($conn,$uid,$ip,$orig,$sz,$mime,$ctx,$reason) { _logAudit($conn,$uid,$ip,$orig,null,$sz,$mime,$ctx,'rejected',$reason); error_log("[File Upload Rejected] user={$uid} ip={$ip} file={$orig} ctx={$ctx} reason={$reason}"); return ['success'=>false,'filename'=>null,'original_name'=>$orig,'relative_path'=>null,'file_size'=>$sz,'mime_type'=>$mime,'error'=>$reason]; }
function _logAudit($conn,$uid,$ip,$orig,$saved,$sz,$mime,$ctx,$status,$reason=null) { if(!$conn) return; $sql="INSERT INTO file_upload_audit (user_id,ip_address,original_filename,saved_filename,file_size,mime_type,upload_context,status,rejection_reason) VALUES (?,?,?,?,?,?,?,?,?)";
    $stmt=@mysqli_prepare($conn,$sql); if(!$stmt){$a=$status==='accepted'?'ACCEPTED':'REJECTED'; error_log("[Upload Audit][{$a}] user={$uid} file={$orig} ctx={$ctx}".($reason?" reason={$reason}":'')); return;}
    $saved=$saved??''; $mime=$mime??''; $reason=$reason??''; mysqli_stmt_bind_param($stmt,'isssissss',$uid,$ip,$orig,$saved,$sz,$mime,$ctx,$status,$reason);
    @mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt); }

function ensureUploadDirectorySecurity($dir) { $p=rtrim($dir,'/\\').DIRECTORY_SEPARATOR.'.htaccess'; if(file_exists($p)) return true;
    $c="# Prevent PHP execution\n<IfModule mod_php.c>\nphp_flag engine off\n</IfModule>\n<IfModule mod_php7.c>\nphp_flag engine off\n</IfModule>\n<IfModule mod_php8.c>\nphp_flag engine off\n</IfModule>\n<FilesMatch \"\\.(php|phtml|php3|php4|php5|php7|php8|phar|cgi|pl|py|rb|sh|bat|cmd|exe|com)$\">\nRequire all denied\n</FilesMatch>\n<IfModule mod_headers.c>\nHeader set X-Content-Type-Options \"nosniff\"\n</IfModule>";
    return @file_put_contents($p,$c)!==false; }
?>
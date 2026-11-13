<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
try {
 require (getenv('UNIQUE_CRM_PRIVATE_DIR') ?: dirname($_SERVER['DOCUMENT_ROOT']).'/.unique-tailoring-crm').'/app.php';
 if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);header('Allow: POST');echo json_encode(['error'=>'POST required.']);exit;}
 if(!in_array($_SERVER['HTTP_ORIGIN']??'',$config['allowed_origins'],true)){http_response_code(403);echo json_encode(['error'=>'This origin is not allowed.']);exit;}
 if(stripos($_SERVER['CONTENT_TYPE']??'','application/json')!==0)throw new InvalidArgumentException('JSON required.');
 $raw=file_get_contents('php://input',false,null,0,24001);if(strlen($raw)>24000)throw new InvalidArgumentException('Enquiry too long.');$data=json_decode($raw,true,32,JSON_THROW_ON_ERROR);if(!is_array($data))throw new InvalidArgumentException('Invalid enquiry.');
 if(!empty($data['website']))throw new InvalidArgumentException('Unable to accept this enquiry.');
 throttle('enquiry',10,900);$r=saveEnquiry($data,'Website',field($data,'submissionId',36,true));echo json_encode(['reference'=>'UT-'.str_pad((string)$r['id'],6,'0',STR_PAD_LEFT)]);
}catch(OverflowException $e){http_response_code(429);echo json_encode(['error'=>$e->getMessage()]);}
catch(InvalidArgumentException|JsonException $e){http_response_code(422);echo json_encode(['error'=>$e instanceof JsonException?'Invalid JSON.':$e->getMessage()]);}
catch(Throwable $e){error_log('Unique CRM API: '.get_class($e));http_response_code(503);echo json_encode(['error'=>'We could not save your enquiry. Please try again or call +971 4 582 0555.']);}

<?php
/*
 * SPDX-License-Identifier: AGPL-3.0-only
 * Original project author: Kaspars Mednis (madKaspars)
 * AI-generated implementation; see NOTICE.md for provenance.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
error_reporting(E_ALL);
set_error_handler(function($level,$message,$file,$line) { throw new ErrorException($message,0,$level,$file,$line); });
$module=dirname(__DIR__);
$ui=$argv[1] ?? getenv('ZABBIX_UI_ROOT');
if (!$ui || !is_file($ui.'/include/defines.inc.php')) { throw new RuntimeException('Pass the Zabbix ui source directory as the first argument.'); }
require $ui.'/include/defines.inc.php';
// CLI fixtures do not bootstrap optional frontend product banners.
$ZBX_FEATURE_FLAGS = ['banners_enabled' => false];
if (!function_exists('_')) { function _($s) { return $s; } }
function error($s) {}
function zbx_formatDomId($s){return str_replace(['[',']'],['_',''],$s);}
function zbx_date2str($f,$t) { return date('Y-m-d H:i:s',$t); }
function zbx_dbstr($v) { return "'".str_replace("'","''",$v)."'"; }
if (!defined('DATE_TIME_FORMAT_SECONDS')) define('DATE_TIME_FORMAT_SECONDS','Y-m-d H:i:s');
$pdo=new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$sql_log=[];
$fail_expiry_queries=false;
$fail_relationship_queries=false;
$fail_change_queries=false;
function DBselect($sql,$limit=null) { global $pdo,$sql_log,$fail_expiry_queries,$fail_relationship_queries,$fail_change_queries; $sql_log[]=$sql; if($fail_change_queries&&strpos($sql,'FROM acknowledges')!==false)return false; if($fail_expiry_queries&&strpos($sql,' AS known_clocks')!==false)return false; if($fail_relationship_queries&&strpos($sql,' AS cause_groups')!==false)return false; if($limit!==null)$sql.=' LIMIT '.$limit; return $pdo->query($sql); }
function DBfetch($result, $convertNulls = true) {
    $row = $result->fetch(PDO::FETCH_ASSOC);
    if ($row && $convertNulls) { foreach ($row as &$value) { if ($value === null) $value = '0'; } unset($value); }
    return $row;
}
function check($bool,$msg) { if(!$bool)throw new RuntimeException($msg); }
$ddl=[
'CREATE TABLE acknowledges(acknowledgeid INTEGER PRIMARY KEY,eventid INTEGER,clock INTEGER,action INTEGER,details TEXT,cep_ruleid INTEGER)',
'CREATE INDEX ack_event ON acknowledges(eventid,clock)',
'CREATE TABLE cep_window(cep_windowid INTEGER PRIMARY KEY,cep_ruleid INTEGER,group_by INTEGER,groupid INTEGER,hostid INTEGER,tags TEXT,tags_value TEXT,created_at INTEGER)',
'CREATE TABLE cep_rule(cep_ruleid INTEGER PRIMARY KEY,name TEXT,status INTEGER)',
'CREATE TABLE cep_rule_window(cep_ruleid INTEGER PRIMARY KEY,type INTEGER,duration TEXT,capacity TEXT)',
'CREATE TABLE hosts(hostid INTEGER PRIMARY KEY,host TEXT)',
'CREATE TABLE hstgrp(groupid INTEGER PRIMARY KEY,name TEXT)',
'CREATE TABLE cep_window_event(cep_windowid INTEGER,eventid INTEGER,event_index INTEGER)',
'CREATE TABLE events(eventid INTEGER PRIMARY KEY,name TEXT,clock INTEGER,source INTEGER,object INTEGER,objectid INTEGER)',
'CREATE TABLE event_recovery(eventid INTEGER PRIMARY KEY,r_eventid INTEGER,cep_ruleid INTEGER)',
'CREATE TABLE event_symptom(eventid INTEGER PRIMARY KEY,cause_eventid INTEGER NOT NULL)'];
foreach($ddl as $s)$pdo->exec($s);
$pdo->exec("INSERT INTO cep_rule VALUES(1,'North rule',0),(2,'Other',0)");
$pdo->exec("INSERT INTO cep_rule_window VALUES(1,1,'14d','0'),(2,1,'10m','2')");
$pdo->exec("INSERT INTO hosts VALUES(5,'storage-01')");
$pdo->exec("INSERT INTO cep_window VALUES(10,1,0,NULL,NULL,'','',1700000000),(20,2,1,NULL,5,'','',1700000000)");
$pdo->exec("INSERT INTO events VALUES(100,'<script>alert(1)</script>',1700000000,0,0,7),(101,'Recovered',1700000010,0,0,7),(102,'CEP closed',1700000020,0,0,7)");
$pdo->exec("INSERT INTO event_recovery VALUES(101,201,NULL),(102,202,1)");
$pdo->exec("INSERT INTO events VALUES(201,'Recovery',1700000060,0,0,7)");
$pdo->exec("INSERT INTO cep_window_event VALUES(10,100,0),(10,101,1),(10,102,2),(10,999,3)");
require $module.'/includes/Repository.php';
use Modules\CepWindowInspector\Includes\Repository as R;
foreach(['1','9223372036854775807'] as $id)check(R::validId($id), 'Valid ID rejected');
foreach(['0','-1','1 OR 1=1','1;DROP TABLE events','1.1','1e3',' 1','01',"1\n",'9223372036854775808'] as $id)check(!R::validId($id),'Invalid ID accepted:'.$id);
$before=count($sql_log);try{R::members('1 OR 1=1','','');throw new RuntimeException('Injection accepted');}catch(InvalidArgumentException $e){}check(count($sql_log)===$before,'Invalid ID queried');
$members=R::members('10','','')['rows'];
check(array_column($members,'problem_status')===['Open','Recovered / closed','Recovered / closed','Event history unavailable'],'Status classification');
check((string)$members[2]['closed_by_cep_ruleid']==='1','CEP recovery ID');
check((int)$members[1]['recovery_timestamp']===1700000060,'Recovery must use the linked recovery event clock');
check($members[0]['recovery_timestamp']===null && $members[2]['recovery_timestamp']===null,
    'Absent recovery history must remain NULL without hiding recovered membership');
$pdo->exec('UPDATE events SET clock=0 WHERE eventid=201');
check((string)R::members('10','','')['rows'][1]['recovery_timestamp']==='0','Valid epoch recovery lost');
$pdo->exec('UPDATE events SET clock=1700000060 WHERE eventid=201');
$counts=R::counts('10');check(array_map('intval',$counts)===['members'=>4,'open_count'=>1,'closed_count'=>2,'unavailable_count'=>1],'Counts');
check(count(R::windows('1','')['rows'])===1,'Rule filter');check(count(R::windows('','20')['rows'])===1,'Window cursor');
check(R::window('987')===null,'Missing window');
// Over 100 records, including duplicate indexes at the page boundary.
for($i=1;$i<=205;$i++)$pdo->exec('INSERT INTO cep_window_event VALUES(20,'.(1000+$i).','.intdiv($i,2).')');
$seen=[];$index='';$id='';do{$page=R::members('20',$index,$id);foreach($page['rows'] as $r)$seen[]=(string)$r['eventid'];$last=end($page['rows']);$index=(string)$last['event_index'];$id=(string)$last['eventid'];}while($page['more']);
check(count($seen)===205 && count(array_unique($seen))===205,'Member keyset paging skipped/duplicated rows');
// Large member id remains intact rather than floating point conversion.
$pdo->exec('INSERT INTO cep_window_event VALUES(10,9007199254740993,4)');
check((string)R::members('10','3','999')['rows'][0]['eventid']==='9007199254740993','Large event ID lost');
// Restricted controller tests with a minimal framework adapter.
class CWebUser {
    public static $type=3;
    public static $data=['username'=>'fixture-admin','debug_mode'=>GROUP_DEBUG_MODE_DISABLED,'lang'=>'en_GB'];
    static function getType(){return self::$type;}
}
class CControllerResponseFatal {}
class CControllerResponseData { public $data; function __construct($d){$this->data=$d;} function setTitle($t){} function disableView(){return $this;} }
class CController { public $input=[],$response; function hasInput($k){return array_key_exists($k,$this->input);} function getInput($k,$d=''){return $this->input[$k]??$d;} function validateInput($f){foreach($f as $k=>$v)if(isset($this->input[$k])&&!is_string($this->input[$k]))return false;return true;} function setResponse($r){$this->response=$r;} function disableCsrfValidation(){} }
require $ui.'/include/classes/helpers/CCepRuleHelper.php';
require $module.'/actions/Inspect.php';
class TestInspect extends Modules\CepWindowInspector\Actions\Inspect { function allowed(){return $this->checkPermissions();} function valid(){return $this->checkInput();} function execute(){ $this->doAction(); return $this->response->data; } }
$controller=new TestInspect();
foreach([0,1,2] as $type){CWebUser::$type=$type;check(!$controller->allowed(),'Non-super-admin allowed');}
CWebUser::$type=3;check($controller->allowed(),'Super admin denied');
foreach([['windowid'=>'-1'],['windowid'=>['1']],['after_index'=>'2'],['after_eventid'=>'10'],['after_index'=>'-1','after_eventid'=>'1']] as $input){$controller->input=$input;check(!$controller->valid(),'Invalid input accepted');}
$controller->input=['windowid'=>'10'];check($controller->valid(),'Valid controller input');$detail=$controller->execute();check(count($detail['members'])===5,'Detail route');
check($detail['view']==='table','Table is not the default view');
$controller->input=[];$overview=$controller->execute();check(count($overview['windows'])===2,'Overview route');
// Real Zabbix HTML classes to catch view compatibility and escaping issues.
$classmap=[];foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ui.'/include/classes')) as $f){if($f->isFile()&&$f->getExtension()==='php')$classmap[$f->getBasename('.php')]=$f->getPathname();}
spl_autoload_register(function($class)use($classmap){if(isset($classmap[$class]))require_once $classmap[$class];});
CPartial::registerDirectory($ui.'/app/partials');
require $ui.'/include/html.inc.php';
require $ui.'/include/js.inc.php';
function get_prepared_messages(array $options = []): ?string { return null; }
$render=function($data)use($module){ob_start();require $module.'/views/cep.window.inspect.php';return ob_get_clean();};
$html=$render($detail);check(strpos($html,'<script>alert(1)</script>')===false,'Unescaped event HTML');check(strpos($html,'&lt;script&gt;')!==false,'Event name missing');
$controller->input=['windowid'=>'987'];check(strpos($render($controller->execute()),'Window not found')!==false,'Missing window view');
$controller->input=['windowid'=>'20'];$render($controller->execute());
// Refresh reuses the authorized page and its exact member cursor; no separate data endpoint.
$controller->input=['windowid'=>'20','ruleid'=>'2','before'=>'30','after_index'=>'50','after_eventid'=>'1100','details'=>'1'];
$refresh_html=$render($controller->execute());
check(substr_count($refresh_html,'data-cep-refresh="1"')===1,'Missing or duplicate snapshot refresh button');
check(strpos($refresh_html,'data-window-id="20"')!==false && strpos($refresh_html,'data-window-present="1"')!==false,
    'Snapshot identity contract missing');
preg_match('/data-refresh-url="([^"]+)"/',$refresh_html,$refresh_match);
parse_str(parse_url(html_entity_decode($refresh_match[1],ENT_QUOTES),PHP_URL_QUERY),$refresh_args);
foreach(['action'=>'cep.window.inspect','windowid'=>'20','ruleid'=>'2','before'=>'30','after_index'=>'50','after_eventid'=>'1100','details'=>'1'] as $key=>$value) {
    check(($refresh_args[$key]??null)===$value,'Refresh lost request scope: '.$key);
}
$controller->input=['windowid'=>'987'];$missing_html=$render($controller->execute());
check(strpos($missing_html,'data-window-present="0"')!==false && strpos($missing_html,'data-cep-timeline')===false,
    'Expired window must clear member views');
check(strpos($missing_html,'data-cep-refresh="1"')!==false,'Expired window cannot be refreshed again');
// Empty list and missing-history views render without notices.
$overview['windows']=[];$overview['more']=false;$render($overview);
// Expiry calculations follow server age limits and fixed causal boundaries.
use Modules\CepWindowInspector\Includes\Timing as T;
foreach(['1'=>1,'1s'=>1,'10m'=>600,'2h'=>7200,'14d'=>1209600,'2w'=>1209600,
    '00014d'=>1209600,'2147483647'=>2147483647] as $raw=>$expected) {
    check(T::durationSeconds((string)$raw)===$expected,'Duration parse: '.$raw);
}
foreach([null,'','0','0d','-1','1.5h','1h30m','14 d',' 14d',"14d\n",'1y','1M',
    '2147483648','24856d','99999999999999999999999','{$CEP.DURATION}','{{$CEP.DURATION}.regsub("a","b")}'] as $raw) {
    check(T::durationSeconds($raw)===null,'Invalid or unresolved duration accepted');
}
$simple=['window_type'=>'1','duration'=>'14d','created_at'=>'1600000000'];
foreach($members as $i=>$member) {
    $expiry=T::forEvent($simple,$member);
    check($expiry['at']===($i===3?null:1701209600+$i*10),'Independent original-event timer including recovered members');
}
check(T::remaining(1701209600,1700000000)==='14d 00h 00m 00s','14-day countdown');
check(T::remaining(100,99)==='0d 00h 00m 01s','One second remaining');
check(T::remaining(100,100)==='Due now','Exact expiry boundary');
check(T::remaining(100,101)==='Overdue by 0d 00h 00m 01s','Overdue status');
check(T::remaining(null,100)==='Unavailable','Unknown remaining');
check(T::forEvent($simple,['event_timestamp'=>(string)PHP_INT_MAX])['at']===null,'Timestamp addition overflow');
check(T::forEvent($simple,['event_timestamp'=>'0'])['at']===1209600,'Zero epoch treated as present');
check(T::forEvent($simple,['event_timestamp'=>null])['at']===null,'Missing clock does not become zero');
check(T::forEvent(array_replace($simple,['window_type'=>null]),$members[0])['at']===null,'Unknown mode');
check(T::forEvent(array_replace($simple,['duration'=>'{$DURATION}']),$members[0])['at']===null,'Macro does not receive a guessed date');
foreach([3,4] as $type) {
    check(T::forEvent(array_replace($simple,['window_type'=>(string)$type]),$members[1])['at']===1701209610,
        'Sliding age deadline for tag correlation and pattern');
}
$causal=['window_type'=>'2','duration'=>'10m','created_at'=>'1700000000'];
check(T::forEvent($causal,$members[0])['at']===1700000600,'Causal shared period');
check(T::forEvent($causal,$members[1])['at']===1700000600,'Later causal member shares deadline');
check(T::forEvent($causal,['event_timestamp'=>null])['at']===1700000600,'Causal deadline independent of missing history');
check(T::forWindow($causal,null)['at']===1700000600,'Persisted causal deadline is not silently advanced');
$clock=(new DateTimeImmutable('2026-10-18 12:00:00',new DateTimeZone('Europe/Riga')))->getTimestamp();
$due=T::forEvent($simple,['event_timestamp'=>(string)$clock])['at'];
check($due-$clock===1209600,'14 days stays 1209600 seconds across DST');
check((new DateTimeImmutable('@'.$due))->setTimezone(new DateTimeZone('Europe/Riga'))->format('Y-m-d H:i:s')==='2026-11-01 11:00:00','Expiry renders a timezone-adjusted instant');
// Summaries include ALL pages, group windows independently, and retain recovered membership.
check(R::expirySummaries([])===[],'Empty batch');
check(T::forWindow($simple,R::expirySummaries(['10'])['10'])['at']===null,'Incomplete history prevents false whole-window estimate');
$pdo->exec("INSERT INTO cep_window VALUES(30,2,1,NULL,5,'','',1500000000),(40,2,1,NULL,6,'','',1500000000),(50,2,0,NULL,NULL,'','',1500000000)");
for($i=0;$i<105;$i++) {
    $id=2000+$i;$clock=1700000000+$i*60;
    $pdo->exec("INSERT INTO events VALUES($id,'Storage latency',$clock,0,0,7)");
    $pdo->exec("INSERT INTO cep_window_event VALUES(30,$id,$i)");
}
$pdo->exec('INSERT INTO event_recovery VALUES(2104,3104,NULL)');
$pdo->exec('INSERT INTO cep_window_event VALUES(40,2000,0)');
$summary=R::expirySummaries(['30','40','50']);
check((int)$summary['30']['members']===105 && (int)$summary['30']['known_clocks']===105,'All-page aggregate');
check(T::forWindow(R::window('30'),$summary['30'])['at']===1700006840,'Latest recovered member determines current window expiry');
check(T::forWindow(R::window('40'),$summary['40'])['at']===1700000600,'Independent host window estimate');
check(T::forWindow(R::window('50'),$summary['50'])['at']===null,'Empty sliding window has no invented deadline');
// The latest timestamp need not be the largest index; this mirrors time-based lifetime.
$pdo->exec('UPDATE events SET clock=1700010000 WHERE eventid=2001');
$controller->input=['windowid'=>'30'];$first=$controller->execute();
check(count($first['members'])===100 && $first['more'],'First expiry member page');
check($first['window']['expiry']['at']===1700010600,'Out-of-order maximum clock');
$last=end($first['members']);
$controller->input=['windowid'=>'30','after_index'=>(string)$last['event_index'],'after_eventid'=>(string)$last['eventid']];
$second=$controller->execute();
check(count($second['members'])===5 && !$second['more'],'Second expiry member page');
check($first['window']['expiry']===$second['window']['expiry'],'Window estimate remains consistent across pages');
$controller->input=[];$new_overview=$controller->execute();
$row=array_values(array_filter($new_overview['windows'],fn($row)=>(string)$row['cep_windowid']==='30'))[0];
check($row['expiry']===$first['window']['expiry'],'Overview and detail agree');
$html=$render($first);
$controller->input=['windowid'=>'30','details'=>'1'];
$html=$render($controller->execute());
foreach(['Estimated expiry','Recovery time','Time left','Expiry estimate',
    gmdate('Y-m-d H:i:s',1700010600),'Next','tr_events.php','Unix timestamp','Recovery event ID'] as $text) {
    check(strpos($html,htmlspecialchars($text,ENT_QUOTES))!==false,'Missing new or existing view element: '.$text);
}
// Null handling must survive the frontend DBfetch default conversion.
check($members[3]['event_timestamp']===null && $members[3]['name']===null,'NULL history was converted to zero');
check($members[0]['recovery_eventid']===null,'Missing recovery was converted to zero');
$controller->input=['windowid'=>'10'];
$missing_html=$render($controller->execute());
check(strpos($missing_html,'triggerid=0')===false,'Missing history generated an invalid trigger link');
check(strpos($missing_html,'1970-01-01')===false,'Missing history rendered as epoch');
check(strpos($missing_html,'Recovery event ID')===false,'Technical fields shown by default');
$controller->input=['windowid'=>'30','details'=>'1','before'=>'200'];
$technical_html=$render($controller->execute());
check(strpos($technical_html,'details=1')!==false && strpos($technical_html,'before=200')!==false,'Navigation lost display or list context');
foreach(['filter-space','filter_set','filter_rst','header-controls','section-head','pager-container','list-table'] as $class) check(strpos($technical_html,$class)!==false,'Missing native UI: '.$class);
// Rule names must open the native configuration dialog on both inspector views.
$rule_links = static function(string $html): array {
    preg_match_all('/<a\b[^>]*href="([^"]*)"[^>]*>/', $html, $matches);
    $links = [];
    foreach ($matches[1] as $href) {
        parse_str(parse_url(html_entity_decode($href, ENT_QUOTES), PHP_URL_QUERY) ?? '', $args);
        if (($args['action'] ?? '') === 'popup' && ($args['popup'] ?? '') === 'ceprule.edit') {
            $links[] = $args['cepruleid'] ?? '';
        }
    }
    return $links;
};
$controller->input=[];
$rule_overview=$controller->execute();
check(in_array('1', $rule_links($render($rule_overview)), true), 'Overview rule link does not open configuration');
$controller->input=['windowid'=>'10'];
$rule_detail=$controller->execute();
check($rule_links($render($rule_detail))===['1'], 'Detail rule link does not open configuration');
$rule_detail['window']['cep_ruleid']='9007199254740993';
check($rule_links($render($rule_detail))===['9007199254740993'], 'Rule link lost large identifier');
$rule_detail['window']['rule_name']=null;
check($rule_links($render($rule_detail))===[], 'Missing rule unexpectedly linked');
check(strpos($render($rule_overview), 'windowid=10')!==false, 'Window ID navigation lost');
// A failure of the new supplementary query must not hide the existing member list.
$fail_expiry_queries=true;
$controller->input=['windowid'=>'30'];$degraded=$controller->execute();
check($degraded['error']==='' && $degraded['timing_error']!=='' && count($degraded['members'])===100,'Supplementary-query failure broke the member page');
check($degraded['window']['expiry']['at']===null && $degraded['members'][0]['expiry']['at']===1700000600,'Event expiry survives summary failure');
check(strpos($render($degraded),'Storage latency')!==false,'Member view hidden after summary failure');
$controller->input=[];$degraded=$controller->execute();
check($degraded['error']==='' && count($degraded['windows'])===5,'Supplementary-query failure broke overview');
$render($degraded);$fail_expiry_queries=false;
check(count(array_filter($sql_log,fn($sql)=>!preg_match('/^SELECT /',$sql)))===0,'Non-read SQL');
// Timeline transport uses exactly the authorized page, keeps IDs as strings and never invents missing clocks.
foreach (['timeline', 'table'] as $mode) {
    $controller->input=['windowid'=>'10','view'=>$mode];
    check($controller->valid(), 'Valid timeline view rejected');
    $view_data=$controller->execute();
    $view_html=$render($view_data);
    check($view_data['view']===$mode, 'View preference not passed through');
    check(strpos($view_html,'view='.$mode)!==false, 'View preference lost in navigation');
    preg_match('/data-cep-timeline="([^"]+)"/', $view_html, $match);
    $payload=json_decode(html_entity_decode($match[1],ENT_QUOTES),true,512,JSON_THROW_ON_ERROR);
    check(count($payload['rows'])===5,'Timeline differs from table member page');
    check($payload['rows'][0]['name']==='<script>alert(1)</script>','Escaping changed event data');
    check($payload['rows'][3]['clock']===null && $payload['rows'][3]['expiry']===null,'Unknown history became a plotted date');
    check($payload['rows'][1]['recovery']===1700000060,'Recorded recovery time missing from timeline');
    check($payload['rows'][2]['recovery']===null && $payload['rows'][2]['state']==='closed'
        && $payload['rows'][2]['recoveryText']==='Unavailable','Missing recovery history invented a timestamp or lost status');
    check($payload['rows'][0]['recovery']===null && $payload['rows'][0]['recoveryText']==='—','Open event given a recovery');
    check($payload['windowExpiry']===null && $payload['windowExpiryText']==='Some event history is unavailable',
        'Incomplete whole-window history must not fall back to the latest visible member');
    check(strpos($view_html,'Recovery time')!==false && strpos($view_html,gmdate('Y-m-d H:i:s',1700000060))!==false,
        'Recovery time column absent from table');
    check(strpos($view_html,'id="cep-member-table"')<strpos($view_html,'id="cep-member-timeline"'),
        'Table is not the first panel');
    check($payload['rows'][3]['url']===null,'Unknown history created a link');
    check($payload['rows'][4]['id']==='9007199254740993','JSON lost a large event identifier');
    check(strpos($payload['rows'][0]['url'],'eventid=100')!==false,'Event detail URL lost');
    check(strpos($view_html,'id="cep-member-table"')!==false,'No-JS table fallback absent');
}
$controller->input=['view'=>'bogus']; check(!$controller->valid(),'Unrecognized display mode accepted');
$controller->input=['view'=>['timeline']]; check(!$controller->valid(),'Array display mode accepted');
check(T::durationSeconds('1m')===60 && T::durationSeconds('365d')===31536000,'Required timeline duration endpoints');
$view_data['window']['window_type']='2'; $view_data['window']['created_at']='1700000000';
$payload=json_decode(Modules\CepWindowInspector\Includes\Timeline::json($view_data),true);
check($payload['sharedBoundary']===true && $payload['periodStart']===1700000000,'Shared period metadata lost');
foreach ([$first,$second] as $page_data) {
    $payload=json_decode(Modules\CepWindowInspector\Includes\Timeline::json($page_data),true);
    check($payload['windowExpiry']===1700010600,'Whole-window boundary changes across member pages');
}
$view_data['window']['expiry']=T::forWindow($causal,null);
$payload=json_decode(Modules\CepWindowInspector\Includes\Timeline::json($view_data),true);
check($payload['windowExpiry']===1700000600,'Causal shared expiry boundary lost');
echo "PASS: whole-window expiry transport across member pages, missing history and shared periods.\n";
echo "PASS: timeline payload, view validation/persistence, no-JS fallback, large IDs, missing history, 1m/365d and shared-boundary metadata.\n";
echo "PASS: Table default, recovery-event join, exact recovery timestamp, missing recovery history and epoch zero.\n";
// Cause/symptom roles are current saved ranks, independent of clock order, recovery and cursor page.
use Modules\CepWindowInspector\Includes\Relationships as Rel;
check(count(array_filter($sql_log,fn($sql)=>strpos($sql,'event_symptom')!==false))===0,'Non-causal views queried roles');
$pdo->exec("INSERT INTO cep_rule VALUES(3,'Causal rule',0)");
$pdo->exec("INSERT INTO cep_rule_window VALUES(3,2,'10m','0')");
$pdo->exec("INSERT INTO cep_window VALUES(60,3,0,NULL,NULL,'','',1700000000),(70,3,0,NULL,NULL,'','',1700000000),(80,3,0,NULL,NULL,'','',1700000000)");
for($i=0;$i<105;$i++) {
    $id=4000+$i; $clock=$i===0?1700000100:1700000000;
    $pdo->prepare('INSERT INTO events VALUES(?,?,?,?,?,?)')->execute([$id,$i===0?'<svg onload=alert(1)> Cause':'Symptom '.$i,$clock,0,0,7]);
    $pdo->prepare('INSERT INTO cep_window_event VALUES(?,?,?)')->execute([60,$id,intdiv($i,2)]);
    if($i!==0)$pdo->exec('INSERT INTO event_symptom VALUES('.$id.',4000)');
}
$pdo->exec("INSERT INTO events VALUES(5001,'Recovery',1700000200,0,0,7)");
$pdo->exec('INSERT INTO event_recovery VALUES(4001,5001,NULL)');
$controller->input=['windowid'=>'60'];$causal_first=$controller->execute();
check(count($causal_first['members'])===100 && $causal_first['more'],'Causal first page');
check(array_map('intval',$causal_first['relationships']['summary'])===['cause_groups'=>1,'causes'=>1,'symptoms'=>104,'unknown'=>0],'All-page role totals');
check(Rel::member($causal_first['members'][0],$causal_first['relationships'])['role']==='cause','Cause depends on timestamp order');
check(Rel::member($causal_first['members'][0],$causal_first['relationships'])['symptomCount']===104,'Cause child count omits unloaded members');
check(Rel::member($causal_first['members'][1],$causal_first['relationships'])['role']==='symptom'
    && $causal_first['members'][1]['problem_status']==='Recovered / closed','Recovered symptom lost its role');
$last=end($causal_first['members']);
$controller->input=['windowid'=>'60','after_index'=>(string)$last['event_index'],'after_eventid'=>(string)$last['eventid']];
$causal_second=$controller->execute();
check(count($causal_second['members'])===5 && !$causal_second['more'],'Causal cursor skipped duplicate-index members');
check($causal_second['relationships']['summary']===$causal_first['relationships']['summary'],'Role summary changed on later page');
foreach($causal_second['members'] as $member) {
    $rel=Rel::member($member,$causal_second['relationships']);
    check($rel['role']==='symptom' && $rel['causeId']==='4000' && $rel['causeInWindow']===true,'Later page invented a cause');
    check(strpos($rel['causeUrl'],'eventid=4000')!==false,'Cause link missing across pages');
}
$role_html=$render($causal_second);
check(strpos($role_html,'<svg onload=alert(1)>')===false && strpos($role_html,'&lt;svg')!==false,'Cause name not escaped');
check(strpos($role_html,'cep-relationship-summary')!==false && strpos($role_html,'cep-role-symptom')!==false,'Approved role UI absent');
$role_payload=json_decode(Modules\CepWindowInspector\Includes\Timeline::json($causal_second),true);
check($role_payload['causal'] && $role_payload['rows'][0]['relationship']['causeId']==='4000','Timeline role differs from table');
$before=count($sql_log);R::relationships('60',$causal_first['members']);
check(count($sql_log)-$before===4,'Relationship reads grew per member');
// A current cause can be outside this window; retain large IDs without float conversion.
$pdo->exec("INSERT INTO events VALUES(9007199254740995,'External cause',1700000000,0,0,8)");
$pdo->exec('UPDATE event_symptom SET cause_eventid=9007199254740995 WHERE eventid=4002');
$controller->input=['windowid'=>'60'];$mixed=$controller->execute();
$external=Rel::member($mixed['members'][2],$mixed['relationships']);
check($external['causeId']==='9007199254740995' && $external['causeInWindow']===false
    && strpos($external['causeUrl'],'9007199254740995')!==false,'External cause or large ID lost');
check((int)$mixed['relationships']['summary']['cause_groups']===2,'Multiple recorded cause groups collapsed');
$pdo->exec('DELETE FROM events WHERE eventid=9007199254740995');
$mixed=$controller->execute();$external=Rel::member($mixed['members'][2],$mixed['relationships']);
check($external['role']==='symptom' && $external['causeUrl']===null && $external['causeInWindow']===null,'Missing cause history guessed a link/membership');
$pdo->exec('INSERT INTO cep_window_event VALUES(60,888,200),(60,999,201)');
$pdo->exec('INSERT INTO event_symptom VALUES(999,4000)');
$missing=R::members('60','199','4104',true)['rows'];
check(Rel::member($missing[0],null)['role']==='unknown','Missing event without link labelled cause');
check(Rel::member($missing[1],null)['role']==='symptom','Recorded link lost when member history missing');
$pdo->exec('DELETE FROM event_symptom WHERE eventid=4001');
$changed=$controller->execute();
check(Rel::member($changed['members'][1],$changed['relationships'])['role']==='cause','Current rank changes ignored');
$pdo->exec('INSERT INTO event_symptom VALUES(4001,4000)');
$fail_relationship_queries=true;$degraded_roles=$controller->execute();$fail_relationship_queries=false;
check($degraded_roles['error']==='' && count($degraded_roles['members'])===100 && $degraded_roles['relationships']===null,'Summary failure hid member data');
check(Rel::member($degraded_roles['members'][1],null)['role']==='symptom'
    && Rel::member($degraded_roles['members'][0],null)['symptomCount']===null,'Summary failure invented zero counts or lost role');
check(strpos($render($degraded_roles),'Relationship summary unavailable')!==false,'Missing role summary not disclosed');
$controller->input=['windowid'=>'70'];$empty_roles=$controller->execute();$render($empty_roles);
check((int)$empty_roles['relationships']['summary']['cause_groups']===0,'Empty causal window gained a cause');
for($i=0;$i<8;$i++) {
    $id=6000+$i;$pdo->exec("INSERT INTO events VALUES($id,'Separate cause',1700000000,0,0,7)");
    $pdo->exec("INSERT INTO cep_window_event VALUES(80,$id,$i)");
}
$controller->input=['windowid'=>'80'];$many=$controller->execute();
check(count($many['relationships']['groups'])===5 && (int)$many['relationships']['summary']['cause_groups']===8,'Cause summary not bounded');
check(strpos($render($many),'Showing 5 of 8 cause groups.')!==false,'Truncated groups not disclosed');
check(count(array_filter($sql_log,fn($sql)=>!preg_match('/^SELECT /',$sql)))===0,'Non-read relationship SQL');
echo "PASS: current roles, all-page cause context/counts, duplicate-index paging, recovered symptoms, outside/missing causes, large IDs, safe HTML/JSON, rank changes, bounded groups, unknown roles and supplementary-query failure.\n";
echo 'PASS: existing validation, access, SQL status, missing history, large IDs, keyset paging, controller paths, real HTML views and XSS escaping; expiry, countdown, all modes, all-page summaries, independent groups, recovered members, macros, DST, overflow and supplementary-query failure. '.count($sql_log)." SELECT queries executed.\n";

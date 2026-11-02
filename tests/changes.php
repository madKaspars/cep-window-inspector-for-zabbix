<?php
/*
 * SPDX-License-Identifier: AGPL-3.0-only
 * Original project author: Kaspars Mednis (madKaspars)
 * AI-generated implementation; see NOTICE.md for provenance.
 */
require __DIR__.'/regression.php';
use Modules\CepWindowInspector\Includes\ChangeRepository as CR;
use Modules\CepWindowInspector\Includes\Repository as R;
use Modules\CepWindowInspector\Includes\Changes as CH;
// Real severity helper, with configured names supplied without a live API connection.
(new ReflectionProperty(CSettingsHelper::class, 'params'))->setValue(null,
    ['severity_name_0'=>'Not classified','severity_name_1'=>'Information','severity_name_2'=>'Warning',
        'severity_name_3'=>'Average','severity_name_4'=>'High','severity_name_5'=>'Disaster']);
require $module.'/actions/Changes.php';
class TestChanges extends Modules\CepWindowInspector\Actions\Changes {
    function allowed(){return $this->checkPermissions();}
    function valid(){return $this->checkInput();}
    function execute(){ $this->doAction(); return json_decode($this->response->data['main_block'],true,512,JSON_THROW_ON_ERROR); }
}
$change_controller=new TestChanges();
foreach([0,1,2] as $type){CWebUser::$type=$type;check(!$change_controller->allowed(),'Change history exposed to non-super-admin');}
CWebUser::$type=3;check($change_controller->allowed(),'History denied to super admin');
$base=['windowid'=>'10','eventid'=>'100','snapshot'=>'1700005000'];
foreach([[],array_replace($base,['eventid'=>'1 OR 1=1']),array_replace($base,['snapshot'=>strval(time()+1)]),
    array_replace($base,['snapshot'=>'0']),array_replace($base,['eventid'=>['100']]),
    array_replace($base,['direction'=>'next']),array_replace($base,['direction'=>'garbage']),
    array_replace($base,['anchor'=>'1']),array_replace($base,['focus'=>'01'])] as $input) {
    $change_controller->input=$input;check(!$change_controller->valid(),'Bad history input accepted');
}
$change_controller->input=$base;check($change_controller->valid(),'Valid history request rejected');
$insert=$pdo->prepare('INSERT INTO acknowledges VALUES(?,?,?,?,?,?)');
$ops=['cep'=>[['operation'=>4,'severity'=>['old'=>3,'new'=>4]],
    ['operation'=>11,'tag'=>['tag'=>'dc','value'=>'<img src=x onerror=alert(1)>']]]];
$large='9007199254740993';
for($i=0;$i<125;$i++)$insert->execute([(string)((int)$large+$i),100,1700000100+intdiv($i,3),2048,json_encode($ops),1]);
$insert->execute([1,100,1700001000,4,'{}',1]); // Manual update must not appear.
$insert->execute([2,100,1700005001,2048,json_encode($ops),1]); // After snapshot.
$insert->execute([3,101,1700000100,2048,'{broken',1]);
$insert->execute([4,102,1700000100,2048,'{"cep":[{"operation":999}]}',1]);
// The reported example: three operations saved in one update, hence one timeline marker.
$pdo->exec("INSERT INTO events VALUES(1465,'We have a problem 2',1700000000,0,0,7)");
$pdo->exec('INSERT INTO cep_window_event VALUES(10,1465,300)');
$three_ops = json_encode(['cep'=>[
    ['operation'=>19,'cause'=>['eventid'=>1464]],
    ['operation'=>6,'severity'=>['old'=>4,'new'=>3]],
    ['operation'=>11,'tag'=>['tag'=>'type','value'=>'symptom']]
]]);
$insert->execute([900,1465,1700000100,2048,$three_ops,1]);
$controller->input=['windowid'=>'10'];$detail=$controller->execute();
$summary=CR::summaries($detail['members'],1700005000,'1');
check($summary[100]['count']==='125' && count($summary[100]['markers'])===20,'Exact count or marker cap incorrect');
check($summary[100]['label']==='125 updates · ≥40 changes' && !$summary[100]['changesComplete'],
    'A bounded detail sample was labelled as an exact operation total');
check($summary[1465]['label']==='1 update · 3 changes' && $summary[1465]['changesComplete']
    && count($summary[1465]['markers'])===1 && $summary[1465]['markers'][0]['changeCount']===3,
    'One recorded update confused with its three operations');
check($summary[101]['label']==='1 update · changes unavailable','Unreadable details labelled zero changes');
check($summary[102]['label']==='1 update · 1 change','Singular or unknown operation count');
check(CH::changeCount('{"cep":[]}')===['known'=>0,'complete'=>true],'Valid empty operation list');
check(CH::changeCount('{"cep":{}}')===['known'=>0,'complete'=>false],'Object counted as a valid empty list');
check(CH::changeCount('{"cep":[null,{"operation":999}]}')===['known'=>1,'complete'=>false],'Partial malformed details');
check(CH::countLabel('1',1,false)==='1 update · ≥1 change','Partial singular wording');
foreach(['null','[]','{"cep":null}','{"cep":"bad"}','{"cep":[{"operation":"4"}]}'] as $bad) {
    check(!CH::changeCount($bad)['complete'],'Invalid details marked complete');
}
$single = CR::history('10','1465',1700005000);
$single_html = CH::popup($single,array_replace($base,['eventid'=>'1465']));
check(strpos($single_html,'1 update · 3 changes')!==false && strpos($single_html,'Update 1')!==false
    && substr_count($single_html,'class="cep-change-operation"')===3,'Popup grouping or totals');
$insert->execute([901,1465,1700000100,2048,json_encode($ops),1]);
$twice = CR::summaries($detail['members'],1700005000,'1')[1465];
check($twice['label']==='2 updates · 5 changes' && count($twice['markers'])===2,'Same-second updates merged in counts');
$pdo->exec('DELETE FROM acknowledges WHERE acknowledgeid=901');
check($summary[100]['markers'][0]['id']===(string)((int)$large+105),'Latest marker IDs lost precision/order');
check($summary[999]['count']===null,'Missing event inferred unchanged');
check($summary[101]['markers'][0]['icon']===ZBX_ICON_CEP,'Malformed history hid marker');
check(CH::markerIcon(json_encode(['cep'=>[$ops['cep'][0]]]))===ZBX_ICON_ARROW_UP_SMALL,'Severity up symbol');
check(CH::markerIcon('{"cep":[{"operation":6,"severity":{"old":4,"new":3}}]}')===ZBX_ICON_ARROW_DOWN_SMALL,'Severity down symbol');
check(CH::description($ops['cep'][0])==='Severity: Average → High','Before/after severity format');
check(strpos(CH::description(['operation'=>19,'cause'=>['eventid'=>$large]]),$large)!==false,'Cause ID lost');
foreach(['null','1','[]','{"cep":"bad"}','{"cep":[null,1,{},"text"]}'] as $raw) {
    foreach(CH::operations($raw) as $operation) {CH::description($operation);CH::icon($operation);}
}
check(CH::operations('{"cep":[{"operation":19,"cause":{"eventid":9223372036854775808}}]}')[0]['cause']['eventid']==='9223372036854775808','Big JSON ID precision');
$seen=[];$anchor='';$direction='first';$pages=[];
do {
    $page=CR::history('10','100',1700005000,$direction,$anchor);$pages[]=$page;
    foreach($page['rows'] as $row)$seen[]=(string)$row['acknowledgeid'];
    $anchor=(string)end($page['rows'])['acknowledgeid'];$direction='next';
} while($page['has']['next']);
check(count($pages)===3 && count($pages[0]['rows'])===50 && count($pages[2]['rows'])===25,'History page sizes');
check(array_column($pages,'offset')===[0,50,100],'Global update numbers do not follow pagination');
check(count($seen)===125 && count(array_unique($seen))===125 && $seen[0]===$large,'Paging duplicated/skipped tied timestamps or large IDs');
$back=CR::history('10','100',1700005000,'previous',(string)$pages[2]['rows'][0]['acknowledgeid']);
check(array_column($back['rows'],'acknowledgeid')===array_column($pages[1]['rows'],'acknowledgeid'),'Previous page order');
$focus=CR::history('10','100',1700005000,'focus',$summary[100]['markers'][0]['id']);
check(in_array($summary[100]['markers'][0]['id'],array_map('strval',array_column($focus['rows'],'acknowledgeid')),true),'Marker did not include selected update');
check((string)$focus['rows'][0]['acknowledgeid']===(string)((int)$large+81),'Marker omitted preceding history context');
foreach([['10','100','next','3'],['20','100','first',''],['10','100','next','2']] as [$w,$e,$d,$a]) {
    try {CR::history($w,$e,1700005000,$d,$a);throw new Exception('Unauthorized member/cursor accepted');}catch(RuntimeException $e){}
}
$out=$change_controller->execute();
check(isset($out['data']) && strpos($out['data'],'Severity: Average → High')!==false,'Native popup render');
check(strpos($out['data'],'<img')===false && strpos($out['data'],'&lt;img')!==false,'History XSS');
check(strpos($out['data'],'data-cep-history-page')!==false,'Popup paging missing');
check(substr_count($out['data'],'data-change-id=')===50,'Popup unbounded');
check(strpos($out['data'],'On this page: 50 updates · 100 changes')!==false,'Page subtotal presented as entire history');
$later_html=CH::popup($pages[2],$base);
check(strpos($later_html,'On this page: 25 updates · 50 changes')!==false
    && strpos($later_html,'Update 101')!==false && strpos($later_html,'Update 125')!==false,'Later page totals or update numbers');
$change_controller->input=array_replace($base,['eventid'=>'101']);
check(strpos($change_controller->execute()['data'],'Change details unavailable.')!==false,'Broken JSON erased history');
$change_controller->input=array_replace($base,['eventid'=>'102']);
$out=$change_controller->execute()['data'];check(strpos($out,'Unknown operation (999)')!==false ,'Unknown operation fallback');
$pdo->exec('DELETE FROM cep_window_event WHERE cep_windowid=10 AND eventid=101');
$change_controller->input=array_replace($base,['eventid'=>'101']);
check(isset($change_controller->execute()['error']),'Evicted member still served history');
$fail_change_queries=true;
$controller->input=['windowid'=>'10'];$degraded=$controller->execute();
check($degraded['error']==='' && $degraded['members'] && $degraded['changes']===[] && $degraded['changes_error']!=='','History failure hid working window');
check(strpos($render($degraded),'Change history is temporarily unavailable.')!==false,'History failure not disclosed');
$change_controller->input=$base;check(isset($change_controller->execute()['error']),'Failed popup read not reported');
$fail_change_queries=false;
$many_members=R::members('20','','')['rows'];
$logid=50000;
foreach($many_members as $member)for($i=0;$i<21;$i++)$insert->execute([$logid++,$member['eventid'],1700000200+$i,2048,'{"cep":[{"operation":8}]}',2]);
$before=count($sql_log);$bounded=CR::summaries($many_members,1700005000,'2');
check(count($sql_log)===$before+1 && count($bounded)===100,'Marker summaries became per-event queries');
check(array_sum(array_map(fn($row)=>count($row['markers']),$bounded))===2000,'Maximum page markers not bounded');
foreach($bounded as $row)check($row['count']==='21','Capped markers changed exact count');
// One event belongs to two different rules' windows. Other rules must not affect any history surface.
$pdo->exec("INSERT INTO cep_window VALUES(110,2,0,NULL,NULL,'','',1700000000),(111,1,0,NULL,NULL,'','',1700000000),(112,999,0,NULL,NULL,'','',1700000000),(113,9007199254740995,0,NULL,NULL,'','',1700000000)");
$pdo->exec('INSERT INTO cep_window_event VALUES(110,100,0),(110,1465,1),(111,100,0),(112,100,0),(113,100,0)');
$other_ops=json_encode(['cep'=>[['operation'=>11,'tag'=>['tag'=>'other-rule-tag','value'=>'rule-two']]]]);
for($i=0;$i<85;$i++)$insert->execute([20000+$i,100,1700000090+intdiv($i,3),2048,$other_ops,2]);
// Newer updates from another rule must not crowd the selected rule out of its latest-20 batch.
for($i=0;$i<21;$i++)$insert->execute([21000+$i,100,1700004000+$i,2048,$other_ops,2]);
$insert->execute([22000,100,1700004010,2048,$other_ops,null]);
$insert->execute([22001,100,1700004010,2048,$other_ops,999]);
$insert->execute([22002,100,1700004010,2048,$other_ops,'9007199254740995']);
$controller->input=['windowid'=>'10'];$rule_one=$controller->execute();
// The controller's current snapshot includes one additional own-rule update newer than the fixed test snapshot.
$one=CR::summaries($rule_one['members'],1700005000,'1')[100];
check($one['count']==='125' && $one['label']==='125 updates · ≥40 changes'
    && array_column($one['markers'],'id')===array_column($summary[100]['markers'],'id'),'Other rules changed counts or crowded matching markers out');
$controller->input=['windowid'=>'110'];$rule_two=$controller->execute();
$two=CR::summaries($rule_two['members'],1700005000,'2');
check($two[100]['count']==='106' && $two[100]['label']==='106 updates · ≥20 changes','Second rule inherited first rule updates');
check($two[1465]['count']==='0' && $two[1465]['markers']===[],'No own-rule history inherited another rule update');
$rule_two_html=$render($rule_two);
check(strpos($rule_two_html,'1 update · 3 changes')===false,'Table retained unfiltered update count');
$payload=json_decode(Modules\CepWindowInspector\Includes\Timeline::json($rule_two),true);
check($payload['rows'][0]['changes']['count']==='106' && $payload['rows'][1]['changes']['count']==='0','Timeline payload not rule scoped');
foreach([['10','1',125],['110','2',106]] as [$windowid,$ruleid,$expected]) {
    $seen=[];$anchor='';$direction='first';$offset=0;
    do {
        $page=CR::history($windowid,'100',1700005000,$direction,$anchor);
        check($page['offset']===$offset,'Other-rule records affected chronological update numbers');
        foreach($page['rows'] as $row) {check((string)$row['cep_ruleid']===$ruleid,'Foreign rule leaked into page');$seen[]=(string)$row['acknowledgeid'];}
        $offset+=count($page['rows']);$anchor=(string)end($page['rows'])['acknowledgeid'];$direction='next';
    } while($page['has']['next']);
    check(count($seen)===$expected && count(array_unique($seen))===$expected,'Scoped paging dropped/duplicated updates');
    $previous=CR::history($windowid,'100',1700005000,'previous',(string)$page['rows'][0]['acknowledgeid']);
    check($previous['offset']===50 && count($previous['rows'])===50,'Scoped Previous page');
}
foreach([['10','20000'],['110',$large],['10','22000'],['10','22001']] as [$windowid,$foreign]) {
    foreach(['focus','next','previous'] as $direction) {
        try {CR::history($windowid,'100',1700005000,$direction,$foreign);throw new Exception('Foreign-rule cursor accepted');}catch(RuntimeException $e){}
    }
}
$change_controller->input=array_replace($base,['ruleid'=>'2','cep_ruleid'=>'2']);
check($change_controller->valid(),'Extra display filter caused invalid request');
$scoped_html=$change_controller->execute()['data'];
check(strpos($scoped_html,'other-rule-tag')===false && strpos($scoped_html,'Severity: Average')!==false,'Client-supplied rule overrode saved window');
check(strpos(CH::popup(CR::history('112','100',1700005000),array_replace($base,['windowid'=>'112'])),'Rule unavailable')!==false,'Deleted rule metadata hides matching history');
check((string)CR::history('113','100',1700005000)['rows'][0]['cep_ruleid']==='9007199254740995','Large rule identifier changed precision');
$controller->input=['windowid'=>'111'];$same_rule=$controller->execute();
check(CR::summaries($same_rule['members'],1700005000,'1')[100]===$one,'Windows of the same rule cannot be distinguished by these logs');
$before=count($sql_log);
try {CR::summaries($rule_one['members'],1700005000,'1 OR 1=1');throw new Exception('Invalid rule accepted');}catch(InvalidArgumentException $e){}
check(count($sql_log)===$before,'Invalid rule reached database');
echo "PASS: shared events isolated by saved window rule across counts, markers, popup pages/ordinals and focus; foreign/unknown rules and cursors excluded; rule filter cannot be overridden by client input.\n";
check(count(array_filter($sql_log,fn($sql)=>!preg_match('/^SELECT /',$sql)))===0,'Non-read history SQL');
echo "PASS: native history HTML, authorized event membership, cross-event cursors, snapshot cutoff, tied-time paging, focus, exact record counts, bounded markers, configured severity names, malformed data, missing rules and XSS.\n";

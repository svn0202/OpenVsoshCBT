#!/usr/bin/env python3
"""Synthetic endpoint and Chromium regressions for reason-specific save failures."""
import json,pathlib,subprocess,tempfile,shutil
from urllib.parse import urlsplit,parse_qs
from playwright.sync_api import sync_playwright
from browser_answer_save_analysis import markup
ROOT=pathlib.Path(__file__).resolve().parents[1]

def endpoint_tests():
 for case,expected in [('active','saved'),('closed','attempt_closed'),('blocked','attempt_blocked'),('timeout','time_expired'),('foreign','access_denied'),('session','session_required'),('csrf','csrf_failed'),('invalid','invalid_request'),('db','error'),('refresh','csrf_refreshed')]:
  with tempfile.TemporaryDirectory() as directory:
   p=pathlib.Path(directory)
   for d in ['public/code','public/config','shared/code']:(p/d).mkdir(parents=True,exist_ok=True)
   shutil.copy(ROOT/'shared/code/tce_functions_request_log.php',p/'shared/code/tce_functions_request_log.php')
   shutil.copy(ROOT/'public/code/tce_test_answer_save.php',p/'public/code/tce_test_answer_save.php')
   shutil.copy(ROOT/'shared/code/tce_functions_answer_access.php',p/'shared/code/tce_functions_answer_access.php')
   (p/'public/config/tce_config.php').write_text("<?php define('K_AUTH_PUBLIC_TEST_EXECUTE',3);define('K_TABLE_TESTS_LOGS','logs');define('K_TABLE_TEST_USER','users');define('K_TABLE_TESTS','tests');")
   (p/'shared/code/tce_authorization.php').write_text("<?php $_SESSION=['session_user_id'=>1]; if($GLOBALS['case']==='session') F_tmf_answer_json(403,['status'=>'session_required']);")
   (p/'shared/code/tce_functions_test.php').write_text('''<?php
$db=null;
function f_get_boolean($v){return (bool)$v;}
function F_db_query(...$args){return $GLOBALS['case']==='db'?false:true;}
function F_db_fetch_array($r){$c=$GLOBALS['case'];return ['testuser_user_id'=>$c==='foreign'?2:1,'testuser_test_id'=>1,'testuser_status'=>$c==='closed'?4:1,'testuser_close_reason'=>$c==='blocked'?'blocked':null,'testuser_pregenerated'=>false,'test_begin_time'=>date('Y-m-d H:i:s',time()-100),'test_end_time'=>date('Y-m-d H:i:s',time()+100),'testuser_creation_time'=>date('Y-m-d H:i:s',time()-($c==='timeout'?4000:10)),'test_duration_time'=>60];}
function check_csrf_token_for_script(...$args){return $GLOBALS['case']!=='csrf';}
function f_get_csrf_token_for_script($s){return 'fresh';}
function f_tmf_answer_operation_is_valid($op){return strlen($op)===32;}
function f_execute_test($id){return true;}
function f_tmf_save_question_answer(...$args){return ['status'=>'saved','version'=>2];}
''')
   runner='''$GLOBALS['case']=$argv[1];$_SERVER['REQUEST_METHOD']=$argv[1]==='refresh'?'GET':'POST';$_POST=['testid'=>1,'testlogid'=>2,'csrf_token'=>'old','answer_version'=>1,'answer_operation'=>str_repeat('a',32)];if($argv[1]==='invalid')$_POST['testid']=0;$_GET=$_POST+['action'=>'refresh_csrf'];require 'tce_test_answer_save.php';'''
   r=subprocess.run(['php','-r',runner,case],cwd=p/'public/code',capture_output=True,text=True)
   assert r.returncode==0,r.stderr
   result=json.loads(r.stdout);assert result['status']==expected,(case,result)
 for level,reason in [(0,'session_required'),(1,'access_denied')]:
  code="define('OPENVSOSH_ANSWER_API',true); $_SESSION=['session_user_level'=>(int)$argv[1]]; function F_tmf_answer_json($http,$payload):never {echo json_encode([$http,$payload]);exit();} require $argv[2]; f_login_form();"
  r=subprocess.run(['php','-r',code,str(level),str(ROOT/'shared/code/tce_functions_authorization.php')],capture_output=True,text=True)
  assert r.returncode==0,r.stderr
  assert json.loads(r.stdout)==[403,{'status':reason}]
 print('Endpoint: 10 scenarios; real authorization hook: 2 scenarios passed')

def browser_tests():
 with sync_playwright() as pw:
  browser=pw.chromium.launch()
  for case in ['session_required','attempt_closed','time_expired','attempt_blocked','access_denied','invalid_request','unknown_html','null_json','csrf_retry','csrf_twice','csrf_session','csrf_foreign']:
   ctx=browser.new_context();page=ctx.new_page();calls=[];refresh=[];navigation=[]
   def handler(route):
    req=route.request;path=urlsplit(req.url).path
    if path.endswith('tce_test_answer_save.php'):
     if req.method=='GET':
      if case in ('csrf_session','csrf_foreign'):
       refresh.append(req.url);route.fulfill(status=403,content_type='application/json',body=json.dumps({'status':'session_required' if case=='csrf_session' else 'access_denied'}));return
      refresh.append(req.url);route.fulfill(content_type='application/json',body=json.dumps({'status':'csrf_refreshed','csrf_token':'fresh'}));return
     calls.append(req.post_data)
     if case=='csrf_retry' and len(calls)==2:
      route.fulfill(content_type='application/json',body='{"status":"saved","version":2}');return
     if case=='unknown_html':route.fulfill(status=403,content_type='text/html',body='Forbidden');return
     if case=='null_json':route.fulfill(status=403,content_type='application/json',body='null');return
     reason='csrf_failed' if case.startswith('csrf_') else case
     route.fulfill(status=422 if case=='invalid_request' else 403,content_type='application/json',body=json.dumps({'status':reason}));return
    if path.endswith('tce_test_execute.php'):navigation.append(req.method);route.fulfill(content_type='text/html',body=markup('2'));return
    route.fulfill(body='{}',content_type='application/json')
   page.route('**/*',handler);page.goto('https://analysis.invalid/fixture');page.set_content(markup().replace('<textarea','<input name="csrf_token" value="old"><textarea'))
   page.add_script_tag(path=str(ROOT/'shared/jscripts/mobile-exam.js'));page.locator('textarea').fill('KEEP_ME')
   page.locator('[name=nextquestion]').click();page.wait_for_timeout(300)
   if case=='csrf_retry':
    assert navigation==['GET'] and len(calls)==2 and len(refresh)==1
    # Refresh must preserve operation and version of the original attempt.
    import re
    op=lambda b:re.search('name="answer_operation"\r\n\r\n([^\r]+)',b).group(1)
    assert op(calls[0])==op(calls[1]);assert 'fresh' in calls[1]
   else:
    assert not navigation,(case,navigation)
    assert page.locator('#testform').get_attribute('aria-busy')=='false',case
    assert page.evaluate("() => {const e=new Event('beforeunload',{cancelable:true});window.dispatchEvent(e);return e.defaultPrevented;}"),case
    assert page.locator('textarea').input_value()=='KEEP_ME',case
    assert page.locator('#answer-save-status').inner_text(),case
    assert len(calls)==(2 if case=='csrf_twice' else 1),case
    if case=='session_required':assert page.locator('#answer-login-link').get_attribute('target')=='_blank'
   ctx.close()
  browser.close()
 print('Chromium: 12 scenarios passed')

if __name__=='__main__':endpoint_tests();browser_tests()

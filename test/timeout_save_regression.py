#!/usr/bin/env python3
"""Run the full execute controller with synthetic save outcomes and boundary stubs."""
import json,pathlib,subprocess,tempfile,shutil
ROOT=pathlib.Path(__file__).resolve().parents[1]
results=[]
for outcome, mode in [(o, m) for o in ['saved','conflict','error'] for m in ['last','general']]:
 with tempfile.TemporaryDirectory(prefix='ov-timeout-analysis-') as d:
  p=pathlib.Path(d)
  for path in ['public/code','public/config','shared/code']:(p/path).mkdir(parents=True,exist_ok=True)
  shutil.copy(ROOT/'shared/code/tce_functions_request_log.php',p/'shared/code/tce_functions_request_log.php')
  shutil.copy(ROOT/'public/code/tce_test_execute.php',p/'public/code/tce_test_execute.php')
  (p/'public/config/tce_config.php').write_text('''<?php
define('K_AUTH_PUBLIC_TEST_EXECUTE',3);define('K_NEWLINE',"\\n");
$l=['t_test_execute'=>'test','hp_test_execute'=>'test','a_meta_language'=>'en','a_meta_dir'=>'ltr','a_meta_charset'=>'UTF-8','w_index'=>'Index'];
''')
  for f in ['tce_authorization.php','tce_functions_form.php']:(p/'shared/code'/f).write_text('<?php')
  (p/'shared/code/tce_functions_test.php').write_text('''<?php
function f_get_test_password($id){return '';}
function f_execute_test($id){return true;}
function f_get_test_data($id){return [];}
function f_get_boolean($v){return (bool)$v;}
function f_is_right_testlog_user($a,$b){return true;}
function f_tmf_save_question_answer(...$args){$GLOBALS['calls'][]=['save',$GLOBALS['outcome']];return ['status'=>$GLOBALS['outcome'],'version'=>2];}
function f_terminate_user_test($id,$reason){$GLOBALS['calls'][]=['terminate',$id,$reason];}
''')
  (p/'public/code/run.php').write_text('''<?php
$outcome=$argv[1];$calls=[];
$_POST=$_REQUEST=['testid'=>1,'testlogid'=>2,'answer_version'=>1,'answertext'=>'LOCAL_ANSWER','forceterminate'=>'lasttimedquestion','final_save_json'=>'1'];
if($argv[2]==='general')unset($_POST['forceterminate'],$_REQUEST['forceterminate']);
$_SERVER['REQUEST_METHOD']='POST';$_SERVER['SCRIPT_NAME']='/public/code/tce_test_execute.php';
$_SESSION=['session_user_id'=>1,'session_user_ip'=>'127.0.0.1'];
ob_start();register_shutdown_function(function(){$body=ob_get_clean();echo json_encode(['body'=>$body,'outcome'=>$GLOBALS['outcome'],'calls'=>$GLOBALS['calls'],'error'=>error_get_last()]);});
require 'tce_test_execute.php';
''')
  r=subprocess.run(['php','run.php',outcome,mode],cwd=p/'public/code',capture_output=True,text=True)
  assert r.returncode==0,(r.stdout,r.stderr)
  row=json.loads(r.stdout);assert row['error'] is None,row
  assert row['calls']==[['save',outcome]]+([['terminate',1,'timeout']] if mode=='last' else []),row
  assert json.loads(row['body'])['status']==outcome,row
  results.append(row)
print(json.dumps(results,indent=2))

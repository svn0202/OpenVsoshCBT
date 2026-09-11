#!/usr/bin/env python3
"""Check shared correlation IDs, malformed headers, and log field allowlisting."""
import json, pathlib, subprocess, tempfile
ROOT = pathlib.Path(__file__).resolve().parents[1]
for incoming in ['a'*32, '', 'secret-header\r\nforged', 'b'*33]:
    with tempfile.TemporaryDirectory() as d:
        log = pathlib.Path(d)/'events.log'
        php = '''ini_set('error_log',$argv[2]); $_SERVER['HTTP_X_REQUEST_ID']=$argv[3];
require $argv[1].'/shared/code/tce_functions_request_log.php';
require $argv[1].'/shared/code/tce_functions_auth_log.php';
openvsosh_log_auth_event('test');
openvsosh_log_answer_event('write',['status'=>'saved','testlogid'=>9,'operation_id'=>str_repeat('c',32),'expected_version'=>1,'result_version'=>2,'answer_text'=>'secret-answer','csrf_token'=>'secret-token']);
echo json_encode([openvsosh_request_id(),openvsosh_request_id()]);'''
        result = subprocess.run(['php','-r',php,str(ROOT),str(log),incoming],capture_output=True,text=True,check=True)
        ids = json.loads(result.stdout)
        assert ids[0] == ids[1] and len(ids[0])==32
        if incoming=='a'*32: assert ids[0]==incoming
        else: assert ids[0]!=incoming
        raw = log.read_text()
        assert 'secret-' not in raw and 'forged' not in raw
        rows=[json.loads(line[line.index('{'):]) for line in raw.splitlines()]
        assert len(rows)==2 and all(row['request_id']==ids[0] for row in rows)
        assert rows[1]['operation_id']=='c'*32
print('Request correlation: 4 scenarios passed')

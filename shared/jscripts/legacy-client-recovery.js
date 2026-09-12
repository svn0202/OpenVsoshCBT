(function () {
    'use strict';
    function recover() {
        if (document.getElementById('legacy-client-recovery')) { return; }
        var box = document.createElement('section');
        box.id = 'legacy-client-recovery';
        box.setAttribute('role', 'alert');
        box.style.cssText = 'position:relative;z-index:2147483647;padding:20px;background:#fff4d6;color:#222;border:3px solid #986700;font:18px/1.5 system-ui,sans-serif';
        var text = document.createElement('p');
        text.textContent = 'Открыта устаревшая версия страницы. Перед переходом скопируйте введённый ответ. Переход не сохраняет ответ и не завершает тест.';
        box.appendChild(text);
        var copy = document.createElement('button');
        copy.type = 'button';
        copy.textContent = 'Показать введённый ответ для копирования';
        copy.onclick = function () {
            var draft = [];
            document.querySelectorAll('[name="answertext"], [name="testcomment"], [name="answpos"], [name^="answpos["]').forEach(function (control) {
                if ((control.type === 'checkbox' || control.type === 'radio') && !control.checked) { return; }
                draft.push([control.name, control.value]);
            });
            var output = box.querySelector('textarea');
            if (!output) { output = document.createElement('textarea'); output.readOnly = true; output.rows = 8; output.style.width = '100%'; box.appendChild(output); }
            output.value = JSON.stringify(draft, null, 2);
            output.focus(); output.select();
        };
        box.appendChild(copy);
        var link = document.createElement('a');
        var test = document.querySelector('[name="testid"]');
        var id = test ? test.value : new URL(location.href).searchParams.get('testid');
        var target = new URL('/public/code/tce_login.php', location.origin);
        if (id && /^[1-9][0-9]*$/.test(id)) {
            target.pathname = '/public/code/tce_test_execute.php';
            target.searchParams.set('testid', id);
        }
        target.searchParams.set('fresh', String(Date.now()));
        link.href = target.href;
        link.textContent = ' Открыть актуальную страницу';
        box.appendChild(link);
        document.body.insertBefore(box, document.body.firstChild);
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', recover); }
    else { recover(); }
}());

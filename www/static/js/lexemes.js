function toggle() {
    var f = document.getElementById('form_query');
    var b = document.getElementById('toggle_form_query');
    if (f.style.display !== 'none') {
        f.style.display = 'none';
        b.value = 'show';
    }
    else {
        f.style.display = 'block';
        b.value = 'hide';
    }
}

document.getElementById('query').innerHTML += ' <input type="button" id="toggle_form_query" value="hide" />';

if (document.getElementById('results') !== null) {
    toggle();
}

document.getElementById('toggle_form_query').onclick = toggle;

function generate(id, property = 'P734') {
    var r = '';
    var results = document.getElementById('results').childNodes[0];
    for (i = 1; i < results.childElementCount; i++) {
        var row = results.childNodes[i];
        if (row.childNodes[0].childNodes[0].checked) {
            r += row.childNodes[1].childNodes[0].innerText + '\t' + property + '\t' + id + '\n';
        }
    }
    document.getElementById('generated').innerHTML = '<p><textarea rows="10">' + r + '</textarea></p>';
}
function sutton_replace() {
    Array.from(document.getElementsByClassName('sutton')).forEach((element) => {
        value = ssw.ttf.fsw.signSvg(element.innerHTML).replaceAll('black', '#369');
        if (value != '<svg version="1.1" xmlns="http://www.w3.org/2000/svg" width="1" height="1"></svg>') {
            element.innerHTML = value;
        }
    });
}

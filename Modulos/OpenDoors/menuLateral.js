window.addEventListener('message', function (evento) {
    var marco = document.querySelector('iframe.embebida');
    if (evento.origin !== window.location.origin || evento.source !== marco?.contentWindow
        || evento.data?.tipo !== 'locales-opendoors-actualizados' || !Array.isArray(evento.data.locales)) return;

    var lista = document.querySelector('[data-lista-id="opendoors-lugares"]');
    if (!lista) return;
    var locales = evento.data.locales;
    if (!locales.every(local => Number.isSafeInteger(local?.id) && local.id > 0 && typeof local.nombre === 'string')) return;

    var filas = locales.map(function (local) {
        var fila = document.createElement('li');
        var enlace = document.createElement('a');
        var etiqueta = document.createElement('span');
        enlace.href = '?seccion=' + lista.dataset.listaModulo + '/local-' + local.id;
        enlace.style.setProperty('--d', '2');
        etiqueta.className = 'label';
        etiqueta.textContent = local.nombre;
        enlace.append(etiqueta);
        fila.append(enlace);
        return fila;
    });
    if (!filas.length) {
        var vacio = document.createElement('li');
        vacio.className = 'sub-vacio';
        vacio.textContent = 'Sin lugares';
        filas.push(vacio);
    }
    lista.replaceChildren(...filas);
});

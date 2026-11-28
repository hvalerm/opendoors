<script>
    (function () {
        var form = document.getElementById('formAbrir');
        var btn = document.getElementById('btnAbrir');
        var aviso = document.getElementById('estadoError');
        var textoAviso = aviso.querySelector('[data-mensaje-estado]') || aviso;
        var timer = null;
        var controller = null;
        var enviando = false;
        var ventana = window;
        try {
            if (window.top.document) ventana = window.top;
        } catch (error) {
            if (error.name !== 'SecurityError') throw error;
        }

        function visible() {
            return !document.hidden && ventana.document.hasFocus();
        }

        function mostrar(valido, mensaje, comprobando) {
            btn.disabled = !valido;
            textoAviso.textContent = mensaje;
            aviso.hidden = !mensaje;
            if (aviso.hasAttribute('data-color-estado')) {
                aviso.classList.toggle('error', !valido);
                aviso.classList.toggle('comprobando', !!comprobando);
            }
        }

        function detener() {
            clearInterval(timer);
            timer = null;
            if (controller) controller.abort();
            controller = null;
            btn.disabled = true;
        }

        async function consultar() {
            if (!visible() || controller || enviando) return;
            var actual = new AbortController();
            controller = actual;
            var plazo = setTimeout(function () { actual.abort(); }, 5000);
            try {
                var datos = new URLSearchParams({ accion: form.dataset.accionEstado });
                var token = form.querySelector('[name="token"]');
                if (token) datos.set('token', token.value);
                var empresa = form.querySelector('[name="empresa"]');
                if (empresa) datos.set('empresa', empresa.value);
                var res = await fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: datos,
                    cache: 'no-store',
                    signal: actual.signal
                });
                if (!res.ok) throw new Error('No se pudo consultar el estado.');
                var estado = await res.json();
                if (!actual.signal.aborted && visible() && !enviando) {
                    mostrar(estado.valido === true, estado.mensaje || '');
                }
            } catch (error) {
                if (visible() && !enviando && controller === actual) {
                    mostrar(false, 'No se pudo verificar la disponibilidad. Revisa tu conexión e inténtalo nuevamente.');
                }
            } finally {
                clearTimeout(plazo);
                if (controller === actual) controller = null;
            }
        }

        function iniciar() {
            if (!visible() || timer !== null || enviando) return;
            mostrar(false, 'Comprobando disponibilidad...', true);
            consultar();
            timer = setInterval(consultar, 1500);
        }

        document.addEventListener('visibilitychange', function () {
            if (!visible()) detener();
            else iniciar();
        });
        ventana.addEventListener('blur', function () {
            setTimeout(function () { if (!visible()) detener(); }, 0);
        });
        ventana.addEventListener('focus', iniciar);
        window.addEventListener('pagehide', detener);
        window.addEventListener('pageshow', function () {
            enviando = false;
            iniciar();
        });
        form.addEventListener('submit', function () {
            enviando = true;
            detener();
            btn.textContent = 'Abriendo puerta...';
        });
        iniciar();
    })();
</script>

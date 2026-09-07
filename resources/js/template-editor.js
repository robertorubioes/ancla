/**
 * Editor visual de plantillas.
 *
 * Reparto de responsabilidades:
 *
 *   servidor  mide las paginas en milimetros y dibuja las cajas ya colocadas,
 *             en porcentaje sobre la pagina
 *   este JS   pinta el PDF sobre cada canvas y traduce el arrastre a
 *             milimetros para devolverselos a Livewire
 *
 * Que las cajas vayan en porcentaje desde el servidor es lo que hace que
 * sobrevivan a cada re-render de Livewire y a cualquier escala de pantalla,
 * sin que este fichero tenga que recolocarlas.
 *
 * PDF.js se carga bajo demanda: pesa mas de 200 KB comprimido y solo hace
 * falta en esta pantalla.
 */
let pdfjsPromise = null;

function loadPdfJs() {
    pdfjsPromise ??= (async () => {
        // Se importa como ?worker y no como ?url a proposito. Con ?url, Vite
        // emite el fichero conservando la extension .mjs, y un servidor que
        // no la tenga en sus mime.types la sirve como
        // application/octet-stream; el navegador entonces se niega a cargarla
        // como modulo y PDF.js falla con "Setting up fake worker failed".
        // Eso pasaria igual en produccion, asi que se arregla aqui y no en la
        // configuracion de cada servidor: con ?worker, Vite lo empaqueta y lo
        // sirve como .js normal.
        const [lib, worker] = await Promise.all([
            import('pdfjs-dist'),
            import('pdfjs-dist/build/pdf.worker.min.mjs?worker'),
        ]);

        lib.GlobalWorkerOptions.workerPort = new worker.default();

        return lib;
    })();

    return pdfjsPromise;
}

export default function templateEditor({ pdfUrl }) {
    /*
     * Estado que NO puede vivir en el objeto de Alpine.
     *
     * Alpine envuelve sus datos en un Proxy, y el documento de PDF.js usa
     * campos privados de clase: leerlos a traves de un Proxy lanza
     * "Cannot read private member #s from an object whose class did not
     * declare it". El observador y el conjunto de paginas pintadas van aqui
     * por lo mismo.
     */
    let doc = null;
    let observer = null;
    const painted = new Set();

    return {
        pdfUrl,

        loading: true,
        loadError: '',

        /** Numero de paginas, para la interfaz */
        pageCount: 0,

        /** Pagina que se esta mirando ahora mismo */
        currentPage: 1,

        /** Arrastre en curso */
        drag: null,

        async init() {
            try {
                const pdfjsLib = await loadPdfJs();
                doc = await pdfjsLib.getDocument({ url: this.pdfUrl }).promise;
                this.pageCount = doc.numPages;

                this.watchPages();

                // Al anadir o mover un campo, Livewire rehace el HTML de las
                // cajas. Los canvas sobreviven -van en wire:ignore- pero hay
                // que volver a observar por si algun contenedor es nuevo.
                window.Livewire?.hook('morph.updated', ({ el }) => {
                    if (this.$root?.contains(el)) {
                        this.watchPages();
                    }
                });
            } catch (error) {
                this.loadError = this.describe(error);
                console.error('[template-editor]', error);
            } finally {
                this.loading = false;
            }
        },

        describe(error) {
            const detalle = error?.message ? ` (${error.message})` : '';

            return `No se pudo cargar el PDF de la plantilla${detalle}.`;
        },

        /**
         * Pinta cada pagina cuando esta a punto de verse, y no antes.
         *
         * Un contrato de cincuenta paginas tardaba una eternidad en abrirse
         * porque se pintaban todas de golpe. Con esto solo se pinta lo que se
         * mira, y el resto queda en blanco hasta que hace falta.
         */
        watchPages() {
            observer?.disconnect();

            observer = new IntersectionObserver(
                (entries) => {
                    for (const entry of entries) {
                        if (entry.isIntersecting) {
                            this.paintPage(entry.target);
                        }
                    }
                },
                // Se observa dentro del panel con scroll, con margen para
                // que la pagina llegue ya pintada.
                { root: this.$refs.scroller ?? null, rootMargin: '600px 0px' },
            );

            for (const holder of document.querySelectorAll('.tpl-page')) {
                observer.observe(holder);

                // Un canvas que Livewire haya recreado se queda vacio aunque
                // la pagina figure como pintada: se repinta.
                const canvas = holder.querySelector('canvas');
                if (canvas && canvas.width === 0) {
                    painted.delete(Number(holder.dataset.page));
                }
            }
        },

        async paintPage(holder) {
            const number = Number(holder.dataset.page);
            const canvas = holder.querySelector('canvas');

            if (!canvas || !doc || painted.has(number)) {
                return;
            }

            // Se marca antes de empezar: pintar es asincrono y el observador
            // puede volver a disparar mientras tanto.
            painted.add(number);

            try {
                const page = await doc.getPage(number);
                const base = page.getViewport({ scale: 1 });

                // Al ancho real del hueco y con el ratio del dispositivo, para
                // que no salga borroso en pantallas HiDPI.
                const cssWidth = canvas.clientWidth || 760;
                const ratio = Math.min(window.devicePixelRatio || 1, 2);
                const viewport = page.getViewport({ scale: (cssWidth * ratio) / base.width });

                canvas.width = Math.round(viewport.width);
                canvas.height = Math.round(viewport.height);

                await page.render({
                    canvasContext: canvas.getContext('2d'),
                    viewport,
                }).promise;
            } catch (error) {
                painted.delete(number);
                console.error('[template-editor] pagina', number, error);
            }
        },

        /**
         * Anade un campo en la pagina que se este mirando.
         *
         * Existe porque nadie adivina que hay que hacer doble clic sobre el
         * documento. El doble clic se mantiene como atajo para colocarlo en
         * un punto concreto.
         */
        addFieldHere() {
            const holder = this.visiblePage();

            if (!holder) {
                return;
            }

            const mmWidth = Number(holder.dataset.mmWidth);
            const mmHeight = Number(holder.dataset.mmHeight);

            // Centrado a lo ancho y un poco por encima del medio: se ve
            // siempre, y desde ahi se arrastra a donde toque.
            this.$wire.addField(
                Number(holder.dataset.page),
                this.round(mmWidth / 2 - 30),
                this.round(mmHeight / 3),
            );
        },

        /** La pagina que ocupa mas superficie visible del panel */
        visiblePage() {
            const scroller = this.$refs.scroller;

            if (!scroller) {
                return document.querySelector('.tpl-page');
            }

            const view = scroller.getBoundingClientRect();
            let mejor = null;
            let mayor = 0;

            for (const holder of scroller.querySelectorAll('.tpl-page')) {
                const r = holder.getBoundingClientRect();
                const visible = Math.min(r.bottom, view.bottom) - Math.max(r.top, view.top);

                if (visible > mayor) {
                    mayor = visible;
                    mejor = holder;
                }
            }

            return mejor ?? document.querySelector('.tpl-page');
        },

        /** Mantiene al dia el indicador de pagina */
        trackCurrentPage() {
            const holder = this.visiblePage();

            if (holder) {
                this.currentPage = Number(holder.dataset.page);
            }
        },

        /** El contenedor de la pagina, con sus dimensiones en mm */
        pageOf(element) {
            const holder = element.closest('.tpl-page');

            if (!holder) {
                return null;
            }

            return {
                holder,
                number: Number(holder.dataset.page),
                mmWidth: Number(holder.dataset.mmWidth),
                mmHeight: Number(holder.dataset.mmHeight),
                rect: holder.getBoundingClientRect(),
            };
        },

        /** Anade un campo donde se ha hecho doble clic */
        addFieldAt(event, pageNumber) {
            const page = this.pageOf(event.currentTarget);

            if (!page) {
                return;
            }

            const x = ((event.clientX - page.rect.left) / page.rect.width) * page.mmWidth;
            const y = ((event.clientY - page.rect.top) / page.rect.height) * page.mmHeight;

            this.$wire.addField(pageNumber, this.round(x), this.round(y));
        },

        startDrag(event, index, mode = 'move') {
            const box = event.currentTarget.closest('[data-field-index]');
            const page = this.pageOf(box);

            if (!page) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            this.drag = {
                index,
                mode,
                page,
                box,
                startX: event.clientX,
                startY: event.clientY,
                originLeft: box.offsetLeft,
                originTop: box.offsetTop,
                originWidth: box.offsetWidth,
                originHeight: box.offsetHeight,
            };

            this.$wire.selectField(index);

            const onMove = (e) => this.onDrag(e);
            const onUp = (e) => {
                this.endDrag(e);
                window.removeEventListener('pointermove', onMove);
                window.removeEventListener('pointerup', onUp);
            };

            window.addEventListener('pointermove', onMove);
            window.addEventListener('pointerup', onUp);
        },

        /**
         * Mientras dura el arrastre solo se mueve la caja: no tiene sentido ir
         * al servidor en cada pixel.
         */
        onDrag(event) {
            if (!this.drag) {
                return;
            }

            const { box, mode, startX, startY } = this.drag;
            const dx = event.clientX - startX;
            const dy = event.clientY - startY;

            if (mode === 'move') {
                box.style.left = `${Math.max(0, this.drag.originLeft + dx)}px`;
                box.style.top = `${Math.max(0, this.drag.originTop + dy)}px`;

                return;
            }

            box.style.width = `${Math.max(12, this.drag.originWidth + dx)}px`;
            box.style.height = `${Math.max(10, this.drag.originHeight + dy)}px`;
        },

        endDrag() {
            if (!this.drag) {
                return;
            }

            const { index, mode, page, box } = this.drag;
            this.drag = null;

            const toMmX = (px) => (px / page.rect.width) * page.mmWidth;
            const toMmY = (px) => (px / page.rect.height) * page.mmHeight;

            if (mode === 'move') {
                this.$wire.moveField(
                    index,
                    this.round(toMmX(box.offsetLeft)),
                    this.round(toMmY(box.offsetTop)),
                );

                return;
            }

            this.$wire.moveField(
                index,
                this.round(toMmX(box.offsetLeft)),
                this.round(toMmY(box.offsetTop)),
                this.round(toMmX(box.offsetWidth)),
                this.round(toMmY(box.offsetHeight)),
            );
        },

        round(value) {
            return Math.round(value * 100) / 100;
        },
    };
}

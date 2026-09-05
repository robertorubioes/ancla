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
    return {
        pdfUrl,

        loading: true,
        loadError: '',

        /** Arrastre en curso */
        drag: null,

        async init() {
            try {
                await this.paintPages();
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
         * Pinta cada pagina sobre el canvas que el servidor ya dejo puesto.
         */
        async paintPages() {
            const pdfjsLib = await loadPdfJs();
            const doc = await pdfjsLib.getDocument({ url: this.pdfUrl }).promise;

            for (let number = 1; number <= doc.numPages; number++) {
                const canvas = document.getElementById(`tpl-canvas-${number}`);

                // El servidor pudo no medir alguna pagina; se ignora en vez
                // de abortar el resto.
                if (!canvas) {
                    continue;
                }

                const page = await doc.getPage(number);
                const base = page.getViewport({ scale: 1 });

                // Se pinta al ancho real del hueco, con el limite del
                // dispositivo, para que no salga borroso en pantallas HiDPI.
                const cssWidth = canvas.clientWidth || 760;
                const ratio = Math.min(window.devicePixelRatio || 1, 2);
                const viewport = page.getViewport({ scale: (cssWidth * ratio) / base.width });

                canvas.width = Math.round(viewport.width);
                canvas.height = Math.round(viewport.height);

                await page.render({
                    canvasContext: canvas.getContext('2d'),
                    viewport,
                }).promise;
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

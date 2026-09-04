/**
 * Editor visual de plantillas.
 *
 * Pinta las paginas del PDF con PDF.js y superpone las cajas de campo. Todo
 * lo que sale de aqui hacia Livewire va en MILIMETROS desde arriba a la
 * izquierda, la misma convencion que usa el servidor al estampar y que ya
 * usaba config/signing.php para la firma.
 *
 * El navegador solo mueve cajas: la validacion y la persistencia son del
 * servidor, que no se fia de estas coordenadas.
 *
 * PDF.js se carga bajo demanda: pesa mas de 200 KB comprimido y solo hace
 * falta en esta pantalla, asi que no debe viajar en el bundle de todas las
 * paginas.
 */
let pdfjsPromise = null;

function loadPdfJs() {
    pdfjsPromise ??= (async () => {
        const [lib, worker] = await Promise.all([
            import('pdfjs-dist'),
            import('pdfjs-dist/build/pdf.worker.min.mjs?url'),
        ]);

        lib.GlobalWorkerOptions.workerSrc = worker.default;

        return lib;
    })();

    return pdfjsPromise;
}

export default function templateEditor({ pdfUrl }) {
    return {
        pdfUrl,

        /** Paginas renderizadas: { number, widthMm, heightMm, scale } */
        pages: [],

        loading: true,
        loadError: '',

        /** Arrastre en curso */
        drag: null,

        /** Milimetros por pulgada, para pasar de puntos PDF a mm */
        MM_PER_POINT: 25.4 / 72,

        async init() {
            try {
                await this.renderPdf();
            } catch (error) {
                this.loadError = 'No se pudo cargar el PDF de la plantilla.';
                console.error('[template-editor]', error);
            } finally {
                this.loading = false;
            }
        },

        async renderPdf() {
            const pdfjsLib = await loadPdfJs();
            const doc = await pdfjsLib.getDocument({ url: this.pdfUrl }).promise;
            const pages = [];

            for (let number = 1; number <= doc.numPages; number++) {
                const page = await doc.getPage(number);

                // viewport a escala 1 = puntos PDF, que es lo que hay que
                // convertir a milimetros para el servidor
                const base = page.getViewport({ scale: 1 });
                const widthMm = base.width * this.MM_PER_POINT;
                const heightMm = base.height * this.MM_PER_POINT;

                // Se pinta a un ancho fijo para que la pagina quepa en pantalla
                const targetWidth = 760;
                const scale = targetWidth / base.width;
                const viewport = page.getViewport({ scale });

                const canvas = this.$refs[`canvas${number}`] ?? this.makeCanvas(number);
                canvas.width = viewport.width;
                canvas.height = viewport.height;
                canvas.style.width = `${viewport.width}px`;
                canvas.style.height = `${viewport.height}px`;

                await page.render({
                    canvasContext: canvas.getContext('2d'),
                    viewport,
                }).promise;

                pages.push({
                    number,
                    widthMm,
                    heightMm,
                    pixelWidth: viewport.width,
                    pixelHeight: viewport.height,
                });
            }

            this.pages = pages;
        },

        makeCanvas(number) {
            const holder = document.getElementById(`page-holder-${number}`);
            if (holder) {
                return holder.querySelector('canvas');
            }

            // Las paginas se crean sobre la marcha la primera vez
            const wrapper = document.createElement('div');
            wrapper.id = `page-holder-${number}`;
            wrapper.className = 'relative mx-auto mb-8 shadow-sm ring-1 ring-gray-200';
            wrapper.dataset.page = String(number);

            const canvas = document.createElement('canvas');
            wrapper.appendChild(canvas);

            this.$refs.pageContainer.appendChild(wrapper);

            return canvas;
        },

        /** Pixeles por milimetro en la pagina indicada */
        pxPerMm(pageNumber) {
            const page = this.pages.find((p) => p.number === pageNumber);

            return page ? page.pixelWidth / page.widthMm : 1;
        },

        /** Estilo de una caja de campo, en pixeles de pantalla */
        boxStyle(field) {
            const ratio = this.pxPerMm(field.page);

            return {
                left: `${field.x * ratio}px`,
                top: `${field.y * ratio}px`,
                width: `${field.width * ratio}px`,
                height: `${field.height * ratio}px`,
            };
        },

        /** Anade un campo donde se ha hecho doble clic */
        addFieldAt(event, pageNumber) {
            const rect = event.currentTarget.getBoundingClientRect();
            const ratio = this.pxPerMm(pageNumber);

            const x = (event.clientX - rect.left) / ratio;
            const y = (event.clientY - rect.top) / ratio;

            this.$wire.addField(pageNumber, this.round(x), this.round(y));
        },

        startDrag(event, index, field, mode = 'move') {
            event.preventDefault();
            event.stopPropagation();

            this.drag = {
                index,
                mode,
                page: field.page,
                startX: event.clientX,
                startY: event.clientY,
                originX: field.x,
                originY: field.y,
                originWidth: field.width,
                originHeight: field.height,
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

        onDrag(event) {
            if (!this.drag) {
                return;
            }

            const ratio = this.pxPerMm(this.drag.page);
            const dx = (event.clientX - this.drag.startX) / ratio;
            const dy = (event.clientY - this.drag.startY) / ratio;

            const box = document.querySelector(`[data-field-index="${this.drag.index}"]`);
            if (!box) {
                return;
            }

            // Se mueve solo la caja mientras dura el arrastre: no tiene
            // sentido ir al servidor en cada pixel.
            if (this.drag.mode === 'move') {
                box.style.left = `${Math.max(0, this.drag.originX + dx) * ratio}px`;
                box.style.top = `${Math.max(0, this.drag.originY + dy) * ratio}px`;

                return;
            }

            box.style.width = `${Math.max(5, this.drag.originWidth + dx) * ratio}px`;
            box.style.height = `${Math.max(4, this.drag.originHeight + dy) * ratio}px`;
        },

        endDrag(event) {
            if (!this.drag) {
                return;
            }

            const ratio = this.pxPerMm(this.drag.page);
            const dx = (event.clientX - this.drag.startX) / ratio;
            const dy = (event.clientY - this.drag.startY) / ratio;

            const drag = this.drag;
            this.drag = null;

            if (drag.mode === 'move') {
                this.$wire.moveField(
                    drag.index,
                    this.round(Math.max(0, drag.originX + dx)),
                    this.round(Math.max(0, drag.originY + dy)),
                );

                return;
            }

            this.$wire.moveField(
                drag.index,
                drag.originX,
                drag.originY,
                this.round(Math.max(5, drag.originWidth + dx)),
                this.round(Math.max(4, drag.originHeight + dy)),
            );
        },

        round(value) {
            return Math.round(value * 100) / 100;
        },
    };
}

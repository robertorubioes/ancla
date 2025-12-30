/**
 * Signature Canvas Component for Alpine.js
 * 
 * Handles drawing signatures on HTML5 canvas with mouse and touch support.
 */
export default () => ({
    canvas: null,
    ctx: null,
    isDrawing: false,
    isEmpty: true,
    lastX: 0,
    lastY: 0,

    /**
     * Initialize the canvas component
     */
    init() {
        this.canvas = this.$refs.canvas;
        if (!this.canvas) {
            console.error('Canvas element not found');
            return;
        }

        this.ctx = this.canvas.getContext('2d');
        this.setupCanvas();
        this.addEventListeners();
    },

    /**
     * Setup canvas properties
     */
    setupCanvas() {
        // Set canvas size to match display size (responsive)
        const rect = this.canvas.getBoundingClientRect();
        this.canvas.width = rect.width;
        this.canvas.height = 200;

        // Configure drawing style
        this.ctx.lineWidth = 2;
        this.ctx.lineCap = 'round';
        this.ctx.lineJoin = 'round';
        this.ctx.strokeStyle = '#000000';

        // Fill with white background
        this.ctx.fillStyle = '#ffffff';
        this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
    },

    /**
     * Start drawing
     */
    startDrawing(e) {
        e.preventDefault();
        this.isDrawing = true;
        this.isEmpty = false;

        const pos = this.getPosition(e);
        this.lastX = pos.x;
        this.lastY = pos.y;

        this.ctx.beginPath();
        this.ctx.moveTo(pos.x, pos.y);
    },

    /**
     * Draw on canvas
     */
    draw(e) {
        if (!this.isDrawing) return;
        e.preventDefault();

        const pos = this.getPosition(e);

        this.ctx.lineTo(pos.x, pos.y);
        this.ctx.stroke();

        this.lastX = pos.x;
        this.lastY = pos.y;
    },

    /**
     * Stop drawing
     */
    stopDrawing(e) {
        if (this.isDrawing) {
            e.preventDefault();
            this.isDrawing = false;
            this.ctx.closePath();
        }
    },

    /**
     * Clear the canvas
     */
    clear() {
        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        this.ctx.fillStyle = '#ffffff';
        this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
        this.isEmpty = true;
        this.isDrawing = false;
    },

    /**
     * Get canvas data as PNG data URL
     */
    getDataURL() {
        return this.canvas.toDataURL('image/png');
    },

    /**
     * Check if canvas is empty
     */
    checkIsEmpty() {
        return this.isEmpty;
    },

    /**
     * Get mouse/touch position relative to canvas
     */
    getPosition(e) {
        const rect = this.canvas.getBoundingClientRect();
        let clientX, clientY;

        if (e.touches && e.touches.length > 0) {
            // Touch event
            clientX = e.touches[0].clientX;
            clientY = e.touches[0].clientY;
        } else {
            // Mouse event
            clientX = e.clientX;
            clientY = e.clientY;
        }

        return {
            x: clientX - rect.left,
            y: clientY - rect.top
        };
    },

    /**
     * Add event listeners for mouse and touch
     */
    addEventListeners() {
        // Mouse events
        this.canvas.addEventListener('mousedown', (e) => this.startDrawing(e));
        this.canvas.addEventListener('mousemove', (e) => this.draw(e));
        this.canvas.addEventListener('mouseup', (e) => this.stopDrawing(e));
        this.canvas.addEventListener('mouseleave', (e) => this.stopDrawing(e));

        // Touch events (mobile)
        this.canvas.addEventListener('touchstart', (e) => this.startDrawing(e), { passive: false });
        this.canvas.addEventListener('touchmove', (e) => this.draw(e), { passive: false });
        this.canvas.addEventListener('touchend', (e) => this.stopDrawing(e), { passive: false });
        this.canvas.addEventListener('touchcancel', (e) => this.stopDrawing(e), { passive: false });

        // Prevent scrolling when touching canvas on mobile
        this.canvas.addEventListener('touchstart', (e) => {
            if (e.target === this.canvas) {
                e.preventDefault();
            }
        }, { passive: false });
    },

    /**
     * Resize canvas (call this if container size changes)
     */
    resize() {
        // Save current content
        const imageData = this.ctx.getImageData(0, 0, this.canvas.width, this.canvas.height);
        
        // Resize canvas
        this.setupCanvas();
        
        // Restore content
        if (!this.isEmpty) {
            this.ctx.putImageData(imageData, 0, 0);
        }
    }
});

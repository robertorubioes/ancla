import './bootstrap';
import Alpine from 'alpinejs';
import signatureCanvas from './signature-canvas';

// Initialize Alpine.js
window.Alpine = Alpine;
Alpine.start();

// Make signature canvas available globally for Alpine.js
window.signatureCanvas = signatureCanvas;

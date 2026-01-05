import './bootstrap';
import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import signatureCanvas from './signature-canvas';

// Make signature canvas available globally for Alpine.js
window.signatureCanvas = signatureCanvas;

// Start Livewire with Alpine
Livewire.start();

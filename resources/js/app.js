import './bootstrap';
import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import signatureCanvas from './signature-canvas';
import templateEditor from './template-editor';

// Make signature canvas available globally for Alpine.js
window.signatureCanvas = signatureCanvas;
window.templateEditor = templateEditor;

// Start Livewire with Alpine
Livewire.start();

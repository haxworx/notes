import { Controller } from '@hotwired/stimulus';
import { Spinner } from 'bootstrap';
export default class extends Controller {
    targets = [
        'spinner',
    ];
    connect() {
        this.element.textContent = 'Hello Stimulus! Edit me in assets/controllers/hello_controller.js';
    }
}

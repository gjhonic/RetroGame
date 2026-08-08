import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['overlay', 'image'];

    static values = {
        urls: Array,
    };

    connect() {
        this.currentIndex = 0;
        this.onKeydown = this.onKeydown.bind(this);
    }

    open(event) {
        event.preventDefault();

        const index = Number(event.currentTarget.dataset.lightboxIndex);
        this.currentIndex = Number.isNaN(index) ? 0 : index;
        this.render();

        this.overlayTarget.classList.add('lightbox--open');
        document.addEventListener('keydown', this.onKeydown);
    }

    close() {
        this.overlayTarget.classList.remove('lightbox--open');
        document.removeEventListener('keydown', this.onKeydown);
    }

    closeOnOverlayClick(event) {
        if (event.target === this.overlayTarget) {
            this.close();
        }
    }

    next() {
        this.currentIndex = (this.currentIndex + 1) % this.urlsValue.length;
        this.render();
    }

    prev() {
        this.currentIndex = (this.currentIndex - 1 + this.urlsValue.length) % this.urlsValue.length;
        this.render();
    }

    onKeydown(event) {
        if (event.key === 'Escape') {
            this.close();
        } else if (event.key === 'ArrowRight') {
            this.next();
        } else if (event.key === 'ArrowLeft') {
            this.prev();
        }
    }

    render() {
        this.imageTarget.src = this.urlsValue[this.currentIndex];
    }
}

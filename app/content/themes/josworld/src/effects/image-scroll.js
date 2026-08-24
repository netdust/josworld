// src/modules/image-scroll-effects.js

/**
 * Image Scroll Effects v3.1 – Performance optimized
 * ESM version for Vite
 */
class ImageScrollEffects {
    constructor(options = {}) {
        this.config = {
            selector: options.selector || '.image-scroll-effect',
            stickySelector: options.stickySelector || '.image-scroll-sticky',
            horizontalSelector: options.horizontalSelector || '.horizontal-scroll-section',
            lenis: options.lenis || null,
        };

        this.elements = [];
        this.stickyElements = [];
        this.horizontalElements = [];
        this.scrollHandler = null;

        // Cache viewport height (update on resize)
        this.vh = window.innerHeight;
        this.resizeHandler = null;

        this.init();
    }

    init() {
        this.findElements();
        this.setupScrollListener();
        this.setupResizeListener();
        this.animateImages();
    }

    findElements() {
        // Simple effects - cache img reference
        const elements = document.querySelectorAll(this.config.selector);
        this.elements = Array.from(elements).map(el => {
            el.classList.remove('active');
            el.classList.add('js-ready'); // Mark as initialized (unhides via CSS)
            return {
                element: el,
                img: el.querySelector('img'),  // Cache img reference
                effect: el.dataset.effect || 'fade',
                parallaxSpeed: parseFloat(el.dataset.parallaxSpeed || '0.5'),
            };
        });

        // Sticky effects - already caching img
        const sticky = document.querySelectorAll(this.config.stickySelector);
        this.stickyElements = Array.from(sticky).map(el => {
            el.classList.add('js-ready');
            return {
                element: el,
                content: el.querySelector('.image-scroll-sticky-content'),
                img: el.querySelector('img'),
                effect: el.dataset.effect || 'sticky-scale',
            };
        });

        // Horizontal scroll
        const horiz = document.querySelectorAll(this.config.horizontalSelector);
        this.horizontalElements = Array.from(horiz).map(el => {
            el.classList.add('js-ready');
            return {
                element: el,
                track: el.querySelector('.horizontal-track'),
            };
        });
    }

    setupScrollListener() {
        if (this.scrollHandler) return;

        this.scrollHandler = () => this.animateImages();

        if (this.config.lenis) {
            this.config.lenis.on('scroll', this.scrollHandler);
        } else {
            window.addEventListener('scroll', this.scrollHandler, { passive: true });
        }
    }

    setupResizeListener() {
        if (this.resizeHandler) return;

        this.resizeHandler = () => {
            this.vh = window.innerHeight;
        };
        window.addEventListener('resize', this.resizeHandler, { passive: true });
    }

    removeScrollListener() {
        if (!this.scrollHandler) return;

        if (this.config.lenis) {
            this.config.lenis.off('scroll', this.scrollHandler);
        } else {
            window.removeEventListener('scroll', this.scrollHandler);
        }
        this.scrollHandler = null;
    }

    removeResizeListener() {
        if (this.resizeHandler) {
            window.removeEventListener('resize', this.resizeHandler);
            this.resizeHandler = null;
        }
    }

    animateImages() {
        this.animateSimpleEffects();
        this.animateStickyEffects();
        this.animateHorizontalScroll();
    }

    animateSimpleEffects() {
        const vh = this.vh;
        const centerY = vh / 2;

        this.elements.forEach(item => {
            const { element, img, effect, parallaxSpeed } = item;
            const rect = element.getBoundingClientRect();

            const elCenterY = rect.top + rect.height / 2;

            // Declared without an initialiser: every branch below assigns, so a
            // `= 0` here is a dead store (eslint no-useless-assignment).
            let progress;
            if (elCenterY <= centerY) progress = 1;
            else if (elCenterY >= vh) progress = 0;
            else progress = (vh - elCenterY) / (vh - centerY);

            // Skip on VALUE, not on position. The original guard bailed out when the
            // element was far off-screen, which meant anything you never scrolled
            // THROUGH kept its CSS start state — dimmed text, and for clip-* images
            // `inset(100%)`, i.e. invisible. Any jump lands you there: an anchor link,
            // a restored scroll position, or a programmatic scrollTo. Clamping instead
            // keeps the write-avoidance (off-screen items settle to a constant 0 or 1
            // and then stop writing) while guaranteeing the state is always correct.
            // Parallax is exempt: its transform depends on the rect, not on `progress`.
            if (item.lastProgress === progress && effect !== 'parallax') return;
            item.lastProgress = progress;

            this.applyEffect(element, img, effect, progress, parallaxSpeed, rect, vh);
        });
    }

    applyEffect(element, img, effect, progress, parallaxSpeed, rect, vh) {
        switch (effect) {
            case 'fade':
                element.style.opacity = progress;
                break;
            case 'scale-up':
                element.style.opacity = progress;
                element.style.transform = `scale(${0.8 + progress * 0.2})`;
                break;
            case 'scale-down':
                element.style.opacity = progress;
                element.style.transform = `scale(${1.2 - progress * 0.2})`;
                break;
            case 'slide-up':
                element.style.opacity = progress;
                element.style.transform = `translateY(${50 - progress * 50}px)`;
                break;
            case 'slide-left':
                element.style.opacity = progress;
                element.style.transform = `translateX(${50 - progress * 50}px)`;
                break;
            case 'slide-right':
                element.style.opacity = progress;
                element.style.transform = `translateX(${-50 + progress * 50}px)`;
                break;
            case 'blur':
                element.style.filter = `blur(${10 - progress * 10}px)`;
                element.style.opacity = 0.5 + progress * 0.5;
                break;
            case 'rotate':
                element.style.opacity = progress;
                element.style.transform = `rotate(${-10 + progress * 10}deg) scale(${0.9 + progress * 0.1})`;
                break;
            case 'parallax':
                if (img) {
                    const elCenterY = rect.top + rect.height / 2;
                    const parallaxProgress = (vh - elCenterY) / (vh + rect.height);
                    const movement = (parallaxProgress - 0.5) * 100 * parallaxSpeed;
                    img.style.transform = `translateY(${movement}px)`;
                }
                break;
            case 'clip-up':
                if (img) img.style.clipPath = `inset(${(1 - progress) * 100}% 0 0 0)`;
                break;
            case 'clip-right':
                if (img) img.style.clipPath = `inset(0 ${(1 - progress) * 100}% 0 0)`;
                break;
            case 'clip-left':
                if (img) img.style.clipPath = `inset(0 0 0 ${(1 - progress) * 100}%)`;
                break;
            case 'ken-burns':
                element.style.opacity = progress;
                if (img) img.style.transform = `scale(${1 + progress * 0.1})`;
                break;
        }
    }

    animateStickyEffects() {
        const vh = this.vh;

        this.stickyElements.forEach(item => {
            const { element, img, effect } = item;
            if (!img) return;

            const rect = element.getBoundingClientRect();

            const progress = Math.max(0, Math.min(1, (vh - rect.top) / (vh + rect.height)));

            if (item.lastProgress === progress) return;
            item.lastProgress = progress;

            switch (effect) {
                case 'sticky-slide-left':
                    img.style.transform = `translateX(${(1 - progress) * 100}%)`;
                    break;
                case 'sticky-slide-right':
                    img.style.transform = `translateX(${(progress - 1) * 100}%)`;
                    break;
                case 'sticky-scale':
                    img.style.transform = `scale(${0.5 + progress * 0.5})`;
                    break;
                case 'sticky-clip':
                    img.style.clipPath = `inset(0 ${(1 - progress) * 100}% 0 0)`;
                    break;
                case 'sticky-rotate':
                    img.style.transform = `rotate(${-45 + progress * 45}deg) scale(${0.7 + progress * 0.3})`;
                    break;
            }
        });
    }

    animateHorizontalScroll() {
        const vh = this.vh;

        this.horizontalElements.forEach(item => {
            const { element, track } = item;
            if (!track) return;

            const rect = element.getBoundingClientRect();

            const progress = Math.max(0, Math.min(1, (vh - rect.top) / (vh + rect.height)));

            if (item.lastProgress === progress) return;
            item.lastProgress = progress;

            const trackWidth = track.scrollWidth;
            const containerWidth = element.offsetWidth;
            const maxTranslate = trackWidth - containerWidth;
            const translateX = -(progress * maxTranslate);

            track.style.transform = `translateX(${translateX}px)`;
        });
    }

    refresh() {
        this.elements.forEach(item => item.element.classList.remove('active'));
        this.elements = [];
        this.stickyElements = [];
        this.horizontalElements = [];
        this.findElements();
        this.animateImages();
    }

    destroy() {
        this.removeScrollListener();
        this.removeResizeListener();
        this.elements = [];
        this.stickyElements = [];
        this.horizontalElements = [];
    }
}

// Export factory
export const imageScroll = {
    init: (options) => new ImageScrollEffects(options),
    version: '3.1.0',
};

// Optional: expose globally for legacy
if (typeof window !== 'undefined') {
    window.ntdst = window.ntdst || {};
    window.ntdst.imageScroll = imageScroll;
}

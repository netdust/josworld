// src/modules/text-scroll-effects.js

/**
 * Text Scroll Effects v2.1 – Performance optimized
 * Progressive word/letter animations on scroll
 * Completes at 25vh
 */
class TextScrollEffects {
    constructor(options = {}) {
        this.config = {
            selector: options.selector || '.text-scroll-effect',
            lenis: options.lenis || null,
        };

        this.elements = [];
        this.scrollHandler = null;
        this.resizeHandler = null;

        // Cache viewport height
        this.vh = window.innerHeight;

        this.init();
    }

    init() {
        this.findElements();
        this.splitText();
        this.setupScrollListener();
        this.setupResizeListener();
        this.animateText();
    }

    findElements() {
        const elements = document.querySelectorAll(this.config.selector);
        this.elements = Array.from(elements).map(el => {
            let scrollRef = el;

            // Custom scroll target
            if (el.dataset.scrollTarget) {
                const target = document.querySelector(el.dataset.scrollTarget);
                if (target) scrollRef = target;
            } else {
                // Find nearest section
                const section = el.closest('.uk-section, section, [data-scroll-section]');
                if (section) scrollRef = section;
            }

            return {
                element: el,
                scrollRef,
                effect: el.dataset.effect || 'word-fade',
                processed: false,
                children: [],  // Will be populated in splitText
            };
        });
    }

    splitText() {
        this.elements.forEach(item => {
            if (item.processed) return;

            const { element, effect } = item;
            const text = element.textContent.trim();

            element.innerHTML = '';

            if (effect === 'letter-reveal') {
                // Letter-by-letter
                text.split('').forEach(char => {
                    const span = document.createElement('span');
                    span.textContent = char === ' ' ? '\u00A0' : char;
                    span.className = 'letter';
                    element.appendChild(span);
                });
            } else {
                // Word-based effects
                const words = text.split(/\s+/);
                const classMap = {
                    'word-fade': 'word',
                    'blur': 'blur-word',
                    'color-wave': 'color-word',
                    'scale': 'scale-word',
                    'slide': 'slide-word',
                    'rotate': 'rotate-word',
                    'typewriter': 'type-word',
                    'highlight': 'highlight-word',
                    'flip': 'flip-word',
                };

                const wordClass = classMap[effect] || 'word';

                words.forEach((word, i) => {
                    const span = document.createElement('span');
                    span.textContent = word;
                    span.className = wordClass;
                    element.appendChild(span);

                    if (i < words.length - 1) {
                        element.appendChild(document.createTextNode(' '));
                    }
                });
            }

            // Cache children spans for performance
            item.children = Array.from(element.children).filter(el => el.tagName === 'SPAN');
            item.processed = true;

            // Mark element as ready (unhides it via CSS)
            element.classList.add('js-ready');
        });
    }

    setupScrollListener() {
        if (this.scrollHandler) return;

        this.scrollHandler = () => this.animateText();

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

    animateText() {
        const vh = this.vh;
        const targetY = vh * 0.25; // Complete at 25vh

        this.elements.forEach(item => {
            const { scrollRef, children } = item;
            if (!children.length) return;

            const rect = scrollRef.getBoundingClientRect();


            const elCenterY = rect.top + rect.height / 2;

            let progress = 0;
            if (elCenterY <= targetY) progress = 1;
            else if (elCenterY >= vh) progress = 0;
            else progress = (vh - elCenterY) / (vh - targetY);

            // Skip on VALUE, not on position. The original guard bailed out when the
            // element was far off-screen, which meant anything you never scrolled
            // THROUGH kept its CSS start state — dimmed text, and for clip-* images
            // `inset(100%)`, i.e. invisible. Any jump lands you there: an anchor link,
            // a restored scroll position, or a programmatic scrollTo. Clamping instead
            // keeps the write-avoidance (off-screen items settle to a constant 0 or 1
            // and then stop writing) while guaranteeing the state is always correct.
            if (item.lastProgress === progress) return;
            item.lastProgress = progress;

            const total = children.length;

            children.forEach((child, i) => {
                const threshold = i / total;
                child.classList.toggle('active', progress > threshold);
            });
        });
    }

    refresh() {
        this.elements.forEach(item => (item.processed = false));
        this.elements = [];
        this.findElements();
        this.splitText();
        this.animateText();
    }

    destroy() {
        this.removeScrollListener();
        this.removeResizeListener();
        this.elements = [];
    }
}

// Export factory
export const textScroll = {
    init: (options) => new TextScrollEffects(options),
    version: '2.1.0',
};

// Optional: expose globally for legacy
if (typeof window !== 'undefined') {
    window.ntdst = window.ntdst || {};
    window.ntdst.textScroll = textScroll;
}

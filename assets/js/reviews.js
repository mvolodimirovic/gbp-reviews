(function () {
  'use strict';

  function initReadMore(root) {
    (root || document).querySelectorAll('.reviews__item__text').forEach(function (text) {
      if (text.dataset.egbpRm) {
        return;
      }

      text.dataset.egbpRm = '1';

      const btn = text.nextElementSibling;

      if (!btn || !btn.classList.contains('egbp-read-more')) {
        return;
      }

      if (text.scrollHeight <= text.clientHeight + 2) {
        return;
      }

      btn.hidden = false;

      btn.addEventListener('click', function () {
        const expanding = btn.getAttribute('aria-expanded') !== 'true';

        if (expanding) {
          text._clampedHeight = text.offsetHeight;
          text.style.maxHeight = text.scrollHeight + 'px';
          text.classList.add('is-expanded');
          btn.setAttribute('aria-expanded', 'true');
          btn.textContent = btn.dataset.less;
        } else {
          text.style.maxHeight = text._clampedHeight + 'px';
          text.classList.remove('is-expanded');
          btn.setAttribute('aria-expanded', 'false');
          btn.textContent = btn.dataset.more;

          text.addEventListener('transitionend', function onEnd() {
            text.removeEventListener('transitionend', onEnd);

            if (!text.classList.contains('is-expanded')) {
              text.style.maxHeight = '';
            }
          });
        }
      });
    });
  }

  function initReviewsSwiper() {
    const wrappers = document.querySelectorAll('.reviews-swiper');

    if (!wrappers.length || typeof Swiper === 'undefined') {
      return;
    }

    wrappers.forEach(function (el) {
      const section = el.closest('.reviews__wrapper');
      const progress = section ? section.querySelector('.reviews-progress') : null;
      const slidesCount = el.querySelectorAll('.swiper-slide').length;

      new Swiper(el, {
        slidesPerView: 1.08,
        spaceBetween: 20,
        loop: false,
        rewind: true,
        watchOverflow: true,
        centeredSlides: false,
        autoplay: slidesCount > 1
          ? {
              delay: 5000,
              pauseOnMouseEnter: true,
              disableOnInteraction: false,
            }
          : false,
        pagination: progress
          ? {
              el: progress,
              type: 'bullets',
              clickable: true,
            }
          : false,
        breakpoints: {
          768: {
            slidesPerView: 2,
            centeredSlides: false,
          },
          1200: {
            slidesPerView: 3,
            centeredSlides: false,
          },
        },
        a11y: {
          prevSlideMessage: 'Previous review',
          nextSlideMessage: 'Next review',
        },
        on: {
          init: function (sw) {
            sw.slides.forEach(function (slide) {
              initReadMore(slide);
            });
          },
        },
      });
    });
  }

  function init() {
    initReviewsSwiper();
    initReadMore(document);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
}());
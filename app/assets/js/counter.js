/**
 * Animation count-up au scroll (IntersectionObserver)
 */

(function() {
    var counters = document.querySelectorAll('.counter[data-target]');
    if (!counters.length) return;

    var observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (!entry.isIntersecting) return;

            var el = entry.target;
            var target = parseInt(el.dataset.target, 10) || 0;

            if (target === 0) {
                el.textContent = '0';
                observer.unobserve(el);
                return;
            }

            var duration = 1400;
            var startTime = null;
            var startVal = 0;

            function animate(ts) {
                if (!startTime) startTime = ts;
                var elapsed = ts - startTime;
                var progress = Math.min(elapsed / duration, 1);
                // Ease out cubic
                var ease = 1 - Math.pow(1 - progress, 3);
                var current = Math.round(startVal + (target - startVal) * ease);
                el.textContent = current.toLocaleString('fr-FR');

                if (progress < 1) {
                    requestAnimationFrame(animate);
                } else {
                    el.textContent = target.toLocaleString('fr-FR');
                }
            }

            requestAnimationFrame(animate);
            observer.unobserve(el);
        });
    }, { threshold: 0.3 });

    counters.forEach(function(counter) {
        observer.observe(counter);
    });
})();

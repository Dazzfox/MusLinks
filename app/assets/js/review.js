/**
 * Système d'étoiles interactives pour les avis
 */

(function() {
    var noteInput  = document.getElementById('review-note-input');
    var starPicker = document.getElementById('star-picker');

    if (!noteInput || !starPicker) return;

    var stars = starPicker.querySelectorAll('.star-btn');

    function setNote(n) {
        noteInput.value = n;
        stars.forEach(function(btn) {
            var starVal = parseInt(btn.dataset.star, 10);
            btn.style.color = starVal <= n ? '#059669' : '#e2e8f0';
            btn.classList.toggle('active', starVal <= n);
        });
    }

    // Initialiser à 5 étoiles
    setNote(parseInt(noteInput.value, 10) || 5);

    stars.forEach(function(btn) {
        // Survol
        btn.addEventListener('mouseenter', function() {
            var hoverVal = parseInt(this.dataset.star, 10);
            stars.forEach(function(s) {
                s.style.color = parseInt(s.dataset.star, 10) <= hoverVal ? '#34D399' : '#e2e8f0';
            });
        });

        // Quitter le survol
        btn.addEventListener('mouseleave', function() {
            var current = parseInt(noteInput.value, 10) || 5;
            stars.forEach(function(s) {
                s.style.color = parseInt(s.dataset.star, 10) <= current ? '#059669' : '#e2e8f0';
            });
        });

        // Clic
        btn.addEventListener('click', function() {
            setNote(parseInt(this.dataset.star, 10));
        });
    });

    // Exposer globalement pour les templates inline
    window.setNote = setNote;
})();

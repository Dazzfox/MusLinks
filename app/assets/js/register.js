/**
 * Formulaire d'inscription — SIRET format + formule radio visuel
 */

(function() {
    // ── SIRET formatter ──
    var siretInput = document.getElementById('siret-input');
    var siretPreview = document.getElementById('siret-preview');

    if (siretInput) {
        siretInput.addEventListener('input', function() {
            var val = this.value.replace(/\D/g, '').slice(0, 14);
            this.value = val;

            if (val.length === 14 && siretPreview) {
                siretPreview.textContent = val.slice(0,3) + ' ' + val.slice(3,6) + ' ' + val.slice(6,9) + ' ' + val.slice(9);
                siretPreview.classList.remove('hidden');
            } else if (siretPreview) {
                siretPreview.classList.add('hidden');
            }

            // Validation visuelle
            if (val.length === 14) {
                siretInput.classList.remove('border-red-300');
                siretInput.classList.add('border-emerald-500');
            } else if (val.length > 0) {
                siretInput.classList.add('border-red-300');
                siretInput.classList.remove('border-emerald-500');
            } else {
                siretInput.classList.remove('border-red-300', 'border-emerald-500');
            }
        });
    }

    // ── Code postal — chiffres seulement ──
    var cpInput = document.querySelector('[maxlength="5"][inputmode="numeric"]');
    if (cpInput && cpInput.id !== 'siret-input') {
        cpInput.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '').slice(0, 5);
        });
    }

    // ── Formule radio visuel ──
    var radios = document.querySelectorAll('.formule-radio');
    if (radios.length) {
        function updateFormuleUI() {
            radios.forEach(function(radio) {
                var card = radio.closest('.formule-label') && radio.closest('.formule-label').querySelector('.formule-card');
                if (!card) return;
                if (radio.checked) {
                    card.classList.remove('border-slate-200');
                    card.classList.add('border-emerald-500', 'ring-2', 'ring-emerald-200', 'bg-emerald-50');
                } else {
                    card.classList.remove('border-emerald-500', 'ring-2', 'ring-emerald-200', 'bg-emerald-50');
                    card.classList.add('border-slate-200');
                }
            });
        }

        radios.forEach(function(radio) {
            radio.addEventListener('change', updateFormuleUI);
        });

        updateFormuleUI(); // état initial
    }

    // ── Géolocalisation automatique CP/Ville ──
    var cpField   = document.querySelector('input[name$="[codePostal]"]') ||
                    document.querySelector('input[id$="codePostal"]');
    var villeField = document.querySelector('input[name$="[ville]"]') ||
                     document.querySelector('input[id$="ville"]');
    var latField  = document.querySelector('input[name$="[latitude]"]');
    var lonField  = document.querySelector('input[name$="[longitude]"]');

    // Si le champ CP est rempli, tenter le géocodage pour lat/lon
    if (cpField) {
        cpField.addEventListener('blur', function() {
            var cp = this.value.trim();
            var ville = villeField ? villeField.value.trim() : '';
            if (cp.length === 5 && latField && lonField && !latField.value) {
                geocodeAddress(cp + ' ' + ville + ' France');
            }
        });
    }

    function geocodeAddress(address) {
        fetch('/api/geocode?address=' + encodeURIComponent(address))
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.lat && latField && lonField) {
                    latField.value = d.lat;
                    lonField.value = d.lon;
                }
            })
            .catch(function() {});
    }
})();

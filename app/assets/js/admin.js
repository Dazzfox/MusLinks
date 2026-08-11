/**
 * Admin Dashboard — onglets JS + PIN inputs
 */

// ── Onglets ──
function showTab(id) {
    document.querySelectorAll('.tab-content').forEach(function(el) {
        el.classList.add('hidden');
    });
    document.querySelectorAll('.tab-btn').forEach(function(btn) {
        btn.classList.remove('border-emerald-600', 'text-emerald-700', 'bg-emerald-50');
        btn.classList.add('border-transparent', 'text-slate-500');
    });

    var content = document.getElementById('tab-' + id);
    if (content) content.classList.remove('hidden');

    var btn = document.getElementById('tab-btn-' + id);
    if (btn) {
        btn.classList.add('border-emerald-600', 'text-emerald-700', 'bg-emerald-50');
        btn.classList.remove('border-transparent', 'text-slate-500');
    }
}

window.showTab = showTab;

// ── PIN digits (login) ──
(function initPinInputs() {
    var digits = document.querySelectorAll('.pin-digit');
    if (!digits.length) return;

    digits.forEach(function(input, index) {
        input.addEventListener('input', function() {
            var val = this.value.replace(/\D/g, '');
            this.value = val.slice(-1);

            if (this.value) {
                this.classList.add('filled');
                if (index < digits.length - 1) {
                    digits[index + 1].focus();
                }
            }

            // Auto-submit quand tous remplis
            if (index === digits.length - 1 && this.value) {
                var allFilled = Array.from(digits).every(function(d) { return d.value !== ''; });
                if (allFilled) {
                    var form = document.getElementById('pin-form');
                    if (form) form.submit();
                }
            }
        });

        input.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace') {
                if (!this.value && index > 0) {
                    digits[index - 1].value = '';
                    digits[index - 1].classList.remove('filled');
                    digits[index - 1].focus();
                } else {
                    this.classList.remove('filled');
                }
            }
            if (e.key === 'ArrowLeft' && index > 0) digits[index - 1].focus();
            if (e.key === 'ArrowRight' && index < digits.length - 1) digits[index + 1].focus();
        });

        input.addEventListener('paste', function(e) {
            e.preventDefault();
            var paste = (e.clipboardData || window.clipboardData).getData('text')
                .replace(/\D/g, '').slice(0, 6);
            paste.split('').forEach(function(char, i) {
                if (digits[i]) {
                    digits[i].value = char;
                    digits[i].classList.add('filled');
                }
            });
            var last = Math.min(paste.length, digits.length) - 1;
            if (last >= 0) digits[last].focus();
        });
    });

    // Focus premier champ
    digits[0].focus();
})();

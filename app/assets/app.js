import './bootstrap.js';
import './styles/app.css';

// Auto-dismiss flash alerts
document.querySelectorAll('.flash-alert').forEach(function(el) {
    setTimeout(function() {
        el.style.transition = 'opacity .4s, transform .4s';
        el.style.opacity = '0';
        el.style.transform = 'translateX(20px)';
    }, 4000);
    setTimeout(function() { el.remove(); }, 4500);
});

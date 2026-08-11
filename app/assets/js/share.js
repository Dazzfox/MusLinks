/**
 * Web Share API + fallback presse-papier
 */

var ML_Share = (function() {

    function showToast(msg) {
        var t = document.createElement('div');
        t.className = 'toast';
        t.textContent = msg;
        document.body.appendChild(t);
        setTimeout(function() {
            t.style.transition = 'opacity .3s';
            t.style.opacity = '0';
        }, 2200);
        setTimeout(function() { t.remove(); }, 2600);
    }

    function share(encodedName, url) {
        var name = typeof encodedName === 'string' ? decodeURIComponent(encodedName) : encodedName;

        if (navigator.share) {
            navigator.share({
                title: name + ' — MusLinks',
                text: 'Découvrez ' + name + ' sur MusLinks, le réseau des professionnels de confiance.',
                url: url,
            }).catch(function(e) {
                if (e.name !== 'AbortError') {
                    copyToClipboard(url);
                }
            });
        } else {
            copyToClipboard(url);
        }
    }

    function copyToClipboard(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                showToast('Lien copié dans le presse-papier !');
            }).catch(function() {
                fallbackCopy(text);
            });
        } else {
            fallbackCopy(text);
        }
    }

    function fallbackCopy(text) {
        var el = document.createElement('textarea');
        el.value = text;
        el.style.position = 'fixed';
        el.style.opacity = '0';
        document.body.appendChild(el);
        el.select();
        try {
            document.execCommand('copy');
            showToast('Lien copié !');
        } catch (e) {
            showToast('Impossible de copier le lien.');
        }
        document.body.removeChild(el);
    }

    function sharePage() {
        share(document.title, window.location.href);
    }

    return { share: share, sharePage: sharePage, copyToClipboard: copyToClipboard };
})();

window.ML_Share = ML_Share;
window.sharePage = ML_Share.sharePage;

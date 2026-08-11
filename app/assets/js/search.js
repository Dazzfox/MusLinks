/**
 * Recherche AJAX avec debounce 300ms
 */

var ML_Search = (function() {
    var debounceTimer = null;
    var currentDomaine = '';
    var searchUrl = '/api/search';

    function escHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function getStarsHtml(avg) {
        var html = '<div class="flex items-center gap-0.5">';
        for (var i = 1; i <= 5; i++) {
            html += '<svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 ' +
                (i <= Math.round(avg) ? 'text-emerald-600' : 'text-slate-200') +
                '" viewBox="0 0 20 20" fill="currentColor">' +
                '<path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>' +
                '</svg>';
        }
        html += '</div>';
        return html;
    }

    function renderCard(p, index) {
        var colors = ['059669','0891b2','7c3aed','db2777','ea580c','65a30d'];
        var bg = colors[p.id % colors.length];
        var avatar = p.imageName
            ? '<img src="/uploads/professionals/' + escHtml(p.imageName) + '" alt="" class="w-14 h-14 rounded-xl object-cover flex-shrink-0">'
            : '<div class="w-14 h-14 rounded-xl flex items-center justify-center text-white font-serif font-bold text-lg flex-shrink-0" style="background-color:#' + bg + '">' + escHtml(p.initials) + '</div>';

        var mapsUrl = 'https://www.google.com/maps/search/?api=1&query=' +
            encodeURIComponent(p.nomSociete + ' ' + p.ville + ' France');

        return '<div style="animation:fadeInUp .3s ease both;animation-delay:' + (index * 30) + 'ms">' +
            '<div class="pro-card bg-white rounded-2xl border-l-4 border-emerald-600 shadow-sm hover:-translate-y-1 hover:shadow-lg transition-all duration-300 overflow-hidden flex flex-col" style="box-shadow:0 2px 12px rgba(5,150,105,0.08)">' +
            '<div class="p-5 flex-1">' +
            '<div class="flex items-start gap-3 mb-3">' + avatar +
            '<div class="flex-1 min-w-0">' +
            '<h3 class="font-semibold text-emerald-900 text-sm leading-tight truncate">' + escHtml(p.nomSociete) + '</h3>' +
            '<p class="text-xs text-slate-500 truncate">' + escHtml(p.profession) + '</p>' +
            '<div class="flex items-center gap-1 mt-1">' + getStarsHtml(p.starsAverage) +
            '<span class="text-xs text-slate-500 ml-1">' + parseFloat(p.starsAverage).toFixed(1) + ' (' + p.totalAvis + ')</span></div>' +
            '</div></div>' +
            '<div class="text-xs text-slate-600 space-y-1">' +
            '<div class="flex items-center gap-1.5"><span class="text-emerald-600">📍</span>' + escHtml(p.ville) + ' — ' + escHtml(p.codePostal) + '</div>' +
            '<div class="flex items-center gap-1.5 font-mono"><span class="text-slate-400">SIRET</span> ' + escHtml(p.siretFormatted) + '</div>' +
            '</div></div>' +
            '<div class="px-5 pb-4 flex gap-2">' +
            '<a href="' + escHtml(p.url) + '" class="flex-1 text-center bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium py-2 rounded-lg transition-colors">Voir la fiche</a>' +
            '<a href="' + escHtml(mapsUrl) + '" target="_blank" rel="noopener" class="px-3 py-2 border border-emerald-200 hover:border-emerald-400 text-emerald-700 rounded-lg transition-colors" title="Itinéraire">🗺️</a>' +
            '<button onclick="ML_Share.share(\'' + encodeURIComponent(p.nomSociete) + '\',\'' + escHtml(p.url) + '\')" class="px-3 py-2 border border-emerald-200 hover:border-emerald-400 text-emerald-700 rounded-lg transition-colors" title="Partager">📤</button>' +
            '</div></div></div>';
    }

    function renderLoading() {
        return '<div class="col-span-full text-center py-20">' +
            '<div class="w-10 h-10 border-4 border-emerald-200 border-t-emerald-600 rounded-full animate-spin mx-auto mb-3"></div>' +
            '<p class="text-slate-400 text-sm">Recherche en cours…</p></div>';
    }

    function renderEmpty() {
        return '<div class="col-span-full text-center py-20">' +
            '<div class="text-5xl mb-4">🔍</div>' +
            '<h3 class="font-serif text-xl font-bold text-emerald-900 mb-2">Aucun résultat trouvé</h3>' +
            '<p class="text-slate-500 mb-6">Essayez avec d\'autres mots-clés ou une autre ville.</p>' +
            '</div>';
    }

    function doSearch(opts) {
        opts = opts || {};
        var q     = (document.getElementById('search-q') || {}).value || '';
        var ville = (document.getElementById('search-ville') || {}).value || '';
        var sort  = ((document.getElementById('sort-select') || {}).value) || 'createdAt';

        var params = new URLSearchParams({
            q: q,
            ville: ville,
            domaine: currentDomaine,
            tri: sort,
        });

        var container = document.getElementById('results-container');
        var countEl   = document.getElementById('results-count');

        if (container) container.innerHTML = renderLoading();

        fetch(searchUrl + '?' + params.toString())
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var results = data.results || [];

                if (countEl) {
                    countEl.textContent = results.length + ' professionnel' + (results.length > 1 ? 's' : '') + ' trouvé' + (results.length > 1 ? 's' : '');
                    countEl.classList.remove('hidden');
                }

                if (!results.length) {
                    if (container) container.innerHTML = renderEmpty();
                    return;
                }

                if (container) {
                    container.innerHTML = results.map(function(p, i) {
                        return renderCard(p, i);
                    }).join('');
                }
            })
            .catch(function() {
                if (container) {
                    container.innerHTML = '<div class="col-span-full text-center py-10 text-red-500 text-sm">Erreur lors de la recherche. Veuillez réessayer.</div>';
                }
            });
    }

    function debounce(fn, delay) {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(fn, delay);
    }

    function setDomaine(d) {
        currentDomaine = d;
    }

    function getDomaine() {
        return currentDomaine;
    }

    function init(url) {
        if (url) searchUrl = url;

        var qInput     = document.getElementById('search-q');
        var villeInput = document.getElementById('search-ville');
        var sortSelect = document.getElementById('sort-select');
        var form       = document.getElementById('search-form');

        if (qInput) {
            qInput.addEventListener('input', function() { debounce(doSearch, 300); });
        }
        if (villeInput) {
            villeInput.addEventListener('input', function() { debounce(doSearch, 300); });
        }
        if (sortSelect) {
            sortSelect.addEventListener('change', doSearch);
        }
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                doSearch();
            });
        }

        doSearch();
    }

    return { init: init, doSearch: doSearch, setDomaine: setDomaine, getDomaine: getDomaine };
})();

window.ML_Search = ML_Search;

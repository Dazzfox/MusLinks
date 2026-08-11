/**
 * Initialisation Leaflet.js pour la page carte
 * Dépend de Leaflet CDN + MarkerCluster CDN
 */

var ML_Map = (function() {
    var map = null;
    var markers = null;
    var allPros = [];
    var userMarker = null;
    var userPos = null;
    var currentCategory = 'Tous';

    var greenIcon = null;

    function initIcon() {
        greenIcon = L.divIcon({
            html: '<div style="background:#059669;width:22px;height:22px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);border:3px solid white;box-shadow:0 2px 6px rgba(0,0,0,.25)"></div>',
            iconSize: [22, 22],
            iconAnchor: [11, 22],
            popupAnchor: [0, -22],
            className: ''
        });
    }

    function getStars(avg) {
        var s = '';
        for (var i = 1; i <= 5; i++) {
            s += '<span style="color:' + (i <= Math.round(avg) ? '#059669' : '#d1fae5') + '">★</span>';
        }
        return s;
    }

    function buildPopup(p) {
        return '<div style="min-width:200px;font-family:\'DM Sans\',sans-serif;padding:4px">' +
            '<div style="font-weight:600;color:#064E3B;font-size:14px;margin-bottom:2px">' + p.nomSociete + '</div>' +
            '<div style="color:#64748b;font-size:12px;margin-bottom:4px">' + p.profession + '</div>' +
            '<div style="margin-bottom:4px">' + getStars(p.starsAverage) +
            ' <span style="font-size:11px;color:#94a3b8">(' + p.totalAvis + ' avis)</span></div>' +
            '<div style="font-size:12px;color:#64748b;margin-bottom:8px">📍 ' + p.ville + '</div>' +
            '<a href="' + p.url + '" style="display:inline-block;background:#059669;color:white;padding:5px 14px;border-radius:8px;text-decoration:none;font-size:12px;font-weight:500">Voir la fiche →</a>' +
            '</div>';
    }

    function refreshMarkers() {
        if (markers) map.removeLayer(markers);
        markers = L.markerClusterGroup({ maxClusterRadius: 50, showCoverageOnHover: false });

        allPros.forEach(function(p) {
            if (!p.latitude || !p.longitude) return;
            if (currentCategory !== 'Tous' && p.domaineActivite !== currentCategory) return;

            L.marker([p.latitude, p.longitude], { icon: greenIcon })
                .bindPopup(buildPopup(p), { maxWidth: 260 })
                .addTo(markers);
        });

        map.addLayer(markers);
    }

    function filterByCategory(cat) {
        currentCategory = cat;
        document.querySelectorAll('.map-filter-btn').forEach(function(btn) {
            var isActive = btn.dataset.cat === cat;
            btn.classList.toggle('bg-emerald-600', isActive);
            btn.classList.toggle('text-white', isActive);
            btn.classList.toggle('bg-white', !isActive);
            btn.classList.toggle('text-slate-700', !isActive);
        });
        refreshMarkers();
    }

    function filterByRadius(km) {
        if (!userPos) {
            alert('Activez d\'abord votre position GPS.');
            return;
        }

        document.querySelectorAll('.radius-btn').forEach(function(b) {
            b.classList.remove('bg-emerald-600','text-white');
            b.classList.add('bg-white','text-slate-700');
        });
        event.target.classList.add('bg-emerald-600','text-white');
        event.target.classList.remove('bg-white','text-slate-700');

        if (markers) map.removeLayer(markers);
        markers = L.markerClusterGroup({ maxClusterRadius: 50, showCoverageOnHover: false });

        allPros.forEach(function(p) {
            if (!p.latitude || !p.longitude) return;
            var dist = map.distance(userPos, [p.latitude, p.longitude]) / 1000;
            if (dist > km) return;
            L.marker([p.latitude, p.longitude], { icon: greenIcon })
                .bindPopup(buildPopup(p))
                .addTo(markers);
        });

        map.addLayer(markers);
    }

    function locateUser() {
        if (!navigator.geolocation) return;
        navigator.geolocation.getCurrentPosition(function(pos) {
            userPos = [pos.coords.latitude, pos.coords.longitude];
            if (userMarker) map.removeLayer(userMarker);
            userMarker = L.circleMarker(userPos, {
                radius: 9,
                fillColor: '#059669',
                color: 'white',
                weight: 3,
                fillOpacity: 0.9,
            }).addTo(map).bindPopup('<strong style="color:#064E3B">📍 Vous êtes ici</strong>').openPopup();
            map.setView(userPos, 13);
        }, function() {
            alert('Impossible d\'accéder à votre position.');
        });
    }

    function init(apiUrl) {
        initIcon();

        map = L.map('map', { zoomControl: true }).setView([46.6034, 1.8883], 6);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19,
        }).addTo(map);

        var gpsBtn = document.getElementById('gps-map-btn');
        if (gpsBtn) gpsBtn.addEventListener('click', locateUser);

        fetch(apiUrl)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                allPros = data;
                refreshMarkers();
            })
            .catch(function(e) { console.error('Erreur chargement carte:', e); });
    }

    return {
        init: init,
        filterByCategory: filterByCategory,
        filterByRadius: filterByRadius,
        locateUser: locateUser,
    };
})();

window.ML_Map = ML_Map;
window.filterMapByCategory = ML_Map.filterByCategory.bind(ML_Map);
window.filterByRadius = ML_Map.filterByRadius.bind(ML_Map);

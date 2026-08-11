/**
 * Géolocalisation via API navigateur + reverse geocoding Nominatim
 */

function showLoader(inputId) {
    var input = document.getElementById(inputId);
    if (input) {
        input.placeholder = 'Localisation…';
        input.disabled = true;
    }
}

function hideLoader(inputId) {
    var input = document.getElementById(inputId);
    if (input) {
        input.placeholder = 'Ville ou code postal…';
        input.disabled = false;
    }
}

function showGeoError(err) {
    var messages = {
        1: 'Accès à la position refusé. Autorisez la géolocalisation dans votre navigateur.',
        2: 'Position introuvable. Vérifiez votre connexion.',
        3: 'Délai dépassé. Réessayez.',
    };
    showToast(messages[err.code] || 'Erreur de géolocalisation.');
}

function showToast(msg) {
    var t = document.createElement('div');
    t.className = 'toast';
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(function() { t.remove(); }, 3000);
}

async function geolocate(inputId, onSuccess) {
    if (!navigator.geolocation) {
        showToast('La géolocalisation n\'est pas supportée par votre navigateur.');
        return;
    }

    showLoader(inputId);

    navigator.geolocation.getCurrentPosition(
        async function(pos) {
            var lat = pos.coords.latitude;
            var lon = pos.coords.longitude;

            try {
                var r = await fetch(
                    'https://nominatim.openstreetmap.org/reverse' +
                    '?lat=' + lat + '&lon=' + lon +
                    '&format=json&accept-language=fr'
                );
                var d = await r.json();
                var cp   = d.address.postcode || '';
                var city = d.address.city || d.address.town || d.address.village || '';

                var input = document.getElementById(inputId);
                if (input) {
                    input.value = cp || city;
                }

                hideLoader(inputId);

                if (typeof onSuccess === 'function') {
                    onSuccess(cp, city, lat, lon);
                } else if (typeof triggerSearch === 'function') {
                    triggerSearch();
                }
            } catch (e) {
                hideLoader(inputId);
                showToast('Impossible de déterminer votre adresse.');
            }
        },
        function(err) {
            hideLoader(inputId);
            showGeoError(err);
        },
        { timeout: 8000, maximumAge: 60000 }
    );
}

window.geolocate = geolocate;

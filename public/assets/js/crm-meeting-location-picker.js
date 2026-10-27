/**
 * CRM Meeting Location Picker powered by Mappls (MapmyIndia)
 * High-accuracy Indian address autocomplete & vector map pin placement.
 */
(function(window, document) {
    'use strict';

    window._crmMapplsInstances = window._crmMapplsInstances || {};

    /**
     * Dynamically load Mappls Vector Map SDK & Plugins SDK
     */
    window.crmLoadMapplsSDK = function(apiKey) {
        return new Promise(function(resolve, reject) {
            if (!apiKey) {
                return reject(new Error('Mappls API key is missing.'));
            }
            if ((window.mappls && window.mappls.Map) || (window.MapmyIndia && window.MapmyIndia.Map)) {
                return resolve(window.mappls || window.MapmyIndia);
            }
            if (window._mapplsLoadingPromise) {
                return window._mapplsLoadingPromise.then(resolve).catch(reject);
            }

            window._mapplsLoadingPromise = new Promise(function(res, rej) {
                function loadScript(src) {
                    return new Promise(function(sResolve, sReject) {
                        var s = document.createElement('script');
                        s.src = src;
                        s.async = true;
                        s.onload = function() { sResolve(); };
                        s.onerror = function(err) { sReject(err); };
                        document.head.appendChild(s);
                    });
                }

                // Modern Mappls Web Map SDK v3.0 static key endpoints
                var modernSdkUrl = 'https://sdk.mappls.com/map/sdk/web?v=3.0&access_token=' + encodeURIComponent(apiKey);
                var modernPluginsUrl = 'https://sdk.mappls.com/map/sdk/plugins?v=3.0&libraries=search&access_token=' + encodeURIComponent(apiKey);

                // Legacy advancedmaps endpoint
                var legacySdkUrl = 'https://apis.mappls.com/advancedmaps/api/' + encodeURIComponent(apiKey) + '/map_sdk?layer=vector&v=3.0';
                var legacyPluginsUrl = 'https://apis.mappls.com/advancedmaps/api/' + encodeURIComponent(apiKey) + '/map_sdk_plugins?v=3.0&libraries=search';

                // Try modern endpoint first
                loadScript(modernSdkUrl)
                    .then(function() {
                        return loadScript(modernPluginsUrl).catch(function(e) {
                            console.warn('Mappls plugins warning:', e);
                        });
                    })
                    .then(function() {
                        var mapObj = window.mappls || window.MapmyIndia;
                        if (mapObj && mapObj.Map) {
                            res(mapObj);
                        } else {
                            throw new Error('Mappls object not found on window');
                        }
                    })
                    .catch(function(err) {
                        console.warn('Trying legacy Mappls endpoint...', err);
                        loadScript(legacySdkUrl)
                            .then(function() {
                                return loadScript(legacyPluginsUrl).catch(function() {});
                            })
                            .then(function() {
                                var mapObj = window.mappls || window.MapmyIndia;
                                res(mapObj);
                            })
                            .catch(function(err2) {
                                rej(new Error('Mappls SDK could not be loaded from either endpoint: ' + (err2.message || 'Network/Auth error')));
                            });
                    });
            });

            window._mapplsLoadingPromise.then(resolve).catch(reject);
        });
    };

    /**
     * Initialize Mappls Location Picker for a given container prefix
     */
    window.initCrmMapplsPicker = function(prefix, apiKey, defaultLat, defaultLng) {
        var mapContainer = document.getElementById('meeting_map_' + prefix);
        var addrInput = document.getElementById('loc_address_' + prefix);
        var latInput = document.getElementById('loc_lat_' + prefix);
        var lngInput = document.getElementById('loc_lng_' + prefix);
        var gpsBtn = document.getElementById('btn_detect_gps_' + prefix);
        var coordsDisplay = document.getElementById('coords_display_' + prefix);

        if (!mapContainer) return;

        var currentLat = parseFloat(defaultLat) || parseFloat(latInput ? latInput.value : null) || 24.5854; // Default Udaipur / India
        var currentLng = parseFloat(defaultLng) || parseFloat(lngInput ? lngInput.value : null) || 73.7125;

        function updateCoords(lat, lng, addressText) {
            currentLat = parseFloat(lat);
            currentLng = parseFloat(lng);
            if (latInput) latInput.value = currentLat.toFixed(7);
            if (lngInput) lngInput.value = currentLng.toFixed(7);
            if (coordsDisplay) {
                coordsDisplay.textContent = 'Lat: ' + currentLat.toFixed(5) + ', Lng: ' + currentLng.toFixed(5);
            }
            if (addressText && addrInput && !addrInput.value) {
                addrInput.value = addressText;
            }
        }

        // Setup GPS Button with 2-step fallback (High accuracy for Mobile, WiFi/Network fallback for Laptop)
        if (gpsBtn) {
            gpsBtn.addEventListener('click', function(e) {
                e.preventDefault();
                if (!navigator.geolocation) {
                    alert('Geolocation is not supported by your browser.');
                    return;
                }
                var originalHtml = gpsBtn.innerHTML;
                gpsBtn.disabled = true;
                gpsBtn.innerHTML = '<span class="spinner-border spinner-border-sm text-danger me-1"></span> Locating...';

                function onPosSuccess(position) {
                    gpsBtn.disabled = false;
                    gpsBtn.innerHTML = originalHtml;
                    var lat = position.coords.latitude;
                    var lng = position.coords.longitude;
                    updateCoords(lat, lng);

                    var instance = window._crmMapplsInstances[prefix];
                    if (instance && instance.map) {
                        try {
                            instance.map.setCenter({ lat: lat, lng: lng });
                            if (instance.marker) {
                                instance.marker.setPosition({ lat: lat, lng: lng });
                            }
                        } catch (err) {
                            try {
                                instance.map.setCenter([lng, lat]);
                            } catch (err2) {}
                        }
                    }
                }

                function onPosError(error) {
                    // Code 1: User explicitly blocked permission
                    if (error.code === 1) {
                        gpsBtn.disabled = false;
                        gpsBtn.innerHTML = originalHtml;
                        alert('Browser Location Permission Denied!\n\nChrome ke top URL bar me baayein taraf (left) Settings/Tune icon par click karke Location ko "Allow" karein, fir dobara click karein.');
                        return;
                    }

                    // Fallback to normal accuracy (for Windows Laptops/Desktops without satellite GPS chip)
                    navigator.geolocation.getCurrentPosition(
                        onPosSuccess,
                        function(fallbackErr) {
                            gpsBtn.disabled = false;
                            gpsBtn.innerHTML = originalHtml;
                            alert('Location detect nahi ho payi: ' + (fallbackErr.message || error.message) + '\n\nTips: Windows Settings me "Location Service" ON karein, ya map par direct pin click karein.');
                        },
                        { enableHighAccuracy: false, timeout: 15000, maximumAge: 60000 }
                    );
                }

                // Step 1: Try high accuracy (great on Mobile GPS)
                navigator.geolocation.getCurrentPosition(
                    onPosSuccess,
                    onPosError,
                    { enableHighAccuracy: true, timeout: 6000, maximumAge: 0 }
                );
            });
        }

        // If no API key is provided, stop here (fallback HTML remains visible)
        if (!apiKey || apiKey.trim() === '') {
            return;
        }

        // Load Mappls SDK and render map
        window.crmLoadMapplsSDK(apiKey).then(function(mappls) {
            mapContainer.innerHTML = ''; // Clear placeholder
            mapContainer.style.background = '#e2e8f0';

            var MapClass = mappls.Map || (window.MapmyIndia && window.MapmyIndia.Map);
            var MarkerClass = mappls.Marker || (window.MapmyIndia && window.MapmyIndia.Marker);

            if (!MapClass) {
                throw new Error('Map constructor not found on Mappls object');
            }

            var map = new MapClass('meeting_map_' + prefix, {
                center: { lat: currentLat, lng: currentLng },
                zoom: 14,
                fullscreenControl: false,
                hybrid: false
            });

            var marker = MarkerClass ? new MarkerClass({
                map: map,
                position: { lat: currentLat, lng: currentLng },
                draggable: true
            }) : null;

            if (marker) {
                marker.addListener('dragend', function() {
                    try {
                        var pos = marker.getPosition();
                        var lat = typeof pos.lat === 'function' ? pos.lat() : pos.lat;
                        var lng = typeof pos.lng === 'function' ? pos.lng() : pos.lng;
                        updateCoords(lat, lng);
                    } catch (e) {
                        console.error('Error on marker dragend:', e);
                    }
                });
            }

            map.addListener('click', function(e) {
                if (e && e.lngLat) {
                    var lat = e.lngLat.lat;
                    var lng = e.lngLat.lng;
                    try {
                        if (marker) {
                            marker.setPosition({ lat: lat, lng: lng });
                        }
                        updateCoords(lat, lng);
                    } catch (err) {
                        console.error('Error on map click:', err);
                    }
                }
            });

            // Helper: Resolve Coordinates (Handles both lat/lng and Mappls eLoc)
            function resolveCoordinates(item, onResolved) {
                if (!item) return;
                var lat = parseFloat(item.latitude || item.lat);
                var lng = parseFloat(item.longitude || item.lng);
                if (!isNaN(lat) && !isNaN(lng)) {
                    return onResolved(lat, lng);
                }
                // If coordinates not directly in payload, resolve eLoc via Mappls
                if (item.eLoc) {
                    var elocUrl = 'https://sdk.mappls.com/map_v3/?elm=' + encodeURIComponent(btoa(item.eLoc)) + '&k=' + encodeURIComponent(btoa(apiKey)) + '&a=493e9bm237c60d411fbb64d3efdfcfc503ffc';
                    fetch(elocUrl)
                        .then(function(r) { return r.text(); })
                        .then(function(enc) {
                            var dec = '';
                            for (var i = 0; i < enc.length; i++) {
                                dec += String.fromCharCode(enc.charCodeAt(i) ^ 0x71);
                            }
                            var jsonStr = dec.split('').reverse().join('');
                            var parsed = JSON.parse(jsonStr);
                            if (parsed && parsed.results && parsed.results[0]) {
                                var elLat = parseFloat(parsed.results[0].latitude);
                                var elLng = parseFloat(parsed.results[0].longitude);
                                if (!isNaN(elLat) && !isNaN(elLng)) {
                                    onResolved(elLat, elLng);
                                }
                            }
                        })
                        .catch(function(err) {
                            console.warn('Mappls eloc resolve error:', err);
                        });
                }
            }

            // Universal Address Search (Handles full Indian postal addresses, airport names, office complexes)
            function doAddressSearch(rawQuery) {
                var query = (rawQuery || (addrInput ? addrInput.value : '')).trim();
                if (!query) return;

                var searchBtn = document.getElementById('btn_search_address_' + prefix);
                var origBtnHtml = searchBtn ? searchBtn.innerHTML : '';
                if (searchBtn) {
                    searchBtn.disabled = true;
                    searchBtn.innerHTML = '<span class="spinner-border spinner-border-sm text-white me-1"></span> Searching...';
                }

                function finish() {
                    if (searchBtn) {
                        searchBtn.disabled = false;
                        searchBtn.innerHTML = origBtnHtml;
                    }
                }

                function applyFoundCoords(lat, lng, zoomLevel) {
                    updateCoords(lat, lng);
                    try {
                        // MapLibre / Mappls expects { lat, lng } or [lng, lat]
                        try {
                            map.setCenter({ lat: lat, lng: lng });
                        } catch (errCenter) {
                            map.setCenter([lng, lat]);
                        }
                        map.setZoom(zoomLevel || 13);
                        if (marker) {
                            try {
                                marker.setPosition({ lat: lat, lng: lng });
                            } catch (errPos) {
                                if (marker.setLngLat) marker.setLngLat([lng, lat]);
                            }
                        } else if (MarkerClass) {
                            marker = new MarkerClass({
                                map: map,
                                position: { lat: lat, lng: lng },
                                draggable: true
                            });
                        }
                        if (map.resize) map.resize();
                    } catch (e) {
                        console.warn('Map update error:', e);
                    }
                    finish();
                }

                // Prepare intelligent candidates from comma-separated address
                // e.g. "Neemuch, Madhya Pradesh" or "Maharana Pratap Airport, NH 76, Dabok..."
                var parts = query.split(',').map(function(s) { return s.trim(); }).filter(Boolean);
                var candidates = [query];
                if (parts.length > 1) {
                    candidates.push(parts[0]); // e.g. "Neemuch" or "Maharana Pratap Airport"
                    if (!parts[0].toLowerCase().includes('udaipur') && parts.length > 2) {
                        candidates.push(parts[0] + ', Udaipur');
                    }
                    candidates.push(parts[0] + ', ' + parts[parts.length - 1]); // e.g. "Neemuch, Madhya Pradesh"
                }

                function tryCandidate(index) {
                    if (index >= candidates.length) {
                        finish();
                        alert('Location match nahi mila. Aap direct map par click karke bhi pin drop kar sakte hain.');
                        return;
                    }

                    var cand = candidates[index];
                    var nomUrl = 'https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(cand) + '&countrycodes=in&limit=5';
                    fetch(nomUrl)
                        .then(function(r) { return r.json(); })
                        .then(function(res) {
                            if (res && res.length > 0) {
                                // Prefer populated places (city, town, suburb, village) over geometric boundary centroids (which might be in lakes/forests)
                                var best = res[0];
                                for (var i = 0; i < res.length; i++) {
                                    var itemType = res[i].type || '';
                                    var itemClass = res[i].class || '';
                                    if (itemClass === 'place' || itemType === 'city' || itemType === 'town' || itemType === 'suburb') {
                                        best = res[i];
                                        break;
                                    }
                                }

                                var bLat = parseFloat(best.lat);
                                var bLng = parseFloat(best.lon);
                                if (!isNaN(bLat) && !isNaN(bLng)) {
                                    // Determine intelligent zoom level (cities/districts need wider view, landmarks need close view)
                                    var zoom = 14;
                                    var t = (best.type || '').toLowerCase();
                                    var c = (best.class || '').toLowerCase();
                                    if (c === 'boundary' || t === 'administrative' || t === 'state' || t === 'county') {
                                        zoom = 11;
                                    } else if (t === 'city' || t === 'town' || c === 'place') {
                                        zoom = 12;
                                    } else if (t === 'aeroway' || t === 'aerodrome' || c === 'building' || c === 'amenity') {
                                        zoom = 15;
                                    }
                                    applyFoundCoords(bLat, bLng, zoom);
                                    return;
                                }
                            }
                            tryCandidate(index + 1);
                        })
                        .catch(function() {
                            tryCandidate(index + 1);
                        });
                }

                tryCandidate(0);
            }

            // Hook Search button & Enter key
            var searchBtn = document.getElementById('btn_search_address_' + prefix);
            if (searchBtn) {
                searchBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    doAddressSearch();
                });
            }

            if (addrInput) {
                addrInput.addEventListener('keydown', function(evt) {
                    if (evt.key === 'Enter') {
                        evt.preventDefault();
                        doAddressSearch();
                    }
                });
            }

            // Mappls Autosuggest Search Plugin
            var SearchFn = mappls.search || (window.MapmyIndia && window.MapmyIndia.search);
            if (addrInput && SearchFn) {
                try {
                    // Inject helper CSS to ensure Mappls search dropdown appears above modals/offcanvas
                    if (!document.getElementById('mmi_search_styles')) {
                        var styleTag = document.createElement('style');
                        styleTag.id = 'mmi_search_styles';
                        styleTag.innerHTML = '.mmi_search_dv { z-index: 10555 !important; max-height: 240px; overflow-y: auto; }';
                        document.head.appendChild(styleTag);
                    }

                    // Attach Mappls auto-suggest dropdown to input
                    SearchFn(addrInput, {
                        location: [currentLat, currentLng],
                        access_token: apiKey,
                        region: 'IND',
                        hyperLocal: true
                    }, function(data) {
                        var item = Array.isArray(data) ? data[0] : (data && data.suggestedLocations ? data.suggestedLocations[0] : data);
                        if (item) {
                            var placeText = item.placeName ? (item.placeName + (item.placeAddress ? (', ' + item.placeAddress) : '')) : (item.placeAddress || item.formatted_address || '');
                            resolveCoordinates(item, function(lat, lng) {
                                var zoom = (item.type === 'CITY' || item.type === 'DIST') ? 12 : 15;
                                applyFoundCoords(lat, lng, zoom);
                                if (addrInput && placeText) addrInput.value = placeText;
                            });
                        }
                    });
                } catch (e) {
                    console.warn('Mappls search plugin attach error:', e);
                }
            }

            // Save instance reference
            window._crmMapplsInstances[prefix] = {
                map: map,
                marker: marker,
                updateCoords: updateCoords
            };

            // Trigger map resize when modal / offcanvas becomes visible
            var closestModal = mapContainer.closest('.modal, .offcanvas');
            if (closestModal) {
                var eventName = closestModal.classList.contains('modal') ? 'shown.bs.modal' : 'shown.bs.offcanvas';
                closestModal.addEventListener(eventName, function() {
                    setTimeout(function() {
                        try {
                            if (map.resize) map.resize();
                            window.dispatchEvent(new Event('resize'));
                        } catch (err) {}
                    }, 300);
                });
            }
        }).catch(function(err) {
            console.warn('Mappls SDK could not be loaded:', err.message);
            mapContainer.innerHTML = '<div class="d-flex flex-column align-items-center justify-content-center h-100 p-2 text-center text-muted fs-11">' +
                '<i class="feather-alert-triangle text-warning fs-18 mb-1"></i>' +
                '<span class="fw-bold text-dark fs-12">Mappls Key Authorization Failed</span>' +
                '<span class="text-muted fs-10 mt-0.5">Leave Whitelist IPs empty & ensure Whitelist Domain has 127.0.0.1 in Mappls console.</span>' +
                '</div>';
        });
    };

    /**
     * Show/Hide meeting location picker based on activity type
     */
    window.crmToggleMeetingLocation = function(typeValue, prefix, isGeoVerificationEnabled) {
        var container = document.getElementById('container_meeting_location_' + prefix);
        if (!container) return;

        var val = (typeValue || '').trim().toLowerCase();
        var isMeeting = val === 'meeting' || val === 'demo' || val.includes('meeting') || val.includes('visit');
        var isGeoEnabled = (isGeoVerificationEnabled === true || isGeoVerificationEnabled === '1' || isGeoVerificationEnabled === 1 || isGeoVerificationEnabled === 'true');

        if (isGeoEnabled && isMeeting) {
            container.style.display = 'block';
            setTimeout(function() {
                var inst = (window._crmMapplsInstances && window._crmMapplsInstances[prefix]) ? window._crmMapplsInstances[prefix] : null;
                if (inst && inst.map) {
                    if (typeof inst.map.resize === 'function') {
                        inst.map.resize();
                    }
                    if (typeof inst.map.invalidateSize === 'function') {
                        inst.map.invalidateSize();
                    }
                }
                window.dispatchEvent(new Event('resize'));
            }, 200);
        } else {
            container.style.display = 'none';
        }
    };

})(window, document);

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>Leaflet GeoSearch Autocomplete</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <!-- Leaflet GeoSearch CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet-geosearch@3.11.0/dist/geosearch.css" />

    <style>
        #map {
            height: 600px;
            width: 100%;
        }
    </style>
</head>

<body>
    <div id="map"></div>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <!-- Leaflet GeoSearch JS -->
    <script src="https://unpkg.com/leaflet-geosearch@3.11.0/dist/bundle.min.js"></script>

    <script>
        const map = L.map('map').setView([13.41, 122.56], 6);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors',
        }).addTo(map);

        let marker = null;

        const provider = new window.GeoSearch.OpenStreetMapProvider({
            params: {
                countrycodes: 'PH', // Optional: restrict to Philippines
            },
        });

        const searchControl = new window.GeoSearch.GeoSearchControl({
            provider: provider,
            style: 'bar',
            showMarker: false,
            autoClose: true,
            searchLabel: '🔍 Search location...',
            keepResult: true,
        });

        map.addControl(searchControl);

        map.on('geosearch/showlocation', function(result) {
            const {
                x,
                y,
                label
            } = result.location;
            const latlng = [y, x];
            map.setView(latlng, 16);

            if (marker) {
                marker.setLatLng(latlng);
            } else {
                marker = L.marker(latlng, {
                    draggable: true
                }).addTo(map);
            }

            marker.bindPopup(label).openPopup();
        });
    </script>
</body>

</html>

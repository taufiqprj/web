<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rute Pengiriman</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <style>
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
        }
        #map {
            height: 100vh;
            width: 100%;
        }
        #calculateRoute {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 1000;
        }
    </style>
</head>
<body>
    <div id="map"></div>
    <button id="calculateRoute">Hitung Rute Tercepat</button>

    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <script>
        let map, markers = [], locationsData, routeLayer;

        async function fetchLocations() {
            try {
                const response = await fetch('get_confirmed_orders.php');
                locationsData = await response.json();
                initMap();
                calculateRoute();
            } catch (error) {
                console.error('Error fetching locations:', error);
            }
        }

        function initMap() {
            map = L.map('map').setView([-7.851402, 111.463408], 12);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
            addMarkers();
        }

        function addMarkers() {
            let storeIcon = L.icon({
                iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
                iconSize: [25, 41],
                iconAnchor: [12, 41],
            });

            locationsData.locations.forEach((location, index) => {
                let marker;
                if (location.type === 'toko') {
                    marker = L.marker([location.lat, location.lng], {icon: storeIcon}).addTo(map);
                } else {
                    marker = L.marker([location.lat, location.lng]).addTo(map);
                }
                marker.bindPopup(location.name + (location.details ? '<br>' + location.details : ''));
                markers.push(marker);
            });
        }

        function calculateDistance(lat1, lon1, lat2, lon2) {
            const R = 6371; // Radius bumi dalam km
            const dLat = (lat2 - lat1) * Math.PI / 180;
            const dLon = (lon2 - lon1) * Math.PI / 180;
            const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                      Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * 
                      Math.sin(dLon/2) * Math.sin(dLon/2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
            return R * c; // Jarak dalam km
        }

        function dijkstra(start, destinations) {
            let unvisited = [...destinations];
            let route = [start];
            let current = start;

            while (unvisited.length > 0) {
                let nearestIndex = 0;
                let minDistance = Infinity;

                for (let i = 0; i < unvisited.length; i++) {
                    let distance = calculateDistance(current.lat, current.lng, unvisited[i].lat, unvisited[i].lng);
                    if (distance < minDistance) {
                        minDistance = distance;
                        nearestIndex = i;
                    }
                }

                current = unvisited[nearestIndex];
                route.push(current);
                unvisited.splice(nearestIndex, 1);
            }

            return route;
        }

        async function calculateRoute() {
            let toko = locationsData.locations[0];
            let destinations = locationsData.locations.slice(1);

            let route = dijkstra(toko, destinations);

            // Hapus marker lama
            markers.forEach(m => map.removeLayer(m));
            markers = [];

            // Tambahkan marker toko
            let storeIcon = L.icon({
                iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
                iconSize: [25, 41],
                iconAnchor: [12, 41],
            });
            markers.push(L.marker([toko.lat, toko.lng], {icon: storeIcon}).addTo(map)
                .bindPopup(toko.name));

            // Tambahkan marker tujuan dengan nomor urut
            route.slice(1).forEach((dest, index) => {
                let icon = L.divIcon({
                    className: 'custom-div-icon',
                    html: `<div style='background-color:#4838cc;color:white;padding:5px;border-radius:50%;'>${index + 1}</div>`,
                    iconSize: [30, 30],
                    iconAnchor: [15, 15]
                });

                markers.push(L.marker([dest.lat, dest.lng], {icon: icon}).addTo(map)
                    .bindPopup(dest.name + (dest.details ? '<br>' + dest.details : '')));
            });

            // Build the waypoints string for the OSRM API
            let waypoints = route.map(loc => `${loc.lng},${loc.lat}`).join(';');

            // Fetch the route from the OSRM API
            try {
                const response = await fetch(`http://router.project-osrm.org/route/v1/driving/${waypoints}?overview=full&geometries=geojson`);
                const data = await response.json();
                const coordinates = data.routes[0].geometry.coordinates;

                // Draw the route on the map with a different color
                drawRoute(coordinates, 'red');
            } catch (error) {
                console.error('Error fetching route:', error);
            }
        }

        function drawRoute(coordinates, color) {
            if (routeLayer) {
                map.removeLayer(routeLayer);
            }

            const latLngs = coordinates.map(coord => [coord[1], coord[0]]);
            routeLayer = L.polyline(latLngs, { color: color }).addTo(map);
            map.fitBounds(routeLayer.getBounds());
        }

        document.addEventListener('DOMContentLoaded', (event) => {
            fetchLocations();
            document.getElementById('calculateRoute').addEventListener('click', calculateRoute);
        });
    </script>
</body>
</html>

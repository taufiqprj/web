<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <title>Web Gis Application</title>
    <link rel="icon" type="image/x-icon" href="assets/favicon.ico" />
    <!-- Font Awesome icons (free version)-->
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <!-- Google fonts-->
    <link rel="preconnect" href="https://fonts.gstatic.com" />
    <link href="https://fonts.googleapis.com/css2?family=Tinos:ital,wght@0,400;0,700;1,400;1,500;1,700&amp;display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,wght@0,400;0,500;0,700;1,400;1,500;1,700&amp;display=swap" rel="stylesheet" />
    
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css"
    integrity="sha256-kLaT2GOSpHechhsozzB+flnD+zUyjE2LlfWPgU04xyI=" crossorigin="" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.css" />

    <!-- Core theme CSS (includes Bootstrap)-->
    <link href="css/styles.css" rel="stylesheet" />
    <style>
        #map {
            width: 100%;
            height: 100vh;
        }
    </style>
</head>
<body>
    <div class="masthead">
        <div class="container-fluid">
            <div class="row justify-content-center">
                <div class="col">
                    <div>
                        <h1 class="fst-italic" style="color: white">
                            Web Gis Application
                        </h1>
                    </div>
                    <div class="card">
                        <div class="card-body">
                            <div id="map"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Bootstrap core JS-->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Core theme JS-->
    <script src="js/scripts.js"></script>
    <script src="https://cdn.startbootstrap.com/sb-forms-latest.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js"
    integrity="sha256-WBkoXOwTeyKclOHuWtc+i2uENFpDZ9YPdf5Hf+D7ewM=" crossorigin=""></script>
    <script src="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.js"></script>
    <script src="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.js"></script>
    
    <script>
        var osm = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 17,
            attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        });

        var Stadia_Dark = L.tileLayer(
            'https://tiles.stadiamaps.com/tiles/alidade_smooth_dark/{z}/{x}/{y}{r}.png', {
                maxZoom: 20,
                attribution: '&copy; <a href="https://stadiamaps.com/">Stadia Maps</a>, &copy; <a href="https://openmaptiles.org/">OpenMapTiles</a> &copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors'
            });

        var Esri_WorldStreetMap = L.tileLayer(
            'https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}', {
                attribution: 'Tiles &copy; Esri &mdash; Source: Esri, DeLorme, NAVTEQ, USGS, Intermap, iPC, NRCAN, Esri Japan, METI, Esri China (Hong Kong), Esri (Thailand), TomTom, 2012'
            });

        const ringan = L.layerGroup();
        const sedang = L.layerGroup();
        const parah = L.layerGroup();

        var map = L.map('map', {
            center: [-7.87, 111.46],
            zoom: 14,
            layers: [osm, ringan, sedang, parah]
        });

        const baseLayers = {
            'Openstreetmap': osm,
            'StadiaDark': Stadia_Dark,
            'Esri': Esri_WorldStreetMap
        };

        const overLayers = {
            'Rusak Ringan': ringan,
            'Rusak Sedang': sedang,
            'Rusak Parah': parah
        };

        const layerControl = L.control.layers(baseLayers, overLayers).addTo(map);

        // Calculate distance between two coordinates
        function calculateDistance(lat1, lon1, lat2, lon2) {
            const R = 6371; // Radius of the Earth in km
            const dLat = (lat2 - lat1) * Math.PI / 180;
            const dLon = (lon2 - lon1) * Math.PI / 180;
            const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                      Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * 
                      Math.sin(dLon/2) * Math.sin(dLon/2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
            return R * c; // Distance in km
        }

        // Dijkstra's algorithm to find shortest path
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

        // Function to draw route with numbered markers
        function drawRouteWithMarkers(locations) {
            const waypoints = locations.map(loc => L.latLng(loc.lat, loc.lng));

            // Draw route
            L.Routing.control({
                waypoints: waypoints,
                routeWhileDragging: true,
                geocoder: L.Control.Geocoder.nominatim(),
                showAlternatives: true,
                altLineOptions: {
                    styles: [
                        {color: 'black', opacity: 0.15, weight: 9},
                        {color: 'white', opacity: 0.8, weight: 6},
                        {color: 'blue', opacity: 0.5, weight: 2}
                    ]
                },
                createMarker: function(i, waypoint) {
                    return L.marker(waypoint.latLng, {
                        icon: L.divIcon({
                            className: 'custom-div-icon',
                            html: `<div style="background-color:#2A2A2A;color:#ffffff;width:30px;height:30px;border-radius:50%;text-align:center;line-height:30px;">${i+1}</div>`,
                            iconSize: [30, 30]
                        })
                    });
                }
            }).addTo(map);
        }

        // Fetch confirmed orders and plot routes
        fetch('get_confirmed_orders.php')
            .then(response => response.json())
            .then(data => {
                const start = data.locations.find(loc => loc.type === "toko");
                const destinations = data.locations.filter(loc => loc.type === "destination");

                // Calculate the route using Dijkstra's algorithm
                const orderedLocations = dijkstra(start, destinations);

                // Draw route with numbered markers
                drawRouteWithMarkers(orderedLocations);
            })
            .catch(error => console.error('Error fetching locations:', error));
    </script>
</body>
</html>

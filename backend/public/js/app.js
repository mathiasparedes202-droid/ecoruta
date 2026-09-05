const API_BASE = '/api';

const defaultLocations = [
    
];

// Constantes ecológicas
const CO2_FACTOR_KG_PER_KM = 0.180; // kg CO2 ahorrado vs combustión
const BASE_TARIFF_GS = 12000;
const KM_TARIFF_GS = 2500;

// Estado
let currentLocations = [...defaultLocations];
let routingControl = null;
let pickMode = false;
let routesCount = 0;
let totalCo2Saved = 0;
let totalKmTraveled = 0;
let selectedVehicle = 'bicicleta';
let pendingConfirmCallback = null;
let lastCalculatedRoute = null;

// Inicialización de Mapa en Concepción
const map = L.map('map', {
    zoomControl: true
}).setView([-23.4004, -57.4330], 13);
const streets = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors | EcoRuta Concepción'
});
const satellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
    attribution: '© Esri Satellite'
});
const topo = L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenTopoMap'
});

streets.addTo(map);

L.control.layers({
    "<span><i class='fas fa-map'></i> Calles Eco</span>": streets,
    "<span><i class='fas fa-satellite-dish'></i> Satélite</span>": satellite,
    "<span><i class='fas fa-mountain'></i> Topográfico</span>": topo
}, null, { position: 'topright' }).addTo(map);

// Buscador
L.Control.geocoder({
    defaultMarkGeocode: false,
    placeholder: 'Buscar dirección en Concepción...',
    errorMessage: 'No se encontró la dirección'
}).on('markgeocode', function(e) {
    const latlng = e.geocode.center;
    map.setView(latlng, 15);
    showToast(`Ubicación encontrada: ${e.geocode.name}`, 'success');
}).addTo(map);

// Marcadores
const markers = L.markerClusterGroup({
    spiderfyOnMaxZoom: true,
    showCoverageOnHover: false,
    maxClusterRadius: 40
});
map.addLayer(markers);

function createEcoIcon(type = 'default') {
    const color = type === 'Comercio' ? '#059669' : type === 'Hub Principal' ? '#0284c7' : '#16a34a';
    const iconClass = type === 'Comercio' ? 'fa-store' : type === 'Hub Principal' ? 'fa-warehouse' : 'fa-leaf';

    return L.divIcon({
        html: `<div style="
            background: ${color};
            width: 34px;
            height: 34px;
            border-radius: 50% 50% 50% 0;
            transform: rotate(-45deg);
            border: 3px solid white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            display: flex;
            align-items: center;
            justify-content: center;
        ">
            <i class="fas ${iconClass}" style="
                transform: rotate(45deg);
                color: white;
                font-size: 13px;
            "></i>
        </div>`,
        className: 'custom-marker',
        iconSize: [34, 34],
        iconAnchor: [17, 34],
        popupAnchor: [0, -34]
    });
}

function showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    const icon = type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'leaf';
    toast.innerHTML = `
        <i class="fas fa-${icon}"></i>
        <span>${message}</span>
    `;
    container.appendChild(toast);
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(50px)';
        toast.style.transition = 'all 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 3200);
}

function showConfirm(title, message, onConfirm) {
    const overlay = document.getElementById('modalOverlay');
    document.getElementById('modalTitle').textContent = title;
    document.getElementById('modalMessage').textContent = message;
    document.getElementById('modalQrContainer').style.display = 'none';
    pendingConfirmCallback = onConfirm;
    overlay.classList.add('active');
}

function showQrModal(orderTitle, qrDataText, onConfirmed) {
    const overlay = document.getElementById('modalOverlay');
    document.getElementById('modalTitle').innerHTML = `<i class="fas fa-qrcode" style="color: #059669;"></i> Código QR de Entrega`;
    document.getElementById('modalMessage').textContent = `Muestra o escanea este código para validar: ${orderTitle}`;
    
    const qrContainer = document.getElementById('modalQrContainer');
    const qrBox = document.getElementById('qrCodeBox');
    qrContainer.style.display = 'block';
    qrBox.innerHTML = '';
    
    if (window.QRCode) {
        new QRCode(qrBox, {
            text: qrDataText,
            width: 160,
            height: 160,
            colorDark: "#047857",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.H
        });
    } else {
        qrBox.innerHTML = `<p style="font-size: 12px; color: #047857;"><strong>${qrDataText}</strong></p>`;
    }

    pendingConfirmCallback = () => {
        if (onConfirmed) onConfirmed();
        showToast('¡Acción procesada con éxito!', 'success');
    };
    overlay.classList.add('active');
}

document.getElementById('modalCancel').addEventListener('click', () => {
    document.getElementById('modalOverlay').classList.remove('active');
    pendingConfirmCallback = null;
});

document.getElementById('modalConfirm').addEventListener('click', () => {
    document.getElementById('modalOverlay').classList.remove('active');
    if (pendingConfirmCallback) {
        pendingConfirmCallback();
        pendingConfirmCallback = null;
    }
});

document.getElementById('modalOverlay').addEventListener('click', (e) => {
    if (e.target === e.currentTarget) {
        e.currentTarget.classList.remove('active');
        pendingConfirmCallback = null;
    }
});

// Cargar pedidos y rutas desde ecoruta_db
async function syncWithBackend() {
    try {
        const res = await fetch(`${API_BASE}/orders`);
        if (res.ok) {
            const data = await res.json();
            if (Array.isArray(data) && data.length > 0) {
                // Sincronizar datos de la base de datos
                data.forEach((p, idx) => {
                    if (!currentLocations.some(l => l.name === p.direccion_origen)) {
                        currentLocations.push({
                            name: p.direccion_origen || `Comercio #${p.id_comercio}`,
                            lat: -23.4004 + (idx * 0.003),
                            lng: -57.4330 - (idx * 0.002),
                            type: 'Comercio',
                            description: p.detalle_paquete || 'Pedido en ecoruta_db'
                        });
                    }
                });
                renderLocations();
                showToast(`Sincronizado con ecoruta_db: ${data.length} pedidos en base de datos`, 'info');
            }
        }
    } catch {
        console.info('Operando en modo local/sincronizado con backend');
    }
}

function renderLocations() {
    markers.clearLayers();
    const list = document.getElementById('locationList');
    const startSelect = document.getElementById('startSelect');
    const endSelect = document.getElementById('endSelect');
    const emptyState = document.getElementById('emptyState');

    list.innerHTML = '';
    startSelect.innerHTML = '<option value="">Seleccionar punto de inicio...</option>';
    endSelect.innerHTML = '<option value="">Seleccionar destino...</option>';

    if (currentLocations.length === 0) {
        emptyState.style.display = 'block';
    } else {
        emptyState.style.display = 'none';
    }

    currentLocations.forEach((location, index) => {
        const marker = L.marker([location.lat, location.lng], {
            icon: createEcoIcon(location.type)
        });

        marker.bindPopup(`
            <div class="popup-content">
                <h4><i class="fas fa-leaf" style="color: #059669;"></i> ${location.name}</h4>
                <p>${location.description || 'Punto ecológico EcoRuta'}</p>
                <div style="display: flex; gap: 6px; margin-top: 8px;">
                    <button class="popup-btn" onclick="centerOnLocation(${index})">
                        <i class="fas fa-crosshairs"></i> Centrar
                    </button>
                    <button class="popup-btn" style="background: #0284c7;" onclick="setAsDestination(${index})">
                        <i class="fas fa-route"></i> Ir aquí
                    </button>
                </div>
            </div>
        `);

        markers.addLayer(marker);

        const li = document.createElement('li');
        li.className = 'location-item';
        li.innerHTML = `
            <div class="location-item-header">
                <div class="location-icon">
                    <i class="fas ${location.type === 'Comercio' ? 'fa-store' : 'fa-leaf'}"></i>
                </div>
                <div class="location-info">
                    <h3>${location.name}</h3>
                    <p>${location.lat.toFixed(4)}, ${location.lng.toFixed(4)}</p>
                    <span class="badge-tag"><i class="fas fa-check"></i> ${location.type || 'Punto Eco'}</span>
                </div>
            </div>
            <div class="location-actions">
                <button class="btn btn-secondary" onclick="centerOnLocation(${index}); event.stopPropagation();">
                    <i class="fas fa-eye"></i> Ver
                </button>
                <button class="btn btn-secondary" style="color: #059669;" onclick="openQrForLocation(${index}); event.stopPropagation();">
                    <i class="fas fa-qrcode"></i> QR
                </button>
                <button class="btn btn-danger" onclick="deleteLocation(${index}); event.stopPropagation();">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        `;
        li.onclick = () => centerOnLocation(index);
        list.appendChild(li);

        const option = document.createElement('option');
        option.value = index;
        option.textContent = `${location.name} (${location.type || 'Punto'})`;
        startSelect.appendChild(option.cloneNode(true));
        endSelect.appendChild(option);
    });

    document.getElementById('totalLocations').textContent = currentLocations.length;
}

window.centerOnLocation = function(index) {
    const loc = currentLocations[index];
    map.setView([loc.lat, loc.lng], 15);
    if (window.innerWidth <= 768) {
        document.getElementById('sidebar').classList.remove('open');
    }
};

window.setAsDestination = function(index) {
    document.querySelector('[data-tab="route"]').click();
    document.getElementById('endSelect').value = index;
    showToast(`Destino establecido en: ${currentLocations[index].name}`, 'info');
};

window.openQrForLocation = function(index) {
    const loc = currentLocations[index];
    const qrData = `ECORUTA-LOC#${index + 1}-${loc.name}-LAT:${loc.lat}-LNG:${loc.lng}`;
    showQrModal(loc.name, qrData);
};

window.deleteLocation = function(index) {
    showConfirm(
        'Eliminar punto ecológico',
        `¿Estás seguro de eliminar "${currentLocations[index].name}"?`,
        () => {
            currentLocations.splice(index, 1);
            renderLocations();
            showToast('Ubicación eliminada', 'success');
        }
    );
};

// Cambio de pestañas
document.querySelectorAll('.tab').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        tab.classList.add('active');
        const targetId = `${tab.dataset.tab}-tab`;
        const targetElement = document.getElementById(targetId);
        if (targetElement) {
            targetElement.classList.add('active');
        }
    });
});
document.querySelectorAll('.vehicle-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.vehicle-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        selectedVehicle = this.dataset.vehicle;
        showToast(`Vehículo: ${selectedVehicle === 'bicicleta' ? 'Bicicleta (0% Emisiones)' : 'Vehículo Eléctrico'}`, 'info');
    });
});

document.getElementById('addLocationForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const lat = parseFloat(document.getElementById('lat').value);
    const lng = parseFloat(document.getElementById('lng').value);
    const name = document.getElementById('name').value.trim();
    const type = document.getElementById('type').value;
    const description = document.getElementById('description').value.trim();

    if (name && !isNaN(lat) && !isNaN(lng)) {
        currentLocations.push({ lat, lng, name, type, description });
        renderLocations();
        try {
            await fetch(`${API_BASE}/orders`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    direccion_origen: name,
                    direccion_destino: 'Concepción Centro',
                    detalle_paquete: `Punto Registrado: ${type}`,
                    observaciones: description || 'Registrado desde mapa interactivo'
                })
            });
            showToast(`"${name}" guardado exitosamente en ecoruta_db`, 'success');
        } catch {
            showToast(`"${name}" agregado al mapa`, 'success');
        }

        this.reset();
        document.querySelector('[data-tab="locations"]').click();
        map.setView([lat, lng], 15);
    }
});

// Modo selección en mapa
document.getElementById('pickFromMap').addEventListener('click', () => {
    pickMode = true;
    document.getElementById('pickModeBanner').classList.add('active');
    showToast('Haz clic sobre el mapa para capturar las coordenadas', 'info');
    if (window.innerWidth <= 768) {
        document.getElementById('sidebar').classList.remove('open');
    }
});

map.on('click', function(e) {
    if (pickMode) {
        document.getElementById('lat').value = e.latlng.lat.toFixed(6);
        document.getElementById('lng').value = e.latlng.lng.toFixed(6);
        pickMode = false;
        document.getElementById('pickModeBanner').classList.remove('active');
        showToast('Coordenadas fijadas con éxito', 'success');
        document.getElementById('sidebar').classList.add('open');
        document.querySelector('[data-tab="add"]').click();
    }
});

// Cálculo de Rutas y Ahorro de CO2
document.getElementById('calculateRoute').addEventListener('click', function() {
    const startIndex = document.getElementById('startSelect').value;
    const endIndex = document.getElementById('endSelect').value;

    if (startIndex === '' || endIndex === '') {
        showToast('Selecciona un punto de inicio y un destino', 'error');
        return;
    }

    if (startIndex === endIndex) {
        showToast('El inicio y destino deben ser diferentes', 'error');
        return;
    }

    const start = currentLocations[startIndex];
    const end = currentLocations[endIndex];

    if (routingControl) {
        map.removeControl(routingControl);
    }

    const routeColor = selectedVehicle === 'bicicleta' ? '#059669' : '#0284c7';

    routingControl = L.Routing.control({
        waypoints: [
            L.latLng(start.lat, start.lng),
            L.latLng(end.lat, end.lng)
        ],
        routeWhileDragging: false,
        showAlternatives: true,
        lineOptions: {
            styles: [{ color: routeColor, weight: 6, opacity: 0.85 }]
        },
        altLineOptions: {
            styles: [{ color: '#94a3b8', weight: 4, opacity: 0.6 }]
        }
    }).addTo(map);

    routingControl.on('routesfound', function(e) {
        const routes = e.routes;
        const summary = routes[0].summary;
        const distanceKm = (summary.totalDistance / 1000);
        const co2Saved = (distanceKm * CO2_FACTOR_KG_PER_KM);
        const estimatedTariff = BASE_TARIFF_GS + Math.round(distanceKm * KM_TARIFF_GS);

        lastCalculatedRoute = {
            startName: start.name,
            endName: end.name,
            distanceKm,
            co2Saved,
            estimatedTariff,
            vehicle: selectedVehicle
        };

        // Actualizar UI
        document.getElementById('ecoDistance').textContent = `${distanceKm.toFixed(1)} km`;
        document.getElementById('ecoCo2').textContent = `${co2Saved.toFixed(2)} kg`;
        document.getElementById('ecoTariff').textContent = `${estimatedTariff.toLocaleString('es-PY')} ₲`;
        document.getElementById('ecoSummaryCard').classList.add('active');

        routesCount++;
        totalCo2Saved += co2Saved;
        totalKmTraveled += distanceKm;

        document.getElementById('routesCalculated').textContent = routesCount;
        document.getElementById('co2SavedMetric').textContent = `${totalCo2Saved.toFixed(1)} kg`;

        showToast(`Ruta ecológica calculada: ${distanceKm.toFixed(1)} km | CO₂ evitado: ${co2Saved.toFixed(2)} kg`, 'success');
    });

    if (window.innerWidth <= 768) {
        document.getElementById('sidebar').classList.remove('open');
    }
});

// Limpiar Ruta
document.getElementById('clearRoute').addEventListener('click', function() {
    if (routingControl) {
        map.removeControl(routingControl);
        routingControl = null;
        lastCalculatedRoute = null;
        document.getElementById('ecoSummaryCard').classList.remove('active');
        showToast('Ruta eliminada', 'info');
    }
});

// Guardar Ruta calculada en base de datos ecoruta_db y generar QR
document.getElementById('btnQrRoute').addEventListener('click', async function() {
    if (!lastCalculatedRoute) {
        showToast('Calcula una ruta primero', 'error');
        return;
    }

    const { startName, endName, distanceKm, co2Saved, estimatedTariff, vehicle } = lastCalculatedRoute;

    try {
        const res = await fetch(`${API_BASE}/orders`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                direccion_origen: startName,
                direccion_destino: endName,
                detalle_paquete: `Entrega en ${vehicle === 'bicicleta' ? 'Bicicleta' : 'Vehículo Eléctrico'}`,
                distancia_km: distanceKm,
                co2_ahorrado_kg: co2Saved,
                tarifa_ecologica: estimatedTariff,
                observaciones: `Ruta trazada en mapa (${distanceKm.toFixed(1)} km) - Concepción, PY`
            })
        });

        const data = await res.json();
        const pedidoId = data?.id_pedido || Math.floor(Math.random() * 1000);
        const qrData = `ECORUTA-PEDIDO#${pedidoId}-ORIGEN:${startName}-DESTINO:${endName}-CO2:${co2Saved.toFixed(2)}KG`;

        showQrModal(`Pedido #${pedidoId} guardado en ecoruta_db`, qrData, () => {
            showToast(`¡Pedido #${pedidoId} registrado exitosamente en la base de datos!`, 'success');
        });
    } catch {
        const qrData = `ECORUTA-RUTA-${Date.now().toString().slice(-4)}-${startName}➔${endName}`;
        showQrModal(`Ruta: ${startName} ➔ ${endName}`, qrData);
    }
});
document.getElementById('mobileToggle').addEventListener('click', function() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('open');
    this.innerHTML = sidebar.classList.contains('open')
        ? '<i class="fas fa-times"></i>'
        : '<i class="fas fa-bars"></i>';
});
renderLocations();
syncWithBackend();

const express = require('express');
const app = express();
const port = 8080;

const locations = [];

// Middleware para parsear JSON
app.use(express.json());

// Servir archivos estáticos
app.use(express.static('public'));

// Ruta principal
app.get('/', (req, res) => {
  res.sendFile(__dirname + '/public/index.html');
});

// API endpoint para obtener datos de mapa
app.get('/api/mapa', (req, res) => {
  res.json({
    message: 'Datos del mapa',
    locations: locations
  });
});

// API endpoint para agregar ubicación
app.post('/api/mapa', (req, res) => {
  const { lat, lng, name, description } = req.body;
  if (lat && lng && name) {
    locations.push({ lat: parseFloat(lat), lng: parseFloat(lng), name, description: description || '' });
    res.json({ message: 'Ubicación agregada', locations });
  } else {
    res.status(400).json({ error: 'Faltan datos: lat, lng, name' });
  }
});

app.listen(port, () => {
  console.log(`API de mapa corriendo en http://localhost:${port}`);
});
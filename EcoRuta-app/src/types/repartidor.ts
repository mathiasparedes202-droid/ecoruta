export type Pedido = {
  id_pedido: number;
  id_comercio: number;
  razon_social?: string;
  id_repartidor?: number | null;
  id_estado: number;
  nombre_estado?: string;
  direccion_origen: string;
  direccion_destino: string;
  detalle_paquete: string;
  peso_kg?: number;
  distancia_km?: number;
  tarifa_ecologica?: number;
  co2_ahorrado_kg?: number;
  observaciones?: string;
  fecha_solicitud?: string;
  fecha_asignacion?: string;
  fecha_entrega?: string;
  confirmacion_tipo?: string;
  confirmacion_datos?: string;
  origen_coords?: [number, number];
  destino_coords?: [number, number];
  metodo_pago?: 'efectivo' | 'transferencia' | 'mixto';
  pagado?: number | boolean;
  fecha_pago?: string | null;
  monto_efectivo?: number;
  monto_transferencia?: number;
  monto_recibido?: number;
  vuelto?: number;
  comprobante_transferencia?: string;
};

export type RepartidorUser = {
  id: string | number;
  id_usuario: number;
  id_repartidor?: number | null;
  email: string;
  displayName: string;
  rol: string;
  tipo_vehiculo: 'Bicicleta' | 'Vehículo Eléctrico';
  matricula: string;
  disponible: boolean;
  avatar?: string;
};

export type Notificacion = {
  id_notificacion: number;
  id_usuario_destino: number;
  tipo: string;
  titulo: string;
  mensaje: string;
  id_pedido?: number | null;
  leida: number;
  fecha: string;
};

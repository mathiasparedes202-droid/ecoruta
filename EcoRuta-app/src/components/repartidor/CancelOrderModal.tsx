import { FormEvent, useState } from 'react';
import { FaBan } from 'react-icons/fa';
import { Pedido } from '../../types/repartidor';

interface Props {
  order: Pedido;
  onClose: () => void;
  onConfirm: (order: Pedido, motivo: string) => Promise<void>;
}

const QUICK_REASONS = [
  'Receptor no encontrado en la dirección',
  'Destino incorrecto o no válido',
  'Paquete dañado o incompleto',
  'Comercio no entregó el paquete',
  'Condiciones climáticas impidieron la entrega',
];

const CancelOrderModal = ({ order, onClose, onConfirm }: Props) => {
  const [motivo, setMotivo] = useState('');
  const [saving, setSaving] = useState(false);

  const submit = async (event: FormEvent) => {
    event.preventDefault();
    const final = motivo.trim();
    if (final.length < 5) return;
    setSaving(true);
    try {
      await onConfirm(order, final);
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="delivery-modal-backdrop" role="presentation">
      <form className="delivery-modal cancel-modal" onSubmit={submit}>
        <button type="button" className="modal-close" onClick={onClose} aria-label="Cerrar">×</button>

        <span className="confirmation-icon confirmation-icon--danger">
          <FaBan />
        </span>

        <p className="eyebrow">CANCELACIÓN DE PEDIDO</p>
        <h2>¿Cancelar el pedido #{order.id_pedido}?</h2>
        <p className="modal-description">
          Contanos el motivo. El administrador de EcoRuta recibirá una notificación con tu explicación.
        </p>

        <label className="field-label" htmlFor="quick-reason">
          Motivo más común
          <select
            id="quick-reason"
            value={QUICK_REASONS.includes(motivo) ? motivo : ''}
            onChange={(event) => { if (event.target.value) setMotivo(event.target.value); }}
          >
            <option value="">Elegí una opción…</option>
            {QUICK_REASONS.map((reason) => (
              <option key={reason} value={reason}>{reason}</option>
            ))}
          </select>
        </label>

        <label className="field-label">
          Detalle del motivo <em>(mínimo 5 caracteres)</em>
          <textarea
            required
            minLength={5}
            value={motivo}
            onChange={(event) => setMotivo(event.target.value)}
            placeholder="Explicá brevemente qué pasó"
            rows={3}
            autoFocus={!QUICK_REASONS.includes(motivo)}
          />
          {motivo.trim().length > 0 && motivo.trim().length < 5 && (
            <small className="field-hint">Escribí al menos 5 caracteres.</small>
          )}
        </label>

        <div className="modal-actions">
          <button type="button" onClick={onClose}>Volver</button>
          <button
            type="submit"
            className="cancel-confirm-button"
            disabled={saving || motivo.trim().length < 5}
          >
            {saving ? 'Cancelando…' : 'Confirmar cancelación'}
          </button>
        </div>
      </form>
    </div>
  );
};

export default CancelOrderModal;
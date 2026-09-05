import { ChangeEvent, FormEvent, useRef, useState } from 'react';
import { FaCamera, FaCheckCircle, FaPen, FaQrcode, FaUpload } from 'react-icons/fa';
import { decodeQrFromDataUrl, isValidOrderQr } from '../../lib/qr';
import { Pedido } from '../../types/repartidor';
import ScanCam from './ScanCam';

interface Props {
  order: Pedido;
  onClose: () => void;
  onConfirm: (order: Pedido, note: string, cash?: { montoRecibido?: number }) => Promise<void>;
}

const fileToDataUrl = (file: File) => new Promise<string>((resolve, reject) => {
  const reader = new FileReader();
  reader.onload = () => resolve(String(reader.result || ''));
  reader.onerror = () => reject(new Error('No pudimos leer esa imagen como QR.'));
  reader.readAsDataURL(file);
});

const DeliveryConfirmationModal = ({ order, onClose, onConfirm }: Props) => {
  const [method, setMethod] = useState<'qr' | 'signature'>('qr');
  const [code, setCode] = useState('');
  const [note, setNote] = useState('');
  const [signer, setSigner] = useState('');
  const [saving, setSaving] = useState(false);
  const [qrError, setQrError] = useState('');
  const [isReadingQr, setIsReadingQr] = useState(false);
  const [isScannerOpen, setIsScannerOpen] = useState(false);
  const [montoRecibido, setMontoRecibido] = useState('');
  const [cashError, setCashError] = useState('');
  const formRef = useRef<HTMLFormElement>(null);
  const fileInputRef = useRef<HTMLInputElement | null>(null);

  const cashTarget =
    order.metodo_pago === 'mixto' ? Number(order.monto_efectivo || 0) : Number(order.tarifa_ecologica || 0);
  const needsCash = !order.pagado && (order.metodo_pago === 'efectivo' || order.metodo_pago === 'mixto');
  const recibido = Number(montoRecibido);
  const calcVuelto = (received: number) => (received > 0 ? Math.max(0, received - cashTarget) : 0);

  const handleScannedCode = (scannedCode: string) => {
    if (!isValidOrderQr(scannedCode, order.id_pedido)) {
      setQrError(`El código QR leído no corresponde al pedido #${order.id_pedido}. Verifica el QR correcto.`);
      return;
    }
    setCode(scannedCode);
    setQrError('');
    setIsScannerOpen(false);
  };

  const handleQrUpload = async (event: ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    const input = event.target;
    if (!file) {
      input.value = '';
      return;
    }

    setIsReadingQr(true);
    setQrError('');

    try {
      const dataUrl = await fileToDataUrl(file);
      const decoded = await decodeQrFromDataUrl(dataUrl);
      if (!isValidOrderQr(decoded, order.id_pedido)) {
        setQrError(`El código QR leído no corresponde al pedido #${order.id_pedido}. Verifica el QR correcto.`);
        return;
      }
      setCode(decoded);
      setMethod('qr');
      setQrError('');
    } catch (error) {
      setQrError(error instanceof Error ? error.message : 'No pudimos leer el QR de esa imagen.');
    } finally {
      input.value = '';
      setIsReadingQr(false);
    }
  };

  const submit = async (event: FormEvent) => {
    event.preventDefault();

    if (method === 'qr' && !code.trim()) {
      formRef.current?.reportValidity();
      return;
    }

    if (method === 'qr' && !isValidOrderQr(code.trim(), order.id_pedido)) {
      setQrError(`El código QR no pertenece al pedido #${order.id_pedido}.`);
      return;
    }

    if (method === 'signature' && !signer.trim()) {
      formRef.current?.reportValidity();
      return;
    }

    setSaving(true);
    const proof =
      method === 'qr'
        ? `Confirmación QR: ${code.trim()}`
        : `Firma digital registrada a nombre de: ${signer.trim()}`;

    if (needsCash) {
      if (!(recibido > 0) || recibido < cashTarget) {
        setCashError(`El efectivo recibido debe ser al menos ${cashTarget.toLocaleString('es-PY')} ₲.`);
        setSaving(false);
        return;
      }
      setCashError('');
    }

    try {
      await onConfirm(
        order,
        `${proof}${note.trim() ? `. Observación: ${note.trim()}` : ''}`,
        needsCash ? { montoRecibido: recibido } : {}
      );
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="delivery-modal-backdrop" role="presentation">
      <form ref={formRef} className="delivery-modal" onSubmit={submit}>
        <button type="button" className="modal-close" onClick={onClose} aria-label="Cerrar">
          ×
        </button>

        <span className="confirmation-icon">
          <FaCheckCircle />
        </span>

        <p className="eyebrow">CONFIRMACIÓN DE ENTREGA</p>
        <h2>¿Pedido #{order.id_pedido} entregado?</h2>
        <p className="modal-description">
          Confirmá la entrega y, si querés, dejale una notita al comercio.
        </p>

        <div
          className={`payment-info ${order.pagado ? 'payment-info--paid' : order.metodo_pago === 'transferencia' ? 'payment-info--pending' : 'payment-info--cash'}`}
        >
          {order.pagado
            ? <>Pedido ya pagado ({(order.tarifa_ecologica || 0).toLocaleString('es-PY')} ₲). No cobres al entregar.</>
            : order.metodo_pago === 'transferencia'
              ? <>La transferencia de {(order.tarifa_ecologica || 0).toLocaleString('es-PY')} ₲ aún no fue confirmada por el comercio. No cobres en efectivo.</>
              : order.metodo_pago === 'mixto'
                ? <>Cobrá {(order.monto_efectivo || 0).toLocaleString('es-PY')} ₲ en efectivo (parte del pago mixto; la transferencia ya está hecha).</>
                : <>Cobrá {(order.tarifa_ecologica || 0).toLocaleString('es-PY')} ₲ en efectivo al cliente al entregar.</>}
        </div>

        {needsCash && (
          <label className="field-label">
            {order.metodo_pago === 'mixto' ? `Efectivo recibido (mín. ${cashTarget.toLocaleString('es-PY')} ₲)` : 'Cobro en efectivo'}
            <input
              type="number"
              min="0"
              step="500"
              value={montoRecibido}
              onChange={event => {
                setMontoRecibido(event.target.value);
                setCashError('');
              }}
              placeholder={`${cashTarget.toLocaleString('es-PY')} ₲`}
            />
            {calcVuelto(recibido) > 0 && (
              <span className="field-hint">
                Vuelto a entregar al cliente: {calcVuelto(recibido).toLocaleString('es-PY')} ₲
              </span>
            )}
          </label>
        )}

        {cashError && <p className="qr-error">{cashError}</p>}

        <div className="proof-switch">
          <button
            type="button"
            className={method === 'qr' ? 'active' : ''}
            onClick={() => setMethod('qr')}
          >
            <FaQrcode /> Código QR
          </button>
          <button
            type="button"
            className={method === 'signature' ? 'active' : ''}
            onClick={() => setMethod('signature')}
          >
            <FaPen /> Firma digital
          </button>
        </div>

        {method === 'qr' ? (
          <>
            {isScannerOpen ? (
              <ScanCam onScan={handleScannedCode} onClose={() => setIsScannerOpen(false)} />
            ) : (
              <div className="qr-upload-box qr-upload-box--row">
                <button type="button" className="scan-live-button" onClick={() => setIsScannerOpen(true)} disabled={isReadingQr}>
                  <FaCamera /> Escanear en vivo
                </button>
                <button type="button" className="qr-upload-button" onClick={() => fileInputRef.current?.click()} disabled={isReadingQr}>
                  <FaUpload /> {isReadingQr ? 'Leyendo QR…' : 'Subir foto'}
                </button>
                <button
                  type="button"
                  className="qr-manual-button"
                  onClick={() => {
                    setMethod('qr');
                    setCode('');
                    setQrError('');
                  }}
                >
                  Ingresar manual
                </button>
              </div>
            )}

            <label className="field-label">
              Código recibido
              <input
                required
                value={code}
                onChange={event => {
                  setCode(event.target.value);
                  setQrError('');
                }}
                placeholder="Escanea con tu móvil o pega el valor del QR"
                autoFocus
              />
            </label>

            {qrError && <p className="qr-error">{qrError}</p>}
          </>
        ) : (
          <label className="field-label">
            Nombre de quien recibe
            <input
              required
              value={signer}
              onChange={event => setSigner(event.target.value)}
              placeholder="Escribe el nombre de la persona"
              autoFocus
            />
          </label>
        )}

        <label className="field-label">
          Nota para el comercio <em>(opcional)</em>
          <textarea
            value={note}
            onChange={event => setNote(event.target.value)}
            placeholder="Ej.: Se entregó en portería, lo recibió María."
            rows={3}
          />
        </label>

        <div className="modal-actions">
          <button type="button" onClick={onClose}>
            Volver
          </button>
          <button
              type="submit"
              className="confirm-delivery-button"
              disabled={saving}
            >
              {saving ? 'Guardando…' : 'Confirmar entrega'}
            </button>
        </div>
      </form>
      <input
        ref={fileInputRef}
        type="file"
        accept="image/*"
        capture="environment"
        onChange={handleQrUpload}
        className="visually-hidden"
        tabIndex={-1}
      />
    </div>
  );
};

export default DeliveryConfirmationModal;

import { useCallback, useEffect, useRef, useState } from 'react';
import { FaBolt, FaCamera, FaRedo, FaSpinner } from 'react-icons/fa';
import { decodeQrFromVideoFrame } from '../../lib/qr';

interface Props {
  onScan: (code: string) => void;
  onClose: () => void;
}

enum CamState {
  Requesting = 'requesting',
  Running = 'running',
  Error = 'error',
  Denied = 'denied',
}

const ScanCam = ({ onScan, onClose }: Props) => {
  const videoRef = useRef<HTMLVideoElement>(null);
  const streamRef = useRef<MediaStream | null>(null);
  const tickRef = useRef<number | null>(null);
  const lockedRef = useRef(false);
  const [state, setState] = useState<CamState>(CamState.Requesting);
  const [errorMsg, setErrorMsg] = useState('');
  const [flashOn, setFlashOn] = useState(false);
  const [scanSuccess, setScanSuccess] = useState(false);

  const stop = useCallback(() => {
    if (tickRef.current) {
      cancelAnimationFrame(tickRef.current);
      tickRef.current = null;
    }
    streamRef.current?.getTracks().forEach((t) => t.stop());
    streamRef.current = null;
  }, []);

  const requestCamera = useCallback(async () => {
    setState(CamState.Requesting);
    setErrorMsg('');
    setScanSuccess(false);
    try {
      if (!navigator.mediaDevices?.getUserMedia) {
        setState(CamState.Error);
        setErrorMsg('Tu navegador no soporta la cámara en vivo. Podés subir una foto del QR igualmente.');
        return;
      }
      const stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 720 } },
        audio: false,
      });
      streamRef.current = stream;
      if (videoRef.current) {
        videoRef.current.srcObject = stream;
        await videoRef.current.play().catch(() => undefined);
      }
      setState(CamState.Running);
    } catch (err) {
      const name = (err as DOMException)?.name ?? '';
      if (name === 'NotAllowedError') {
        setState(CamState.Denied);
        setErrorMsg('Necesitás permitir el acceso a la cámara para escanear en vivo.');
      } else if (name === 'NotFoundError') {
        setState(CamState.Error);
        setErrorMsg('No se encontró ninguna cámara en este dispositivo.');
      } else {
        setState(CamState.Error);
        setErrorMsg('No pudimos acceder a la cámara. Permití el acceso e intentá de nuevo.');
      }
    }
  }, []);

  const toggleFlash = useCallback(() => {
    const track = streamRef.current?.getVideoTracks()[0];
    if (!track) return;

    const capabilities = track.getCapabilities?.();
    const hasFlash = !!capabilities && 'torch' in capabilities;
    if (!hasFlash) return;

    const nextState = !flashOn;
    track.applyConstraints({
      advanced: [{ torch: nextState } as any],
    } as any).catch(() => undefined);
    setFlashOn(nextState);
  }, [flashOn]);

  const handleSuccessfulScan = useCallback((code: string) => {
    if ('vibrate' in navigator) {
      navigator.vibrate?.([60, 40, 80]);
    }
    setScanSuccess(true);
    setTimeout(() => onScan(code), 240);
  }, [onScan]);

  // Lectura del frame en vivo hasta detectar un QR
  useEffect(() => {
    if (state !== CamState.Running) return;
    lockedRef.current = false;

    const tick = () => {
      const video = videoRef.current;
      if (video && !lockedRef.current) {
        try {
          const code = decodeQrFromVideoFrame(video);
          if (code) {
            lockedRef.current = true;
            stop();
            handleSuccessfulScan(code);
            return;
          }
        } catch {
          // frame inválido, continuar
        }
      }
      tickRef.current = requestAnimationFrame(tick);
    };
    tickRef.current = requestAnimationFrame(tick);

    return () => {
      if (tickRef.current) cancelAnimationFrame(tickRef.current);
    };
  }, [state, stop, handleSuccessfulScan]);

  useEffect(() => () => stop(), [stop]);

  return (
    <div className="scan-cam" role="dialog" aria-modal="true" aria-label="Escaner QR en vivo">
      <div className="scan-cam__preview">
        <video ref={videoRef} playsInline muted className={state !== CamState.Running ? 'is-hidden' : ''} />

        {state === CamState.Requesting && (
          <div className="scan-cam__overlay">
            <FaSpinner className="spin" />
            <p>Encendiendo la cámara…</p>
          </div>
        )}

        {(state === CamState.Error || state === CamState.Denied) && (
          <div className="scan-cam__overlay">
            <FaCamera />
            <p>{errorMsg}</p>
          </div>
        )}

        {state === CamState.Running && (
          <>
            <div className="scan-cam__frame">
              <i />
              <i />
              <i />
              <i />
            </div>
            <p className="scan-cam__hint">{scanSuccess ? 'QR detectado' : 'Apunta la cámara al código QR'}</p>
          </>
        )}
      </div>

      <div className="scan-cam__actions">
        {state === CamState.Running && (
          <button type="button" className="scan-cam__flash" onClick={toggleFlash} aria-label="Encender flash">
            <FaBolt /> {flashOn ? 'Flash on' : 'Flash'}
          </button>
        )}
        {(state === CamState.Error || state === CamState.Denied) && (
          <button className="turn-control__btn --start" onClick={requestCamera}>
            <FaRedo /> Reintentar
          </button>
        )}
        <button className="turn-control__btn --pause" onClick={() => { stop(); onClose(); }}>
          Cancelar
        </button>
      </div>
    </div>
  );
};

export default ScanCam;
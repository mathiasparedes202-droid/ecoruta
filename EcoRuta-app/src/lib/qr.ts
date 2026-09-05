import QRCode from 'qrcode';
import jsQR from 'jsqr';

export const buildOrderQrPayload = ({
  id_pedido,
  id_repartidor,
}: {
  id_pedido: number;
  id_repartidor?: number | null;
}) => {
  const params = new URLSearchParams({
    pedido: String(id_pedido),
    repartidor: String(id_repartidor ?? 0),
    app: 'ecoruta',
  });

  return `ecoruta:pedido:${id_pedido}?${params.toString()}`;
};

export const generateQrDataUrl = async (value: string): Promise<string> => {
  const dataUrl = await QRCode.toDataURL(value, {
    margin: 2,
    width: 320,
    color: {
      dark: '#0D3B2A',
      light: '#F8FFF9',
    },
  });

  return dataUrl;
};

export const parseOrderQrPayload = (raw: string): { idPedido: number; idRepartidor: number } | null => {
  const match = raw.match(/^ecoruta:pedido:(\d+)\?/);
  if (!match) {
    return null;
  }

  let idRepartidor = 0;
  try {
    const params = new URLSearchParams(raw.split('?')[1] ?? '');
    const repartidor = params.get('repartidor');
    if (repartidor && /^\d+$/.test(repartidor)) {
      idRepartidor = Number(repartidor);
    }
  } catch {
    idRepartidor = 0;
  }

  return { idPedido: Number(match[1]), idRepartidor };
};

export const isValidOrderQr = (raw: string, idPedido: number | string): boolean => {
  const parsed = parseOrderQrPayload(raw);
  return parsed !== null && parsed.idPedido === Number(idPedido);
};

export const decodeQrFromDataUrl = async (dataUrl: string): Promise<string> => {
  const image = new Image();
  image.src = dataUrl;

  await new Promise<void>((resolve) => {
    if (image.complete && image.naturalWidth > 0) {
      resolve();
      return;
    }
    image.onload = () => resolve();
    image.onerror = () => resolve();
  });

  if (!image.complete || image.naturalWidth === 0) {
    throw new Error('No se pudo cargar la imagen del QR.');
  }

  return decodeFromImage(image);
};

const decodeFromImage = (image: HTMLImageElement): string => {
  const { naturalWidth: nw } = image;
  const widths = [400, 300, 200, 600];

  const attempts = new Set<number>();
  for (const w of widths) {
    if (w < nw) {
      attempts.add(w);
    }
  }
  attempts.add(Math.min(nw, 700));

  for (const width of attempts) {
    const code = tryDecodeAt(image, width);
    if (code) {
      return code;
    }
  }

  throw new Error('No se pudo leer el código QR de la imagen. Asegúrate de que el QR esté bien encuadrado e iluminado.');
};

const tryDecodeAt = (image: HTMLImageElement, targetWidth: number): string | null => {
  const scale = targetWidth / image.naturalWidth;
  const width = targetWidth;
  const height = Math.max(1, Math.round(image.naturalHeight * scale));

  const canvas = document.createElement('canvas');
  canvas.width = width;
  canvas.height = height;
  const ctx = canvas.getContext('2d', { willReadFrequently: true });
  if (!ctx) {
    return null;
  }

  ctx.fillStyle = '#FFFFFF';
  ctx.fillRect(0, 0, width, height);
  ctx.imageSmoothingEnabled = true;
  ctx.imageSmoothingQuality = 'high';
  ctx.drawImage(image, 0, 0, width, height);

  const imageData = ctx.getImageData(0, 0, width, height);
  const code = jsQR(imageData.data, width, height, {
    inversionAttempts: 'attemptBoth',
  });

  if (code) {
    return code.data;
  }

  // Segundo intento: normalizar contraste (binarizar) para fotos oscuras o con ruido.
  const binary = binarize(imageData);
  const code2 = jsQR(binary.data, width, height, {
    inversionAttempts: 'dontInvert',
  });
  if (code2) {
    return code2.data;
  }

  return null;
};

const binarize = (imageData: ImageData): ImageData => {
  const { data, width, height } = imageData;
  const out = new ImageData(width, height);
  const outData = out.data;

  for (let i = 0; i < data.length; i += 4) {
    const r = data[i];
    const g = data[i + 1];
    const b = data[i + 2];
    const luminance = 0.299 * r + 0.587 * g + 0.114 * b;
    const v = luminance > 128 ? 255 : 0;
    outData[i] = v;
    outData[i + 1] = v;
    outData[i + 2] = v;
    outData[i + 3] = 255;
  }

  return out;
};

/**
 * Decodifica un QR directamente desde un frame de video sin tomar una foto.
 * Recibe el elemento <video> y dibuja cada frame a un canvas para analizarlo
 * con jsQR en tiempo real.
 */
export const decodeQrFromVideoFrame = (
  video: HTMLVideoElement,
  maxWidth = 420
): string | null => {
  if (!video.videoWidth || !video.videoHeight) return null;

  const scale = Math.min(1, maxWidth / video.videoWidth);
  const width = Math.max(1, Math.round(video.videoWidth * scale));
  const height = Math.max(1, Math.round(video.videoHeight * scale));

  const canvas = document.createElement('canvas');
  canvas.width = width;
  canvas.height = height;
  const ctx = canvas.getContext('2d', { willReadFrequently: true });
  if (!ctx) return null;

  ctx.drawImage(video, 0, 0, width, height);
  const imageData = ctx.getImageData(0, 0, width, height);

  const code = jsQR(imageData.data, width, height, {
    inversionAttempts: 'attemptBoth',
  });
  if (code) return code.data;

  const binary = binarize(imageData);
  const code2 = jsQR(binary.data, width, height, {
    inversionAttempts: 'dontInvert',
  });
  if (code2) return code2.data;

  return null;
};

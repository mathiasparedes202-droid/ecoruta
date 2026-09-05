import { Image as NodeImage, createCanvas, Canvas as NodeCanvas } from 'canvas';

(globalThis as unknown as { Image: typeof NodeImage }).Image = NodeImage;

const originalCreateElement = document.createElement.bind(document);
(document as unknown as { createElement: typeof document.createElement }).createElement = (
  tagName: string,
  options?: ElementCreationOptions,
) => {
  if (tagName.toLowerCase() === 'canvas') {
    const nodeCanvas = createCanvas(0, 0);
    const element = originalCreateElement('canvas', options);
    const getContext = () => (nodeCanvas as NodeCanvas).getContext('2d');
    Object.defineProperties(element, {
      getContext: { value: getContext },
      width: { get: () => nodeCanvas.width, set: (v: number) => void (nodeCanvas.width = v) },
      height: { get: () => nodeCanvas.height, set: (v: number) => void (nodeCanvas.height = v) },
    });
    return element;
  }
  return originalCreateElement(tagName, options);
};
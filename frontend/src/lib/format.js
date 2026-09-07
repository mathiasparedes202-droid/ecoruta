export function formatGs(value) {
  return Math.round(Number(value || 0)).toLocaleString('es-PY');
}
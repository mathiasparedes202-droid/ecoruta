import Swal from 'sweetalert2';

const theme = {
  confirmButtonColor: '#176b52',
  cancelButtonColor: '#94a3b8',
  background: '#ffffff',
  color: '#0f3d33',
};

export function toastSuccess(title, text = '') {
  return Swal.fire({
    ...theme,
    toast: true,
    position: 'top-end',
    icon: 'success',
    title,
    text,
    showConfirmButton: false,
    timer: 2500,
    timerProgressBar: true,
  });
}

export function toastError(title, text = '') {
  return Swal.fire({
    ...theme,
    toast: true,
    position: 'top-end',
    icon: 'error',
    title,
    text,
    showConfirmButton: false,
    timer: 4000,
    timerProgressBar: true,
  });
}

export function toastWarning(title, text = '') {
  return Swal.fire({
    ...theme,
    toast: true,
    position: 'top-end',
    icon: 'warning',
    title,
    text,
    showConfirmButton: false,
    timer: 3500,
    timerProgressBar: true,
  });
}

export function alertInfo(title, text = '') {
  return Swal.fire({
    ...theme,
    title,
    text,
    icon: 'info',
    confirmButtonText: 'Entendido',
  });
}

export function confirmAction(title, text = '', confirmText = 'Sí, continuar') {
  return Swal.fire({
    ...theme,
    title,
    text,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: confirmText,
    cancelButtonText: 'Cancelar',
    reverseButtons: true,
  }).then((result) => result.isConfirmed);
}
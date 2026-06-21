<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        background: '#ffffff',
        color: '#333333',
        customClass: {
            popup: 'shadow-sm border'
        },
        didOpen: (toast) => {
            toast.onmouseenter = Swal.stopTimer;
            toast.onmouseleave = Swal.resumeTimer;
        }
    });
    function showAlert(status, message) {
        const validStatus = ['success', 'error', 'warning', 'info'].includes(status) ? status : 'info';
        const finalMessage = message || (status === 'success' ? 'Berhasil diproses!' : 'Terjadi kesalahan.');

        Toast.fire({
            icon: validStatus,
            title: finalMessage
        });
    }
</script>
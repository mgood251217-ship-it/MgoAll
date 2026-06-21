// Ambil token setelah service worker sudah terdaftar
navigator.serviceWorker.ready.then((registration) => {
  getToken(messaging, {
    vapidKey: 'VAPID_KEY_KAMU',
    serviceWorkerRegistration: registration
  }).then((currentToken) => {
    if (currentToken) {
      console.log('Token FCM:', currentToken);

      // Kirim token ke server untuk disimpan
      fetch('/admin/api/save_token.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ token: currentToken })
      })
      .then(res => res.json())
      .then(data => console.log('Simpan token:', data))
      .catch(console.error);
    } else {
      console.warn('Tidak dapat token FCM');
    }
  });
});

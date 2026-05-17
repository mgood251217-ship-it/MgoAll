<link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/img/title_icon.webp">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/content.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/dark_mode.css">
<script src="<?= BASE_URL ?>/assets/js/bootstrap.bundle.min.js"></script>
<?php if (isset($username) && ($username == 'zannia' || $username == 'vikialvian')) { ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/pink_mode.css">
<?php } ?>
<script>
// document.addEventListener('contextmenu', function(e) {
//     if (e.target.nodeName === "IMG") {
//         e.preventDefault();
//         alert("Gambar diproteksi!"); // Opsional: Beri pesan peringatan
//     }
// }, false);

// // Cegah juga drag gambar
// document.addEventListener('dragstart', function(e) {
//     if (e.target.nodeName === "IMG") {
//         e.preventDefault();
//     }
// }, false);
</script>
<style>
    body {
        font-family: 'Open Sans', sans-serif !important;
    }
</style>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/footer.css">
<style>
<?php if (isset($username) && ($username == 'zannia' || $username == 'vikialvian')) { ?>
footer.dark-mode {
  background-color:#ffb3db !important;
  color: #e0e0e0 !important;
  padding: 0.5rem 1rem;
  font-size: 13px;
  text-align: center;
  position: fixed;
  bottom: 0;
  left: 0;
  width: 100%;
  z-index: 1000;
}
<?php } ?>
</style>
<footer class="<?= (isset($mode) && $mode === 1) ? 'dark-mode' : 'light-mode' ?>">
  <small>&copy; <?= date('Y') ?> Mgo. All rights reserved.</small>
  
</footer>



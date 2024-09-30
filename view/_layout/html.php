<?php
/**
 * OpenTHC HTML Layout
 *
 * SPDX-License-Identifier: GPL-3.0-only
 */

header('content-type: text/html; charset=utf-8', true);

$body_class_list = [];
$m1_mode = preg_match('/^(open|mini|shut)$/', $_COOKIE['m1'], $m) ? $m[1] : 'open';
$body_class_list[] = sprintf('m1-%s', $m1_mode);

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="initial-scale=1, user-scalable=yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="theme-color" content="#069420">
<link rel="stylesheet" href="/vendor/fontawesome/css/all.min.css">
<link rel="stylesheet" href="/vendor/bootstrap/bootstrap.min.css">
<!-- <link rel="stylesheet" href="/vendor/datatables/css/dataTables.bootstrap4.min.css" integrity="sha256-F+DaKAClQut87heMIC6oThARMuWne8+WzxIDT7jXuPA=" crossorigin="anonymous"> -->
<!-- <link rel="stylesheet" href="https://cdn.openthc.com/css/www/0.0.2/main.css" crossorigin="anonymous"> -->
<!-- <link rel="stylesheet" href="https://cdn.openthc.com/css/www/0.0.2/menu-tlr.css" crossorigin="anonymous"> -->
<link rel="stylesheet" href="/css/main.css">
<title><?= h(strip_tags($this->data['Page']['title'])) ?></title>
</head>
<body class="<?= implode(' ', $body_class_list) ?>" data-menu-left-mode="<?= $m1_mode ?>">
<?= $this->block('body-head.php') ?>
<?= $this->block('session-flash.php') ?>
<div class="container-fluid" style="min-height:80vh;">
<?= $this->body ?>
</div>

<script src="/vendor/lodash/lodash.min.js"></script>
<script src="/vendor/jquery/jquery.min.js"></script>
<script src="/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<script src="/vendor/qrcodejs/qrcode.min.js"></script>
<script src="/vendor/chart.js/chart.min.js"></script>
<!-- <script src="https://app.openthc.com/js/app.js"></script> -->
<script src="/js/pos.js"></script>
<script>
$(function() {

	//$('#exec-full-screen').on('click', function() {
	//      var el = document.documentElement;
	//      var rfs = el.requestFullScreen
	//                      || el.webkitRequestFullScreen
	//                      || el.mozRequestFullScreen;
	//      rfs.call(el);
	//});

	// $('#pos-terminal-id').on('click', function() {
	// 	// Weed.bodyDim();
	// 	//$.get('/pos/ajax/tid', function() {
	// 	var html = '';
	// 	html+= '<div style="background: #fdfdfd; border:4px solid #000; height: 400px; margin: 0px auto; width: 400px;">';
	// 	// html+= '<img src="http://chart.apis.google.com/chart?cht=qr&chs=400x400&chl=<?= rawurlencode('https://weedtraqr.com/pos/front?t=' . $_SESSION['pos-terminal-id']) ?>&chld=H|0">';
	// 	html+= '</div>';
	// 	Weed.modal(html);
	// });

	// $('#pos-sign-out').on('click', function() {
	// 	window.location = '/auth/pin';
	// });

});
</script>
<?= $this->foot_script ?>
</body>
</html>

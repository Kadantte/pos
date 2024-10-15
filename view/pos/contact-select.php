<?php
/**
 * Main Terminal View v2018
 *
 * SPDX-License-Identifier: GPL-3.0-only
 */

$this->layout_file = sprintf('%s/view/_layout/html-pos.php', APP_ROOT);

$head = sprintf('<h1>%s</h1>', _('Check In:'));

$govt_id_html = __h($_SESSION['Cart']['contact-search']);

$body = <<<HTML

<div class="alert alert-secondary mb-4">Scan the client's identification card or input their identification details.</div>

<div class="input-group input-group-lg mb-4">

	<div class="input-group-text">Transaction:</div>
	<select class="form-select form-select-lg" id="pos-cart-type" name="pos-cart-type">
		<option value="REC">Recreational</option>
		<option value="MED">Medical</option>
	</select>

</div>

<div class="input-group input-group-lg mb-4">

	<div class="input-group-text">Identification:</div>

	<input autocomplete="off" autofocus
		class="form-control form-control-lg"
		id="client-contact-govt-id"
		name="client-contact-govt-id"
		placeholder="State / ID"
		type="text"
		value="{$govt_id_html}">

	<input id="client-contact-scanned-id" name="client-contact-scanned-id" type="hidden" value="">
	<input id="client-contact-id" name="client-contact-id" type="hidden" value="">
	<input id="client-contact-name" name="client-contact-name" type="hidden">
	<input id="client-contact-dob" name="client-contact-dob" type="hidden">

	<button class="btn btn-success"
		id="client-contact-govt-id-scanner"
		title="Use the Scanner"
		type="button"><i class="fas fa-qrcode"></i></button>

	<button class="btn btn-secondary pos-camera-input"
		data-camera-callback=""
		x-id="client-contact-dob-camera"
		title="Use the Device Camera Scanner"
		type="button"><i class="fas fa-camera"></i></button>
</div>

HTML;

$foot = [];
$foot[] = '<div class="d-flex justify-content-between">';
$foot[] = '<div>';

$foot[] = '<button class="btn btn-lg btn-primary" name="a" type="submit" value="client-contact-update">Next <i class="fas fa-arrow-right"></i></button>';

if ($_SESSION['Cart']['contact-push']) {
	unset($_SESSION['Cart']['contact-push']);
	$foot[] = '<button class="btn btn-lg btn-outline-primary" name="a" type="submit" value="client-contact-update-force">Use Anyway <i class="fas fa-arrow-right"></i></button>';
}

$foot[] = '<button class="btn btn-lg btn-secondary" name="a" type="submit" value="client-contact-skip">Skip </button>';

$foot[] = '</div>';
$foot[] = '<div>';
$foot[] ='<button class="btn btn-lg btn-warning" id="btn-form-reset" type="reset" value="client-contact-reopen">Reset <i class="fas fa-ban"></i></button>';
$foot[] = '</div>';
$foot[] = '</div>';
$foot = implode(' ', $foot);

?>

<form action="/pos/checkout/open" autocomplete="off" method="post">
<div class="container mt-4">
<?= _draw_html_card($head, $body, $foot) ?>
</div>
</form>

<script>
var $govInput;
var scan_buffer = [];
var text_buffer = [];

function _checkout_open_reopen()
{
	scan_buffer = [];
	text_buffer = [];
	if ($govInput) {
		$govInput.attr('readonly', false);
		$govInput.val('');
		$govInput.focus();
	}
}

$(function() {

	$govInput = $('#client-contact-govt-id');

	$govInput.on('focus', function() {
		$('#client-contact-govt-id-scanner').addClass('btn-success').removeClass('btn-outline-secondary');
	});

	$govInput.on('blur', function() {
		$('#client-contact-govt-id-scanner').addClass('btn-outline-secondary').removeClass('btn-success');
	});

	// Scanner Keydown Handler
	var scan_time = 0;
	var scan_skip_next = false;
	// $govInput.on('keydown', function(e) {

	// 	console.log(`${e.key} (${e.code}); skip:${scan_skip_next}`);
	// 	// if ( ! scan_time) {
	// 	// 	// scan_time = now()
	// 	// }

	// 	if (scan_skip_next) {
	// 		e.preventDefault();
	// 		scan_skip_next = false;
	// 		return false;
	// 	}

	// 	switch (e.key) {
	// 		case 'Backspace':
	// 		case 'Delete':
	// 			text_buffer.pop();
	// 			return true;
	// 		case 'Control':
	// 			e.preventDefault();
	// 			scan_skip_next = true;
	// 			break;
	// 		case 'Enter':
	// 		case 'Meta':
	// 		// case 'Shift':
	// 			e.preventDefault();
	// 			break;
	// 	}

	// 	var val = e.key;
	// 	switch (val) {
	// 		case 'Control':
	// 		case 'Enter':
	// 		case 'Meta':
	// 		case 'Shift':
	// 			val = `[${val}]`;
	// 			break;
	// 	}

	// 	// if (e.key.length == '1') {
	// 	scan_buffer.push(val);
	// 	// }

	// 	if (val.match(/^[\w\s\/\-\+]$/)) {
	// 		text_buffer.push(val);
	// 		$govInput.val( text_buffer.join('') );
	// 	}

	// 	return false;

	// });

	$govInput.on('keyup', _.debounce(function() {

		var val = scan_buffer.join('');
		console.log(`scanned-val:${val}`);

		if (val.length >= 20) {
			var rex = new RegExp('ANSI.+DCS.+DAC', 'ms');
			if (rex.test(val)) {

				// It's a PDF417 Scan
				var $self = $(this);
				$self.attr('readonly', true);

				var govST = val.match(/DAJ(\w+)/);
				console.log(govST);

				var govID = val.match(/DAQ(\w+)/);
				console.log(govID);

				var nameF = val.match(/DAC(\w+)/);
				console.log(nameF);

				var nameL = val.match(/DCS(\w+)/);
				console.log(nameL);

				var nameM = val.match(/DAD(\w+)/);
				console.log(nameM);

				// Some have this odd thing, so we replace it out
				val = val.replace(/DB\[Shift\]B/, 'DBB');
				var dob = val.match(/DBB(\d{2})(\d{2})(\d{4})/);
				console.log(dob);

				$govInput.val(`${govST[1]} / ${govID[1]}`);
				$('#client-contact-scanned-id').val('1');
				$('#client-contact-name').val(`${nameF[1]} ${nameM[1]} ${nameL[1]}`);
				$('#client-contact-dob').val(`${dob[3]}-${dob[1]}-${dob[2]}`);
				$('#client-contact-pid').focus();

				fetch(`/contact/ajax?term=${govID[1]}`)
					.then(res => res.json())
					.then(function(res) {
						debugger;
					});
			}
		}
	}, 250));

	$('#client-contact-govt-id-scanner').on('click', _checkout_open_reopen);

	$('.contact-autocomplete').autocomplete({
		source: '/contact/ajax',
		select: function(e, ui) {
			// debugger;
			$('#client-contact-id').val(ui.item.id);
		}
	});

	$('#btn-form-reset').on('click', _checkout_open_reopen);

});
</script>

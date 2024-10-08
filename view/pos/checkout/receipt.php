<?php
/**
 * Receipt Options
 */

$this->layout_file = sprintf('%s/view/_layout/html-pos.php', APP_ROOT);

?>

<div class="container mt-4">

<div class="row justify-content-center mb-2">
<div class="col-md-8">

	<div class="alert alert-success" style="font-size:28px;">
		<div class="d-flex justify-content-between">
			<div>Paid:</div>
			<div class="r">$<?= number_format($data['cash_incoming'], 2) ?></div>
		</div>
	</div>

	<div class="alert alert-danger" style="font-size:28px;">
		<div class="d-flex justify-content-between">
			<div>Change:</div>
			<div class="r">$<?= number_format($data['cash_outgoing'], 2) ?></div>
		</div>
	</div>

</div>
</div>

<div class="row justify-content-center">
<div class="col-md-8">
<div class="card">
	<h3 class="card-header">Receipt</h3>
	<div class="card-body">
		<?php
		if ( ! empty($data['send-via-email'])) {
		?>
			<form autocomplete="off" method="post">
			<div class="mb-2">
				<h4>Email</h4>
				<input name="sale_id" type="hidden" value="<?= $data['Sale']['id'] ?>">
				<div class="input-group">
					<input class="form-control" name="receipt-email" placeholder="client@email.com" type="email">
					<button class="btn btn-secondary" name="a" value="send-email"><i class="fas fa-envelope-open-text"></i> Email Receipt</button>
				</div>
			</div>
			</form>
		<?php
		}
		?>

		<?php
		if ( ! empty($data['send-via-phone'])) {
		?>
			<form autocomplete="off" method="post">
			<div class="mb-2">
				<h4>Text/SMS</h4>
				<input name="sale_id" type="hidden" value="<?= $data['Sale']['id'] ?>">
				<div class="input-group">
					<input class="form-control" name="receipt-phone" placeholder="(###) ###-####" type="text">
					<button class="btn btn-secondary" name="a" value="send-phone"><i class="fas fa-sms"></i> Send Receipt</button>
				</div>
			</div>
			</form>
		<?php
		}
		?>

		<!--
		<form autocomplete="off" method="post">
		<div class="mb-2">
			<h4>Print It</h4>
			<div class="input-group">
				<select class="form-select" id="printer-list">
					<option>- Select Printer -</option>
					<?php
					foreach ($data['printer_list'] as $p) {
						printf('<option data-local-link="%s" value="%s">%s</option>', $p['link'], $p['type'], __h($p['name']));
					}
					?>
				</select>

				<button class="btn btn-warning"
					formtarget="openthc-print-window" id="send-print" name="a" type="button"
					value="send-print"><i class="fas fa-print"></i> Print Receipt</button>

				<button class="btn btn-warning"
					id="send-print-frame"
					type="button"><i class="fas fa-print"></i> Print Receipt</button>

			</div>
			<p>Warning: Printing kills trees</p>
		</div>
		</form>

		-->

		<iframe id="print-frame" name="print-frame"
			src="/pos/checkout/receipt?s=<?= rawurldecode($data['Sale']['id']) ?>&amp;a=pdf"
			style="border: 1px solid #000; width:100%;"></iframe>

	</div>


	<form autocomplete="off" method="post">
	<div class="card-footer">
		<div class="d-flex justify-content-between">
			<button class="btn btn-lg btn-primary"
				id="send-print-frame"
				type="button"><i class="fas fa-print"></i> Print Receipt</button>

			<button class="btn btn-lg btn-secondary" name="a" value="send-blank"><i class="fas fa-ban"></i> No Receipt</button>

			<a class="btn btn-lg btn-secondary pos-checkout-reopen" href="/pos/open"><i class="fas fa-check-square"></i> Done</a>
		</div>
	</div>
	</form>

</div>
</div>
</div>
</div>


<script>
var printFrame = null;

function btnErrorFlash($btn)
{
	$btn.addClass('btn-outline-danger');
	setTimeout(function() {
		$btn.removeClass('btn-outline-danger');
	}, 1500);

}

$(function() {

	printFrame = document.querySelector('#print-frame');
	printFrame.addEventListener('load', function() {

		console.log('printFrame!load');

		// These events never fire because the content type is PDF

		// Can't get these to fire
		printFrame.addEventListener('beforeprint', function(e) {
			console.log('printFrame!beforeprint');
		});

		printFrame.contentWindow.addEventListener('beforeprint', function(e) {
			console.log('printFrame.contentWindow!beforeprint');
		});

		// printFrame.contentWindow.addEventListener('afterprint', function(e) {
		// 	console.log('onAfterPrint1!');
		// });

		var mediaQueryList = printFrame.contentWindow.matchMedia('print');
		mediaQueryList.addListener(function (evt) {
			debugger;
			console.log('print event', evt);
		});
		// printFrame.contentWindow.onafterprint = function(e) {
		// 	console.log('onAfterPrint2!');
		// };

		// setTimeout(function() {

			// printFrame.contentWindow.focus();
			// var res = printFrame.contentWindow.print();
			// console.log('print trigger');

		// }, 250);

	});


	$('#send-print').on('click', function() {

		var $btn = $(this);
		var $sel = $('#printer-list').find(':selected');
		var val = $('#printer-list').val();

		// @todo if the selected printer is marked as "local-http"
		// Then we have to capture the PDF from the server
		// And then POST that PDF document to the specific server
		// Hopefully it works, it must be running our custom "print" server
		//var lpu = $('#print-list').val();

		// What ?  Popup?  Prompt for AIR-Print or Whatever?

		switch (val) {
		case 'air':

			btnErrorFlash($btn);

			return false;

			break;

		case 'app-print-direct':

			// Have App Generate One-Time Link
			// Then Pass to the app-container (Android, iOS, Electron) via CustomEvent
			$.post('', { a: 'print-direct-link' }, function(body, stat) {

					var ce = new CustomEvent('openthc_print_direct');
					ce.printer_url = $sel.data('local-link');
					ce.document_url = body.data.document_url;

					window.dispatchEvent(ce);
			});

			return false;

			break;

		case 'lpd':

			// Emit an Application Specific Event for Android, Electron or iOS to Catch?
			btnErrorFlash($btn);

			return false;

			break;

		case 'pdf': // @deprecated

			// Browser Popup
			var opts = [];
			opts.push('top=' + (window.screenTop + 64));
			opts.push('left=' + (window.screenLeft + 64));
			opts.push('width=' + (window.outerWidth - 128));
			opts.push('height=' + (window.outerHeight - 256));
			opts.push('location=yes');
			opts.push('scrollbars=yes');

			var w = window.open('/loading.html', 'openthc-print-window', opts.join(','));
			w.addEventListener('load', function() {
				console.log('onLoad!');
				setTimeout(function() {
					w.print();
				}, 1000);
			}, true);
			w.addEventListener('afterprint', function() {
				console.log('onAfterPrint!');
				// w.close();
			});

			break;

		case 'rpi':

			var lpu = $sel.data('local-link');
			if (lpu) {
				var url = window.location;
				POS.Printer.printLocalNetwork(url, lpu);
			} else {
				btnErrorFlash($btn);
			}

			return false;

			break;

		}

	});

	/**
	 * Print Frame w/PDF
	 */
	$('#send-print-frame').on('click', function() {

		console.log('#send-print-frame!click');

		// This will trigger when the print dialog closes
		// window.addEventListener('mouseover', function(e) {
		// 	console.log('window!mouseover');
		// });

		printFrame.contentWindow.focus();
		printFrame.contentWindow.print();

		return false;

	});

	<?php
	if ($data['auto-print']) {
	?>
		$('#send-print-frame').trigger('click');
	<?php
	}
	?>

});
</script>

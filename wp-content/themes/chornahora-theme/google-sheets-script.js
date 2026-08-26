/**
 * Chornahora Yugoslavia — Google Apps Script webhook.
 *
 * Deploy:
 * 1. Open the order spreadsheet.
 * 2. Extensions → Apps Script → paste this file.
 * 3. Deploy → New deployment → Type: Web app.
 *    Execute as: Me
 *    Who has access: Anyone
 * 4. Copy the web app URL into wp-config.php:
 *    define( 'CHORNAHORA_SHEETS_WEBHOOK_URL', 'https://script.google.com/macros/s/.../exec' );
 *
 * Incoming JSON keys:
 * datetime, order_id, client_name, phone, email, city, warehouse,
 * payment_method, amount, status, comment
 */

var SHEET_HEADERS = [
	'datetime',
	'order_id',
	'client_name',
	'phone',
	'email',
	'city',
	'warehouse',
	'payment_method',
	'amount',
	'status',
	'comment',
];

function doPost(e) {
	try {
		var data = JSON.parse(e.postData.contents);
		var sheet = SpreadsheetApp.getActiveSpreadsheet().getActiveSheet();
		ensureHeaders_(sheet);

		var row = [
			data.datetime || '',
			data.order_id || '',
			data.client_name || '',
			data.phone || '',
			data.email || '',
			data.city || '',
			data.warehouse || '',
			data.payment_method || '',
			data.amount || '',
			data.status || '',
			data.comment || '',
		];

		var existing = findRowByOrderId_(sheet, data.order_id);
		if (existing) {
			sheet.getRange(existing, 1, 1, SHEET_HEADERS.length).setValues([row]);
		} else {
			sheet.appendRow(row);
		}

		return ContentService
			.createTextOutput(JSON.stringify({ status: 'success' }))
			.setMimeType(ContentService.MimeType.JSON);
	} catch (err) {
		return ContentService
			.createTextOutput(JSON.stringify({ status: 'error', message: String(err) }))
			.setMimeType(ContentService.MimeType.JSON);
	}
}

function ensureHeaders_(sheet) {
	if (sheet.getLastRow() === 0) {
		sheet.appendRow(SHEET_HEADERS);
	}
}

function findRowByOrderId_(sheet, orderId) {
	if (!orderId) {
		return 0;
	}

	var lastRow = sheet.getLastRow();
	if (lastRow < 2) {
		return 0;
	}

	var values = sheet.getRange(2, 2, lastRow - 1, 1).getValues();
	for (var i = 0; i < values.length; i++) {
		if (String(values[i][0]) === String(orderId)) {
			return i + 2;
		}
	}

	return 0;
}

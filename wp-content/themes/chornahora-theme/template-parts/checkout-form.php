<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$amount = (int) CHORNAHORA_BOOK_PRICE;
?>
<form class="checkout-form" id="ch-checkout-form" novalidate>
	<p class="checkout-form__note">Доставка Новою поштою</p>

	<div class="checkout-field">
		<label for="ch-full-name">Прізвище та ім’я</label>
		<input id="ch-full-name" name="full_name" type="text" autocomplete="name" required>
		<span class="checkout-field__error" data-error-for="full_name"></span>
	</div>

	<div class="checkout-field">
		<label for="ch-phone">Телефон</label>
		<input id="ch-phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="+380 (XX) XXX-XX-XX" required>
		<span class="checkout-field__error" data-error-for="phone"></span>
	</div>

	<div class="checkout-field">
		<label for="ch-email">Email</label>
		<input id="ch-email" name="email" type="email" autocomplete="email" required>
		<span class="checkout-field__error" data-error-for="email"></span>
	</div>

	<div class="checkout-field checkout-field--suggest">
		<label for="ch-city">Місто</label>
		<input id="ch-city" name="city" type="text" autocomplete="off" required>
		<input type="hidden" name="city_ref" id="ch-city-ref">
		<input type="hidden" name="settlement_ref" id="ch-settlement-ref">
		<ul class="checkout-suggest" id="ch-city-suggest" hidden role="listbox"></ul>
		<span class="checkout-field__error" data-error-for="city"></span>
	</div>

	<div class="checkout-field">
		<label for="ch-warehouse">Відділення / поштомат</label>
		<select id="ch-warehouse" name="warehouse_ref" required disabled>
			<option value="">Спочатку оберіть місто</option>
		</select>
		<input type="hidden" name="warehouse_label" id="ch-warehouse-label">
		<span class="checkout-field__error" data-error-for="warehouse"></span>
	</div>

	<fieldset class="checkout-field checkout-field--radios">
		<legend>Оплата</legend>
		<label class="checkout-radio">
			<input type="radio" name="payment" value="wayforpay" required>
			<span>Оплата на сайті (WayForPay)</span>
		</label>
		<label class="checkout-radio">
			<input type="radio" name="payment" value="cod">
			<span>Оплата під час отримання (НП післяплата)</span>
		</label>
		<span class="checkout-field__error" data-error-for="payment"></span>
	</fieldset>

	<p class="checkout-form__status" data-form-status aria-live="polite"></p>

	<button class="btn btn--primary checkout-form__submit" type="submit">
		Підтвердити замовлення (<?php echo esc_html( (string) $amount ); ?> грн)
	</button>
</form>

<div class="checkout-modal" id="ch-checkout-modal" hidden>
	<div class="checkout-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="ch-checkout-modal-title">
		<h2 id="ch-checkout-modal-title">Замовлення прийнято</h2>
		<p data-modal-message></p>
		<button class="btn btn--primary" type="button" data-modal-close>Закрити</button>
	</div>
</div>

<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$amount = (int) CHORNAHORA_BOOK_PRICE;
?>
<form class="checkout-form" id="ch-checkout-form" novalidate>
	<div class="checkout-layout">
		<div class="checkout-layout__main">
			<section class="checkout-section">
				<h2 class="checkout-section__title">Оплата та доставка</h2>

				<div class="checkout-field">
					<label for="ch-full-name">Ім'я та Прізвище *</label>
					<input id="ch-full-name" name="full_name" type="text" autocomplete="name" required>
					<span class="checkout-field__error" data-error-for="full_name"></span>
				</div>

				<div class="checkout-field">
					<label for="ch-phone">Телефон *</label>
					<input id="ch-phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="+380 (XX) XXX-XX-XX" required>
					<span class="checkout-field__error" data-error-for="phone"></span>
				</div>

				<div class="checkout-field">
					<label for="ch-email">E-mail адреса *</label>
					<input id="ch-email" name="email" type="email" autocomplete="email" required>
					<span class="checkout-field__error" data-error-for="email"></span>
				</div>
			</section>

			<section class="checkout-section">
				<h2 class="checkout-section__title">Доставка Новою Поштою</h2>

				<div class="checkout-field checkout-field--suggest checkout-field--chevron">
					<label for="ch-city">Виберіть населений пункт</label>
					<div class="checkout-city">
						<input id="ch-city" name="city" type="text" autocomplete="off" placeholder="Виберіть населений пункт" required>
						<ul class="checkout-suggest" id="ch-city-suggest" hidden role="listbox"></ul>
					</div>
					<input type="hidden" name="city_ref" id="ch-city-ref">
					<input type="hidden" name="settlement_ref" id="ch-settlement-ref">
					<span class="checkout-field__error" data-error-for="city"></span>
				</div>

				<div class="checkout-field checkout-field--chevron">
					<label for="ch-warehouse">Виберіть відділення або поштомат</label>
					<select id="ch-warehouse" name="warehouse_ref" required disabled>
						<option value="">Виберіть відділення або поштомат</option>
					</select>
					<input type="hidden" name="warehouse_label" id="ch-warehouse-label">
					<span class="checkout-field__error" data-error-for="warehouse"></span>
				</div>

				<div class="checkout-field">
					<label for="ch-notes">Додаткова інформація (необов'язково)</label>
					<textarea id="ch-notes" name="notes" rows="3" placeholder="Впишіть ваші побажання до замовлення"></textarea>
					<span class="checkout-field__error" data-error-for="notes"></span>
				</div>
			</section>
		</div>

		<aside class="checkout-layout__aside">
			<section class="checkout-box">
				<h2 class="checkout-box__title">Ваше замовлення</h2>
				<div class="checkout-box__row">
					<span>Книга “Кривава агонія Югославії” (Олександр Ткаченко) × 1</span>
					<strong><?php echo esc_html( (string) $amount ); ?> грн.</strong>
				</div>
				<div class="checkout-box__row checkout-box__row--ship">
					<span>ВІДПРАВЛЕННЯ</span>
					<span>Нова Пошта</span>
				</div>
				<div class="checkout-box__row checkout-box__row--total">
					<span>ВСЬОГО</span>
					<strong><?php echo esc_html( (string) $amount ); ?> ГРН.</strong>
				</div>
			</section>

			<section class="checkout-box">
				<h2 class="checkout-box__title">Оплата</h2>
				<fieldset class="checkout-field checkout-field--radios">
					<legend class="sr-only">Спосіб оплати</legend>
					<label class="checkout-radio">
						<input type="radio" name="payment" value="wayforpay" required checked>
						<span class="checkout-radio__body">
							<span class="checkout-radio__title">
								Оплатити на сайті
								<span class="checkout-wfp" aria-hidden="true">WAYFORPAY</span>
							</span>
							<span class="checkout-radio__hint">Безпечна оплата карткою або через інтернет-банкінг. Без додаткової комісії.</span>
						</span>
					</label>
					<label class="checkout-radio">
						<input type="radio" name="payment" value="cod">
						<span class="checkout-radio__body">
							<span class="checkout-radio__title">Готівка при отриманні</span>
						</span>
					</label>
					<span class="checkout-field__error" data-error-for="payment"></span>
				</fieldset>

				<p class="checkout-form__status" data-form-status aria-live="polite"></p>

				<button class="btn checkout-form__submit" type="submit">
					ПІДТВЕРДИТИ ЗАМОВЛЕННЯ
				</button>
			</section>
		</aside>
	</div>
</form>

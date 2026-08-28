<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$amount = (int) CHORNAHORA_BOOK_PRICE;
?>
<form class="checkout-form" id="ch-checkout-form" novalidate>
	<div class="checkout-layout">
		<div class="checkout-layout__main">
			<section class="checkout-section checkout-step">
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

			<section class="checkout-section checkout-step">
				<h2 class="checkout-section__title">Доставка Новою Поштою</h2>

				<div class="checkout-field checkout-field--np form-group">
					<span class="checkout-field__label" id="ch-city-label">Виберіть населений пункт</span>
					<div class="np-select np-field-wrapper" id="ch-city-widget" data-np="city">
						<button type="button" class="np-select__trigger np-select-trigger np-city-trigger" id="ch-city-trigger" aria-labelledby="ch-city-label" aria-haspopup="listbox" aria-expanded="false">
							<span class="np-select__value is-placeholder" id="ch-city-value">Виберіть населений пункт</span>
							<span class="np-select__chevron" aria-hidden="true"></span>
						</button>
						<input type="hidden" name="city" id="ch-city" required>
						<input type="hidden" name="city_ref" id="ch-city-ref">
						<input type="hidden" name="settlement_ref" id="ch-settlement-ref">
						<div class="np-select__dropdown" id="ch-city-dropdown" hidden>
							<input type="text" class="np-search-input" id="ch-city-search" placeholder="Почніть вводити назву..." autocomplete="off">
							<ul class="np-select__list" id="ch-city-suggest" role="listbox"></ul>
						</div>
					</div>
					<span class="checkout-field__error" data-error-for="city"></span>
				</div>

				<div class="checkout-field checkout-field--np form-group">
					<span class="checkout-field__label" id="ch-warehouse-label-text">Виберіть відділення або поштомат</span>
					<div class="np-select np-field-wrapper is-disabled" id="ch-warehouse-widget" data-np="warehouse">
						<button type="button" class="np-select__trigger np-select-trigger np-warehouse-trigger disabled" id="ch-warehouse-trigger" aria-labelledby="ch-warehouse-label-text" aria-haspopup="listbox" aria-expanded="false" disabled>
							<span class="np-select__value is-placeholder" id="ch-warehouse-value">Виберіть відділення або поштомат</span>
							<span class="np-select__chevron" aria-hidden="true"></span>
						</button>
						<input type="hidden" name="warehouse_ref" id="ch-warehouse" required>
						<input type="hidden" name="warehouse_label" id="ch-warehouse-label">
						<div class="np-select__dropdown" id="ch-warehouse-dropdown" hidden>
							<input type="text" class="np-search-input" id="ch-warehouse-search" placeholder="Почніть вводити назву..." autocomplete="off">
							<ul class="np-select__list" id="ch-warehouse-list" role="listbox"></ul>
						</div>
					</div>
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

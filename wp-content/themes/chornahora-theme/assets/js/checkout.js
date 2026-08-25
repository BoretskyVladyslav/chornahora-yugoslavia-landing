(function () {
	"use strict";

	var cfg = window.chCheckout;
	var form = document.getElementById("ch-checkout-form");

	if (!cfg || !form) {
		return;
	}

	var cityInput = document.getElementById("ch-city");
	var cityRef = document.getElementById("ch-city-ref");
	var settlementRef = document.getElementById("ch-settlement-ref");
	var suggest = document.getElementById("ch-city-suggest");
	var warehouse = document.getElementById("ch-warehouse");
	var warehouseLabel = document.getElementById("ch-warehouse-label");
	var phone = document.getElementById("ch-phone");
	var statusEl = form.querySelector("[data-form-status]");
	var cityTimer = 0;
	var cityCache = {};
	var warehouseCache = {};
	var abortCity = null;

	function post(action, payload) {
		var body = new FormData();
		body.append("action", action);
		body.append("nonce", cfg.nonce);
		Object.keys(payload).forEach(function (key) {
			body.append(key, payload[key]);
		});
		return fetch(cfg.ajaxUrl, {
			method: "POST",
			credentials: "same-origin",
			body: body,
		}).then(function (res) {
			return res.json().then(function (json) {
				json.status = res.status;
				return json;
			});
		});
	}

	function formatPhone(value) {
		var digits = String(value || "").replace(/\D+/g, "");
		if (digits.indexOf("380") === 0) {
			digits = digits.slice(3);
		} else if (digits.charAt(0) === "0") {
			digits = digits.slice(1);
		}
		digits = digits.slice(0, 9);
		var out = "+380";
		if (digits.length) {
			out += " (" + digits.slice(0, 2);
		}
		if (digits.length >= 2) {
			out += ")";
		}
		if (digits.length > 2) {
			out += " " + digits.slice(2, 5);
		}
		if (digits.length > 5) {
			out += "-" + digits.slice(5, 7);
		}
		if (digits.length > 7) {
			out += "-" + digits.slice(7, 9);
		}
		return out;
	}

	function isCompletePhone(value) {
		return /^\+380 \(\d{2}\) \d{3}-\d{2}-\d{2}$/.test(String(value || ""));
	}

	function isValidEmail(value) {
		return /^[A-Za-z0-9._%+-]+@[A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)*\.[A-Za-z]{2,}$/.test(String(value || "").trim());
	}

	function validateForm() {
		var errors = {};
		var fullName = String(form.full_name.value || "").trim();
		var email = String(form.email.value || "").trim();
		var city = String(form.city.value || "").trim();
		var payment = (form.querySelector('input[name="payment"]:checked') || {}).value || "";

		if (fullName.length < 2) {
			errors.full_name = "Вкажіть ім'я та прізвище.";
		}

		if (!isCompletePhone(form.phone.value)) {
			errors.phone = "Вкажіть телефон у форматі +380 (XX) XXX-XX-XX.";
		}

		if (!isValidEmail(email)) {
			errors.email = "Вкажіть коректний email (наприклад, user@domain.com).";
		}

		if (!city || (!cityRef.value && !settlementRef.value)) {
			errors.city = "Оберіть місто зі списку Нової пошти.";
		}

		if (!warehouse.value) {
			errors.warehouse = "Оберіть відділення або поштомат.";
		}

		if (!payment) {
			errors.payment = "Оберіть спосіб оплати.";
		}

		return errors;
	}

	function clearErrors() {
		form.querySelectorAll(".checkout-field__error").forEach(function (el) {
			el.textContent = "";
		});
		form.querySelectorAll(".is-invalid").forEach(function (el) {
			el.classList.remove("is-invalid");
		});
		statusEl.textContent = "";
	}

	function showFieldErrors(fields) {
		Object.keys(fields || {}).forEach(function (key) {
			var msg = fields[key];
			var holder = form.querySelector('[data-error-for="' + key + '"]');
			var input = form.querySelector('[name="' + (key === "warehouse" ? "warehouse_ref" : key) + '"]');
			if (holder) {
				holder.textContent = msg;
			}
			if (input) {
				input.classList.add("is-invalid");
			}
		});
	}

	function hideSuggest() {
		suggest.hidden = true;
		suggest.innerHTML = "";
	}

	function renderCities(cities) {
		suggest.innerHTML = "";
		if (!cities.length) {
			hideSuggest();
			return;
		}
		cities.forEach(function (city) {
			var li = document.createElement("li");
			li.setAttribute("role", "option");
			li.textContent = city.label;
			li.addEventListener("mousedown", function (event) {
				event.preventDefault();
				selectCity(city);
			});
			suggest.appendChild(li);
		});
		suggest.hidden = false;
	}

	function resetWarehouse() {
		warehouse.disabled = true;
		warehouse.innerHTML = '<option value="">Виберіть відділення або поштомат</option>';
		warehouseLabel.value = "";
	}

	function fillWarehouses(list) {
		warehouse.innerHTML = '<option value="">Виберіть відділення або поштомат</option>';
		list.forEach(function (item) {
			var opt = document.createElement("option");
			opt.value = item.ref;
			opt.textContent = item.label;
			warehouse.appendChild(opt);
		});
		warehouse.disabled = list.length === 0;
	}

	function selectCity(city) {
		cityInput.value = city.label;
		cityRef.value = city.city_ref || "";
		settlementRef.value = city.settlement_ref || "";
		hideSuggest();
		loadWarehouses();
	}

	function loadWarehouses() {
		var key = (cityRef.value || "") + "|" + (settlementRef.value || "");
		if (!cityRef.value && !settlementRef.value) {
			resetWarehouse();
			return;
		}
		if (warehouseCache[key]) {
			fillWarehouses(warehouseCache[key]);
			return;
		}
		warehouse.disabled = true;
		warehouse.innerHTML = '<option value="">Завантаження…</option>';
		post("ch_get_warehouses", {
			city_ref: cityRef.value,
			settlement_ref: settlementRef.value,
		}).then(function (json) {
			var list = json.success && json.data ? json.data.warehouses || [] : [];
			warehouseCache[key] = list;
			fillWarehouses(list);
		}).catch(function () {
			resetWarehouse();
			statusEl.textContent = "Не вдалося завантажити відділення. Спробуйте ще раз.";
		});
	}

	function searchCities() {
		var query = cityInput.value.trim();
		cityRef.value = "";
		settlementRef.value = "";
		resetWarehouse();
		if (query.length < 2) {
			hideSuggest();
			return;
		}
		if (cityCache[query]) {
			renderCities(cityCache[query]);
			return;
		}
		if (abortCity) {
			abortCity.abort();
		}
		abortCity = new AbortController();
		var body = new FormData();
		body.append("action", "ch_search_cities");
		body.append("nonce", cfg.nonce);
		body.append("query", query);
		fetch(cfg.ajaxUrl, {
			method: "POST",
			credentials: "same-origin",
			body: body,
			signal: abortCity.signal,
		})
			.then(function (res) {
				return res.json();
			})
			.then(function (json) {
				var cities = json.success && json.data ? json.data.cities || [] : [];
				cityCache[query] = cities;
				if (cityInput.value.trim() === query) {
					renderCities(cities);
				}
			})
			.catch(function (err) {
				if (err && err.name === "AbortError") {
					return;
				}
				hideSuggest();
			});
	}

	phone.addEventListener("input", function () {
		phone.value = formatPhone(phone.value);
	});

	cityInput.addEventListener("input", function () {
		window.clearTimeout(cityTimer);
		cityTimer = window.setTimeout(searchCities, 320);
	});

	cityInput.addEventListener("blur", function () {
		window.setTimeout(hideSuggest, 180);
	});

	warehouse.addEventListener("change", function () {
		var selected = warehouse.options[warehouse.selectedIndex];
		warehouseLabel.value = selected && selected.value ? selected.textContent : "";
	});

	function submitWayforpay(payload) {
		var wfp = payload.wayforpay;
		if (!wfp || !wfp.url || !wfp.fields) {
			statusEl.textContent = "Не вдалося підготувати оплату WayForPay.";
			return;
		}
		var payForm = document.createElement("form");
		payForm.method = "POST";
		payForm.action = wfp.url;
		payForm.acceptCharset = "utf-8";
		Object.keys(wfp.fields).forEach(function (key) {
			var value = wfp.fields[key];
			if (Array.isArray(value)) {
				value.forEach(function (item) {
					var input = document.createElement("input");
					input.type = "hidden";
					input.name = key + "[]";
					input.value = item;
					payForm.appendChild(input);
				});
				return;
			}
			var input = document.createElement("input");
			input.type = "hidden";
			input.name = key;
			input.value = value;
			payForm.appendChild(input);
		});
		document.body.appendChild(payForm);
		payForm.submit();
	}

	form.addEventListener("submit", function (event) {
		event.preventDefault();
		clearErrors();

		var errors = validateForm();
		if (Object.keys(errors).length) {
			showFieldErrors(errors);
			statusEl.textContent = "Перевірте поля форми.";
			return;
		}

		var submit = form.querySelector(".checkout-form__submit");
		submit.disabled = true;
		statusEl.textContent = "Обробка замовлення…";

		var payload = {
			full_name: String(form.full_name.value || "").trim(),
			phone: form.phone.value,
			email: String(form.email.value || "").trim(),
			city: String(form.city.value || "").trim(),
			city_ref: cityRef.value,
			settlement_ref: settlementRef.value,
			warehouse_ref: warehouse.value,
			warehouse_label: warehouseLabel.value,
			notes: form.notes ? String(form.notes.value || "").trim() : "",
			payment: (form.querySelector('input[name="payment"]:checked') || {}).value || "",
		};

		post("ch_process_order", payload)
			.then(function (json) {
				if (!json.success) {
					var err = json.data || {};
					showFieldErrors(err.fields || {});
					statusEl.textContent = err.message || "Перевірте поля форми.";
					submit.disabled = false;
					return;
				}
				var data = json.data;
				if (data.payment === "wayforpay") {
					statusEl.textContent = "Перехід до оплати…";
					submitWayforpay(data);
					return;
				}
				window.location.href = cfg.thankYouUrl || "/thank-you/";
			})
			.catch(function () {
				statusEl.textContent = "Помилка з’єднання. Спробуйте ще раз.";
				submit.disabled = false;
			});
	});
})();

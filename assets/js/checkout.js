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
	var lastSelectedLabel = "";
	var activeCities = [];
	var activeIndex = -1;
	var CITY_DEBOUNCE_MS = 300;
	var cityCache = {};
	var warehouseCache = {};
	var abortCity = null;
	var cityBusy = false;
	var warehouseBusy = false;
	var afterCityIdle = null;
	var afterWarehouseIdle = null;
	var citySearchGen = 0;

	function setStatus(text) {
		if (statusEl) {
			statusEl.textContent = text;
		}
	}

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
		setStatus("");
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

	function clearFieldError(key) {
		var holder = form.querySelector('[data-error-for="' + key + '"]');
		var input = form.querySelector('[name="' + (key === "warehouse" ? "warehouse_ref" : key) + '"]');
		if (holder) {
			holder.textContent = "";
		}
		if (input) {
			input.classList.remove("is-invalid");
		}
	}

	function escapeHtml(value) {
		return String(value || "")
			.replace(/&/g, "&amp;")
			.replace(/</g, "&lt;")
			.replace(/>/g, "&gt;")
			.replace(/"/g, "&quot;");
	}

	function highlightMatch(label, query) {
		var text = String(label || "");
		var q = String(query || "").trim();
		if (!q) {
			return escapeHtml(text);
		}
		var idx = text.toLowerCase().indexOf(q.toLowerCase());
		if (idx === -1) {
			return escapeHtml(text);
		}
		return (
			escapeHtml(text.slice(0, idx)) +
			"<mark>" +
			escapeHtml(text.slice(idx, idx + q.length)) +
			"</mark>" +
			escapeHtml(text.slice(idx + q.length))
		);
	}

	function hideSuggest() {
		suggest.hidden = true;
		suggest.innerHTML = "";
		suggest.style.display = "none";
		activeCities = [];
		activeIndex = -1;
	}

	function applySuggestStyles() {
		suggest.style.position = "absolute";
		suggest.style.left = "0";
		suggest.style.width = "100%";
		suggest.style.zIndex = "99999";
		suggest.style.background = "#fff";
		suggest.style.border = "1px solid #ddd";
		suggest.style.boxShadow = "0 8px 24px rgba(0,0,0,0.15)";
		suggest.style.maxHeight = "260px";
		suggest.style.overflowY = "auto";
		suggest.style.top = "100%";
		suggest.style.display = "block";
		suggest.style.margin = "0";
		suggest.style.padding = "0.25rem 0";
		suggest.style.listStyle = "none";
	}

	function setActiveItem(index) {
		var items = suggest.querySelectorAll("li");
		if (!items.length) {
			activeIndex = -1;
			return;
		}
		activeIndex = (index + items.length) % items.length;
		items.forEach(function (item, i) {
			item.classList.toggle("is-active", i === activeIndex);
			item.setAttribute("aria-selected", i === activeIndex ? "true" : "false");
			if (i === activeIndex && item.scrollIntoView) {
				item.scrollIntoView({ block: "nearest" });
			}
		});
	}

	function renderCities(cities, query) {
		suggest.innerHTML = "";
		activeCities = cities || [];
		activeIndex = activeCities.length ? 0 : -1;
		if (!activeCities.length) {
			hideSuggest();
			return;
		}
		activeCities.forEach(function (city, index) {
			var li = document.createElement("li");
			li.setAttribute("role", "option");
			li.innerHTML = highlightMatch(city.label, query);
			if (index === 0) {
				li.classList.add("is-active");
				li.setAttribute("aria-selected", "true");
			}
			li.addEventListener("mousedown", function (event) {
				event.preventDefault();
				selectCity(city);
			});
			li.addEventListener("click", function (event) {
				event.preventDefault();
				selectCity(city);
			});
			suggest.appendChild(li);
		});
		suggest.hidden = false;
		suggest.removeAttribute("hidden");
		applySuggestStyles();
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
		if (!city) {
			return;
		}
		lastSelectedLabel = city.label;
		cityInput.value = city.label;
		cityRef.value = city.city_ref || city.Ref || "";
		settlementRef.value = city.settlement_ref || city.SettlementRef || city.ref || "";
		hideSuggest();
		clearFieldError("city");
		clearFieldError("warehouse");
		fetchWarehouses();
	}

	function runAfterCityIdle(fn) {
		afterCityIdle = fn;
	}

	function runAfterWarehouseIdle(fn) {
		afterWarehouseIdle = fn;
	}

	function flushIdleQueue(slot) {
		var fn = slot === "city" ? afterCityIdle : afterWarehouseIdle;
		if (slot === "city") {
			afterCityIdle = null;
		} else {
			afterWarehouseIdle = null;
		}
		if (fn) {
			fn();
		}
	}

	function fetchWarehouses(ref) {
		var city_ref = cityRef.value || ref || "";
		var settlement_ref = settlementRef.value || "";
		var key = city_ref + "|" + settlement_ref;
		if (!city_ref && !settlement_ref) {
			warehouseBusy = false;
			resetWarehouse();
			flushIdleQueue("warehouse");
			return;
		}
		if (warehouseCache[key]) {
			warehouseBusy = false;
			fillWarehouses(warehouseCache[key]);
			flushIdleQueue("warehouse");
			return;
		}
		warehouseBusy = true;
		warehouse.disabled = true;
		warehouse.innerHTML = '<option value="">Завантаження…</option>';
		post("ch_get_warehouses", {
			city_ref: city_ref,
			settlement_ref: settlement_ref,
		}).then(function (json) {
			var list = json.success && json.data ? json.data.warehouses || [] : [];
			warehouseCache[key] = list;
			fillWarehouses(list);
		}).catch(function () {
			resetWarehouse();
			setStatus("Не вдалося завантажити відділення. Спробуйте ще раз.");
		}).then(function () {
			warehouseBusy = false;
			flushIdleQueue("warehouse");
		});
	}

	function bestCityMatch(query) {
		var q = String(query || "").trim().toLowerCase();
		if (!activeCities.length) {
			return null;
		}
		if (activeIndex >= 0 && activeCities[activeIndex]) {
			return activeCities[activeIndex];
		}
		var exact = null;
		var starts = null;
		activeCities.forEach(function (city) {
			var label = String(city.label || "").toLowerCase();
			if (!exact && label === q) {
				exact = city;
			}
			if (!starts && label.indexOf(q) === 0) {
				starts = city;
			}
		});
		return exact || starts || activeCities[0];
	}

	function commitCityFromSuggest() {
		if (lastSelectedLabel && cityInput.value.trim() === lastSelectedLabel && (cityRef.value || settlementRef.value)) {
			hideSuggest();
			return;
		}
		var city = bestCityMatch(cityInput.value);
		if (city) {
			selectCity(city);
			return;
		}
		hideSuggest();
	}

	function searchCities() {
		var query = cityInput.value.trim();
		clearFieldError("city");
		clearFieldError("warehouse");
		if (query.length < 2) {
			cityBusy = false;
			hideSuggest();
			if (!lastSelectedLabel || query !== lastSelectedLabel) {
				cityRef.value = "";
				settlementRef.value = "";
				resetWarehouse();
			}
			flushIdleQueue("city");
			return;
		}
		if (query !== lastSelectedLabel) {
			cityRef.value = "";
			settlementRef.value = "";
			resetWarehouse();
		}
		if (cityCache[query]) {
			cityBusy = false;
			renderCities(cityCache[query], query);
			flushIdleQueue("city");
			return;
		}
		if (abortCity) {
			abortCity.abort();
		}
		var searchGen = ++citySearchGen;
		cityBusy = true;
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
					renderCities(cities, query);
				}
			})
			.catch(function (err) {
				if (err && err.name === "AbortError") {
					return;
				}
				hideSuggest();
			})
			.then(function () {
				if (searchGen !== citySearchGen) {
					return;
				}
				cityBusy = false;
				flushIdleQueue("city");
			});
	}

	function scheduleSearch() {
		clearFieldError("city");
		clearFieldError("warehouse");
		window.clearTimeout(cityTimer);
		cityTimer = window.setTimeout(searchCities, CITY_DEBOUNCE_MS);
	}

	var skipSearchKeys = {
		ArrowDown: true,
		ArrowUp: true,
		Enter: true,
		Escape: true,
		Tab: true,
		Shift: true,
		Control: true,
		Alt: true,
		Meta: true,
	};

	phone.addEventListener("input", function () {
		phone.value = formatPhone(phone.value);
	});

	["input", "keyup", "paste", "focus"].forEach(function (evt) {
		cityInput.addEventListener(evt, function (event) {
			if (evt === "keyup" && event.key && skipSearchKeys[event.key]) {
				return;
			}
			clearFieldError("city");
			clearFieldError("warehouse");
			if (evt === "paste") {
				window.setTimeout(scheduleSearch, 0);
				return;
			}
			scheduleSearch();
		});
	});

	cityInput.addEventListener("keydown", function (event) {
		if (event.key === "ArrowDown") {
			event.preventDefault();
			if (suggest.hidden) {
				scheduleSearch();
				return;
			}
			setActiveItem(activeIndex + 1);
			return;
		}
		if (event.key === "ArrowUp") {
			event.preventDefault();
			if (!suggest.hidden) {
				setActiveItem(activeIndex - 1);
			}
			return;
		}
		if (event.key === "Enter") {
			event.preventDefault();
			commitCityFromSuggest();
			return;
		}
		if (event.key === "Escape") {
			hideSuggest();
		}
	});

	cityInput.addEventListener("blur", function () {
		window.setTimeout(commitCityFromSuggest, 180);
	});

	warehouse.addEventListener("change", function () {
		var selected = warehouse.options[warehouse.selectedIndex];
		warehouseLabel.value = selected && selected.value ? selected.textContent : "";
	});

	function submitWayforpay(payload) {
		var wfp = payload.wayforpay;
		if (!wfp || !wfp.url || !wfp.fields) {
			setStatus("Не вдалося підготувати оплату WayForPay.");
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

		function sendOrder() {
			clearErrors();

			var errors = validateForm();
			if (Object.keys(errors).length) {
				showFieldErrors(errors);
				setStatus("Перевірте поля форми.");
				return;
			}

			var submit = form.querySelector(".checkout-form__submit");
			submit.disabled = true;
			setStatus("Обробка замовлення…");

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
						setStatus(err.message || "Перевірте поля форми.");
						submit.disabled = false;
						return;
					}
					var data = json.data;
					if (data.payment === "wayforpay") {
						setStatus("Перехід до оплати…");
						submitWayforpay(data);
						return;
					}
					window.location.href = data.thank_you_url || cfg.thankYouUrl || "/thank-you/";
				})
				.catch(function () {
					setStatus("Помилка з’єднання. Спробуйте ще раз.");
					submit.disabled = false;
				});
		}

		function continueSubmit() {
			if (cityTimer) {
				window.clearTimeout(cityTimer);
				cityTimer = 0;
				searchCities();
			}
			if (cityBusy) {
				runAfterCityIdle(continueSubmit);
				return;
			}
			commitCityFromSuggest();
			if (warehouseBusy) {
				runAfterWarehouseIdle(sendOrder);
				return;
			}
			sendOrder();
		}

		continueSubmit();
	});
})();

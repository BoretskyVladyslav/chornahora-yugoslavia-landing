(function () {
	"use strict";

	var cfg = window.chCheckout;
	var form = document.getElementById("ch-checkout-form");

	if (!cfg || !form) {
		return;
	}

	var cityWidget = document.getElementById("ch-city-widget");
	var cityTrigger = document.getElementById("ch-city-trigger");
	var cityValueEl = document.getElementById("ch-city-value");
	var citySearch = document.getElementById("ch-city-search");
	var cityDropdown = document.getElementById("ch-city-dropdown");
	var cityList = document.getElementById("ch-city-suggest");
	var cityHidden = document.getElementById("ch-city");
	var cityRef = document.getElementById("ch-city-ref");
	var settlementRef = document.getElementById("ch-settlement-ref");

	var warehouseWidget = document.getElementById("ch-warehouse-widget");
	var warehouseTrigger = document.getElementById("ch-warehouse-trigger");
	var warehouseValueEl = document.getElementById("ch-warehouse-value");
	var warehouseSearch = document.getElementById("ch-warehouse-search");
	var warehouseDropdown = document.getElementById("ch-warehouse-dropdown");
	var warehouseListEl = document.getElementById("ch-warehouse-list");
	var warehouse = document.getElementById("ch-warehouse");
	var warehouseLabel = document.getElementById("ch-warehouse-label");

	var phone = document.getElementById("ch-phone");

	if (
		!cityWidget ||
		!cityTrigger ||
		!citySearch ||
		!cityDropdown ||
		!cityList ||
		!cityHidden ||
		!cityRef ||
		!settlementRef ||
		!warehouseWidget ||
		!warehouseTrigger ||
		!warehouseSearch ||
		!warehouseDropdown ||
		!warehouseListEl ||
		!warehouse ||
		!warehouseLabel ||
		!phone
	) {
		return;
	}

	var statusEl = form.querySelector("[data-form-status]");
	var cityTimer = 0;
	var lastSelectedLabel = "";
	var activeCities = [];
	var activeCityIndex = -1;
	var warehouseItems = [];
	var activeWarehouseIndex = -1;
	var CITY_DEBOUNCE_MS = 350;
	var cityCache = {};
	var warehouseCache = {};
	var abortCity = null;
	var cityBusy = false;
	var warehouseBusy = false;
	var afterCityIdle = null;
	var afterWarehouseIdle = null;
	var citySearchGen = 0;
	var PLACEHOLDER_CITY = "Виберіть населений пункт";
	var PLACEHOLDER_WAREHOUSE = "Виберіть відділення або поштомат";

	function setStatus(text) {
		if (statusEl) {
			statusEl.textContent = text;
		}
	}

	function parseJsonSafe(text) {
		if (!text) {
			return {};
		}
		try {
			var parsed = JSON.parse(text);
			if (!parsed || typeof parsed !== "object" || Array.isArray(parsed)) {
				return {};
			}
			return parsed;
		} catch (err) {
			return {};
		}
	}

	function post(action, payload, signal) {
		var body = new FormData();
		body.append("action", action);
		body.append("nonce", cfg.nonce);
		Object.keys(payload || {}).forEach(function (key) {
			body.append(key, payload[key] == null ? "" : payload[key]);
		});
		var opts = {
			method: "POST",
			credentials: "same-origin",
			body: body,
		};
		if (signal) {
			opts.signal = signal;
		}
		return fetch(cfg.ajaxUrl, opts).then(function (res) {
			return res.text().then(function (text) {
				var json = parseJsonSafe(text);
				json.status = res.status;
				if (typeof json.success === "undefined") {
					json.success = false;
				}
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
		var city = String(cityHidden.value || "").trim();
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

		if (!cityBusy && (!city || (!cityRef.value && !settlementRef.value))) {
			errors.city = "Оберіть місто зі списку Нової пошти.";
		}

		if (!warehouseBusy && !warehouse.value) {
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
		cityWidget.classList.remove("is-invalid");
		warehouseWidget.classList.remove("is-invalid");
		setStatus("");
	}

	function showFieldErrors(fields) {
		var typingCity = cityWidget.classList.contains("is-open") || document.activeElement === citySearch;
		Object.keys(fields || {}).forEach(function (key) {
			if ((key === "city" || key === "warehouse") && (typingCity || cityBusy || warehouseBusy)) {
				return;
			}
			var msg = fields[key];
			var holder = form.querySelector('[data-error-for="' + key + '"]');
			if (holder) {
				holder.textContent = msg;
			}
			if (key === "city") {
				cityWidget.classList.add("is-invalid");
			} else if (key === "warehouse") {
				warehouseWidget.classList.add("is-invalid");
			} else {
				var input = form.querySelector('[name="' + key + '"]');
				if (input) {
					input.classList.add("is-invalid");
				}
			}
		});
	}

	function clearFieldError(key) {
		var holder = form.querySelector('[data-error-for="' + key + '"]');
		if (holder) {
			holder.textContent = "";
		}
		if (key === "city") {
			cityWidget.classList.remove("is-invalid");
		} else if (key === "warehouse") {
			warehouseWidget.classList.remove("is-invalid");
		} else {
			var input = form.querySelector('[name="' + (key === "warehouse" ? "warehouse_ref" : key) + '"]');
			if (input) {
				input.classList.remove("is-invalid");
			}
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

	function setTriggerLabel(el, text, isPlaceholder) {
		el.textContent = text;
		el.classList.toggle("is-placeholder", !!isPlaceholder);
	}

	function isOpen(widget) {
		return widget.classList.contains("is-open");
	}

	function clearFloatingStyles(dropdown) {
		dropdown.classList.remove("is-floating");
		dropdown.style.top = "";
		dropdown.style.left = "";
		dropdown.style.width = "";
		dropdown.style.maxHeight = "";
	}

	function dockDropdown(widget, dropdown) {
		if (dropdown.parentNode !== widget) {
			widget.appendChild(dropdown);
		}
		clearFloatingStyles(dropdown);
		dropdown.hidden = true;
	}

	function placeFloatingDropdown(trigger, dropdown) {
		var rect = trigger.getBoundingClientRect();
		var gap = 4;
		var top = rect.bottom + gap;
		var maxHeight = Math.max(160, window.innerHeight - top - 12);

		dropdown.style.top = Math.round(top) + "px";
		dropdown.style.left = Math.round(rect.left) + "px";
		dropdown.style.width = Math.round(rect.width) + "px";
		dropdown.style.maxHeight = Math.round(maxHeight) + "px";
	}

	function floatDropdown(trigger, dropdown) {
		dropdown.classList.add("is-floating");
		dropdown.hidden = false;
		document.body.appendChild(dropdown);
		placeFloatingDropdown(trigger, dropdown);
	}

	function syncOpenDropdowns() {
		if (isOpen(cityWidget)) {
			placeFloatingDropdown(cityTrigger, cityDropdown);
		}
		if (isOpen(warehouseWidget)) {
			placeFloatingDropdown(warehouseTrigger, warehouseDropdown);
		}
	}

	function closeWidget(widget, trigger, dropdown) {
		widget.classList.remove("is-open");
		trigger.setAttribute("aria-expanded", "false");
		dockDropdown(widget, dropdown);
	}

	function closeAllDropdowns() {
		closeWidget(cityWidget, cityTrigger, cityDropdown);
		closeWidget(warehouseWidget, warehouseTrigger, warehouseDropdown);
	}

	function openWidget(widget, trigger, dropdown, searchInput) {
		if (widget.classList.contains("is-disabled") || trigger.disabled) {
			return;
		}
		if (widget === cityWidget) {
			closeWidget(warehouseWidget, warehouseTrigger, warehouseDropdown);
		} else {
			closeWidget(cityWidget, cityTrigger, cityDropdown);
		}
		widget.classList.add("is-open");
		trigger.setAttribute("aria-expanded", "true");
		floatDropdown(trigger, dropdown);
		window.setTimeout(function () {
			searchInput.focus();
		}, 0);
	}

	function toggleWidget(widget, trigger, dropdown, searchInput) {
		if (isOpen(widget)) {
			closeWidget(widget, trigger, dropdown);
			return;
		}
		openWidget(widget, trigger, dropdown, searchInput);
	}

	function renderList(listEl, items, query, onPick, activeIndex) {
		listEl.innerHTML = "";
		if (!items.length) {
			var empty = document.createElement("li");
			empty.className = "np-select__empty";
			empty.textContent = query && query.length >= 2 ? "Нічого не знайдено" : "Почніть вводити назву...";
			listEl.appendChild(empty);
			return;
		}
		items.forEach(function (item, index) {
			var li = document.createElement("li");
			li.setAttribute("role", "option");
			li.innerHTML = highlightMatch(item.label, query);
			if (index === activeIndex) {
				li.classList.add("is-active");
				li.setAttribute("aria-selected", "true");
			}
			li.addEventListener("mousedown", function (event) {
				event.preventDefault();
				onPick(item);
			});
			listEl.appendChild(li);
		});
	}

	function setActiveRow(listEl, index) {
		var items = listEl.querySelectorAll('li[role="option"]');
		if (!items.length) {
			return -1;
		}
		var next = (index + items.length) % items.length;
		items.forEach(function (item, i) {
			item.classList.toggle("is-active", i === next);
			item.setAttribute("aria-selected", i === next ? "true" : "false");
			if (i === next && item.scrollIntoView) {
				item.scrollIntoView({ block: "nearest" });
			}
		});
		return next;
	}

	function resetWarehouse() {
		warehouseItems = [];
		warehouse.value = "";
		warehouseLabel.value = "";
		setTriggerLabel(warehouseValueEl, PLACEHOLDER_WAREHOUSE, true);
		warehouseTrigger.disabled = true;
		warehouseTrigger.classList.add("disabled");
		warehouseWidget.classList.add("is-disabled");
		closeWidget(warehouseWidget, warehouseTrigger, warehouseDropdown);
		warehouseSearch.value = "";
		warehouseListEl.innerHTML = "";
	}

	function fillWarehouses(list) {
		warehouseItems = list || [];
		warehouse.value = "";
		warehouseLabel.value = "";
		setTriggerLabel(warehouseValueEl, PLACEHOLDER_WAREHOUSE, true);
		if (!warehouseItems.length) {
			warehouseTrigger.disabled = true;
			warehouseTrigger.classList.add("disabled");
			warehouseWidget.classList.add("is-disabled");
			return;
		}
		warehouseTrigger.disabled = false;
		warehouseTrigger.classList.remove("disabled");
		warehouseWidget.classList.remove("is-disabled");
		renderList(warehouseListEl, warehouseItems, "", selectWarehouse, 0);
		activeWarehouseIndex = 0;
	}

	function selectWarehouse(item) {
		if (!item) {
			return;
		}
		warehouse.value = item.ref || "";
		warehouseLabel.value = item.label || "";
		setTriggerLabel(warehouseValueEl, item.label, false);
		closeWidget(warehouseWidget, warehouseTrigger, warehouseDropdown);
		clearFieldError("warehouse");
	}

	function selectCity(city) {
		if (!city) {
			return;
		}
		lastSelectedLabel = city.label;
		cityHidden.value = city.label;
		cityRef.value = city.city_ref || city.Ref || "";
		settlementRef.value = city.settlement_ref || city.SettlementRef || city.ref || "";
		setTriggerLabel(cityValueEl, city.label, false);
		closeWidget(cityWidget, cityTrigger, cityDropdown);
		citySearch.value = "";
		activeCities = [];
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
		resetWarehouse();
		setTriggerLabel(warehouseValueEl, "Завантаження…", true);
		post("ch_get_warehouses", {
			city_ref: city_ref,
			settlement_ref: settlement_ref,
		})
			.then(function (json) {
				var list = [];
				if (json && json.success && json.data && Array.isArray(json.data.warehouses)) {
					list = json.data.warehouses;
				}
				if (list.length) {
					warehouseCache[key] = list;
				}
				fillWarehouses(list);
				if (!list.length) {
					setStatus("Відділення не знайдено. Спробуйте інше місто або ще раз.");
				}
			})
			.catch(function () {
				resetWarehouse();
				setStatus("Не вдалося завантажити відділення. Спробуйте ще раз.");
			})
			.then(function () {
				warehouseBusy = false;
				flushIdleQueue("warehouse");
			});
	}

	function searchCities() {
		var query = citySearch.value.trim();
		clearFieldError("city");
		if (query.length < 2) {
			cityBusy = false;
			activeCities = [];
			activeCityIndex = -1;
			renderList(cityList, [], query, selectCity, -1);
			flushIdleQueue("city");
			return;
		}
		if (cityCache[query]) {
			cityBusy = false;
			activeCities = cityCache[query];
			activeCityIndex = activeCities.length ? 0 : -1;
			renderList(cityList, activeCities, query, selectCity, activeCityIndex);
			flushIdleQueue("city");
			return;
		}
		if (abortCity) {
			abortCity.abort();
		}
		var searchGen = ++citySearchGen;
		cityBusy = true;
		abortCity = new AbortController();
		post("ch_search_cities", { query: query }, abortCity.signal)
			.then(function (json) {
				var cities = [];
				if (json && json.success && json.data && Array.isArray(json.data.cities)) {
					cities = json.data.cities;
				}
				if (cities.length) {
					cityCache[query] = cities;
				}
				if (citySearch.value.trim() === query) {
					activeCities = cities;
					activeCityIndex = cities.length ? 0 : -1;
					renderList(cityList, cities, query, selectCity, activeCityIndex);
				}
			})
			.catch(function (err) {
				if (err && err.name === "AbortError") {
					return;
				}
				if (citySearch.value.trim() === query) {
					activeCities = [];
					activeCityIndex = -1;
					renderList(cityList, [], query, selectCity, -1);
					setStatus("Не вдалося знайти населений пункт. Спробуйте ще раз.");
				}
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
		window.clearTimeout(cityTimer);
		cityTimer = window.setTimeout(searchCities, CITY_DEBOUNCE_MS);
	}

	function filterWarehouses() {
		var query = warehouseSearch.value.trim();
		var q = query.toLowerCase();
		var filtered = warehouseItems.filter(function (item) {
			return !q || String(item.label || "").toLowerCase().indexOf(q) !== -1;
		});
		activeWarehouseIndex = filtered.length ? 0 : -1;
		renderList(warehouseListEl, filtered, query, selectWarehouse, activeWarehouseIndex);
	}

	phone.addEventListener("input", function () {
		phone.value = formatPhone(phone.value);
	});

	cityTrigger.addEventListener("click", function () {
		toggleWidget(cityWidget, cityTrigger, cityDropdown, citySearch);
		if (isOpen(cityWidget) && !cityList.children.length) {
			renderList(cityList, [], "", selectCity, -1);
		}
	});

	warehouseTrigger.addEventListener("click", function () {
		if (warehouseTrigger.disabled) {
			return;
		}
		toggleWidget(warehouseWidget, warehouseTrigger, warehouseDropdown, warehouseSearch);
		if (isOpen(warehouseWidget)) {
			warehouseSearch.value = "";
			filterWarehouses();
		}
	});

	citySearch.addEventListener("input", scheduleSearch);
	citySearch.addEventListener("paste", function () {
		window.setTimeout(scheduleSearch, 0);
	});

	warehouseSearch.addEventListener("input", filterWarehouses);

	citySearch.addEventListener("keydown", function (event) {
		if (event.key === "ArrowDown") {
			event.preventDefault();
			activeCityIndex = setActiveRow(cityList, activeCityIndex + 1);
			return;
		}
		if (event.key === "ArrowUp") {
			event.preventDefault();
			activeCityIndex = setActiveRow(cityList, activeCityIndex - 1);
			return;
		}
		if (event.key === "Enter") {
			event.preventDefault();
			if (activeCityIndex >= 0 && activeCities[activeCityIndex]) {
				selectCity(activeCities[activeCityIndex]);
			}
			return;
		}
		if (event.key === "Escape") {
			closeWidget(cityWidget, cityTrigger, cityDropdown);
		}
	});

	warehouseSearch.addEventListener("keydown", function (event) {
		var visible = warehouseListEl.querySelectorAll('li[role="option"]');
		if (event.key === "ArrowDown") {
			event.preventDefault();
			activeWarehouseIndex = setActiveRow(warehouseListEl, activeWarehouseIndex + 1);
			return;
		}
		if (event.key === "ArrowUp") {
			event.preventDefault();
			activeWarehouseIndex = setActiveRow(warehouseListEl, activeWarehouseIndex - 1);
			return;
		}
		if (event.key === "Enter") {
			event.preventDefault();
			if (visible[activeWarehouseIndex]) {
				visible[activeWarehouseIndex].dispatchEvent(new MouseEvent("mousedown", { bubbles: true }));
			}
			return;
		}
		if (event.key === "Escape") {
			closeWidget(warehouseWidget, warehouseTrigger, warehouseDropdown);
		}
	});

	document.addEventListener("mousedown", function (event) {
		var target = event.target;
		var inCity = cityWidget.contains(target) || cityDropdown.contains(target);
		var inWarehouse = warehouseWidget.contains(target) || warehouseDropdown.contains(target);

		if (!inCity) {
			closeWidget(cityWidget, cityTrigger, cityDropdown);
		}
		if (!inWarehouse) {
			closeWidget(warehouseWidget, warehouseTrigger, warehouseDropdown);
		}
	});

	window.addEventListener("resize", syncOpenDropdowns);
	window.addEventListener("scroll", syncOpenDropdowns, true);

	document.addEventListener("keydown", function (event) {
		if (event.key === "Escape") {
			closeAllDropdowns();
		}
	});

	function submitWayforpay(payload) {
		var wfp = payload.wayforpay;
		var thankYou = payload.thank_you_url || cfg.thankYouUrl || "/thank-you/";
		var submit = form.querySelector(".checkout-form__submit");

		if (!wfp) {
			setStatus("Не вдалося підготувати оплату WayForPay.");
			if (submit) {
				submit.disabled = false;
			}
			return;
		}

		var widgetFields = wfp.widget || null;

		if (widgetFields && typeof window.Wayforpay === "function") {
			try {
				var wayforpay = new window.Wayforpay();
				wayforpay.run(
					widgetFields,
					function () {
						window.location.href = thankYou;
					},
					function () {
						setStatus("Оплату відхилено. Спробуйте ще раз або оберіть післяплату.");
						if (submit) {
							submit.disabled = false;
						}
					},
					function () {
						window.location.href = thankYou;
					}
				);
				window.addEventListener("message", function onWfpMessage(event) {
					if (event.data === "WfpWidgetEventApproved") {
						window.removeEventListener("message", onWfpMessage);
						window.location.href = thankYou;
					}
					if (event.data === "WfpWidgetEventClose") {
						window.removeEventListener("message", onWfpMessage);
						if (submit) {
							submit.disabled = false;
						}
						setStatus("");
					}
				});
				return;
			} catch (err) {
				// Fall through to hosted payment page.
			}
		}

		if (!wfp.url || !wfp.fields) {
			setStatus("Не вдалося підготувати оплату WayForPay.");
			if (submit) {
				submit.disabled = false;
			}
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
		closeAllDropdowns();

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
				city: String(cityHidden.value || "").trim(),
				city_ref: cityRef.value,
				settlement_ref: settlementRef.value,
				warehouse_ref: warehouse.value,
				warehouse_label: warehouseLabel.value,
				notes: form.notes ? String(form.notes.value || "").trim() : "",
				payment: (form.querySelector('input[name="payment"]:checked') || {}).value || "",
			};

			post("ch_process_order", payload)
				.then(function (json) {
					if (!json || !json.success) {
						var err = (json && json.data) || {};
						showFieldErrors(err.fields || {});
						setStatus((err && err.message) || "Перевірте поля форми.");
						submit.disabled = false;
						return;
					}
					var data = json.data || {};
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
			if (warehouseBusy) {
				runAfterWarehouseIdle(sendOrder);
				return;
			}
			sendOrder();
		}

		continueSubmit();
	});
})();

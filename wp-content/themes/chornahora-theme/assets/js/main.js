(function () {
	"use strict";

	var navToggle = document.querySelector(".nav-toggle");
	var navClose = document.querySelector(".nav-close");
	var nav = document.querySelector(".site-nav");
	var overlay = document.querySelector(".nav-overlay");
	var headerInner = document.querySelector(".site-header__inner");
	var mq = window.matchMedia("(max-width: 767px)");

	function setOpen(open) {
		if (!nav || !navToggle) {
			return;
		}
		nav.classList.toggle("is-open", open);
		navToggle.classList.toggle("is-open", open);
		document.body.classList.toggle("nav-open", open);
		navToggle.setAttribute("aria-expanded", open ? "true" : "false");
		navToggle.setAttribute("aria-label", open ? "Close" : "Menu");
		if (overlay) {
			overlay.classList.toggle("is-open", open);
		}
	}

	function placeNav() {
		if (!nav) {
			return;
		}
		if (mq.matches) {
			document.body.appendChild(nav);
			if (overlay) {
				document.body.appendChild(overlay);
			}
		} else {
			setOpen(false);
			if (headerInner && nav.parentNode !== headerInner) {
				headerInner.appendChild(nav);
			}
		}
	}

	if (navToggle && nav) {
		navToggle.addEventListener("click", function () {
			setOpen(!nav.classList.contains("is-open"));
		});
	}

	if (navClose) {
		navClose.addEventListener("click", function () {
			setOpen(false);
		});
	}

	if (overlay) {
		overlay.addEventListener("click", function () {
			setOpen(false);
		});
	}

	if (nav) {
		nav.querySelectorAll("a").forEach(function (link) {
			link.addEventListener("click", function () {
				if (mq.matches) {
					setOpen(false);
				}
			});
		});
	}

	placeNav();
	if (mq.addEventListener) {
		mq.addEventListener("change", placeNav);
	} else if (mq.addListener) {
		mq.addListener(placeNav);
	}

	if (typeof Swiper !== "undefined") {
		var slider = document.querySelector(".maps-swiper");
		if (slider) {
			new Swiper(slider, {
				slidesPerView: 1,
				spaceBetween: 16,
				loop: true,
				watchOverflow: true,
				navigation: {
					nextEl: ".maps-slider__next",
					prevEl: ".maps-slider__prev",
				},
				pagination: {
					el: ".maps-slider__dots",
					clickable: true,
				},
				breakpoints: {
					768: {
						slidesPerView: 2,
						spaceBetween: 24,
					},
					1024: {
						slidesPerView: 4,
						spaceBetween: 20,
					},
				},
			});
		}
	}

	document.querySelectorAll("[data-youtube-id]").forEach(function (facade) {
		facade.addEventListener("click", function () {
			var id = facade.getAttribute("data-youtube-id");
			var start = facade.getAttribute("data-start") || "0";
			if (!id) {
				return;
			}
			var iframe = document.createElement("iframe");
			iframe.src =
				"https://www.youtube-nocookie.com/embed/" +
				encodeURIComponent(id) +
				"?autoplay=1&rel=0&start=" +
				encodeURIComponent(start);
			iframe.title = facade.getAttribute("data-title") || "YouTube";
			iframe.allow =
				"accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share";
			iframe.allowFullscreen = true;
			facade.replaceChildren(iframe);
		});
	});
})();

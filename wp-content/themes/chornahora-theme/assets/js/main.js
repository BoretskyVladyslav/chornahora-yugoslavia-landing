(function () {
	"use strict";

	var navToggle = document.querySelector(".nav-toggle");
	var nav = document.querySelector(".site-nav");

	if (navToggle && nav) {
		navToggle.addEventListener("click", function () {
			var open = nav.classList.toggle("is-open");
			navToggle.setAttribute("aria-expanded", open ? "true" : "false");
		});
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

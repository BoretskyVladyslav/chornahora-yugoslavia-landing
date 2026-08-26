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

	var header = document.querySelector(".site-header");
	function syncHeaderScroll() {
		if (!header) {
			return;
		}
		header.classList.toggle("is-scrolled", window.scrollY > 4);
	}
	syncHeaderScroll();
	window.addEventListener("scroll", syncHeaderScroll, { passive: true });

	if (typeof Swiper !== "undefined") {
		var slider = document.querySelector(".maps-swiper");
		if (slider) {
			var mapsSwiper = new Swiper(slider, {
				slidesPerView: 1,
				spaceBetween: 12,
				loop: true,
				watchOverflow: true,
				centeredSlides: false,
				preventClicks: true,
				preventClicksPropagation: true,
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
						spaceBetween: 16,
						centeredSlides: false,
					},
					1024: {
						slidesPerView: 3,
						spaceBetween: 24,
						centeredSlides: false,
					},
				},
			});

			if (typeof Fancybox !== "undefined") {
				var fancyboxOpts = {
					startIndex: 0,
					theme: "dark",
					backdropClick: "close",
					contentClick: "iterateZoom",
					placeFocusBack: false,
					Images: {
						zoom: true,
						Panzoom: {
							maxScale: 5,
							panMode: "drag",
							touch: true,
							mouseMovePan: true,
							wheel: "zoom",
						},
					},
					Toolbar: {
						display: {
							left: [],
							middle: [],
							right: ["zoomIn", "zoomOut", "close"],
						},
					},
				};

				function uniqueMapItems() {
					var seen = {};
					var items = [];
					slider
						.querySelectorAll(".swiper-slide:not(.swiper-slide-duplicate) a[data-fancybox='maps']")
						.forEach(function (link) {
							var src = link.getAttribute("href");
							if (!src || seen[src]) {
								return;
							}
							seen[src] = true;
							var img = link.querySelector("img");
							items.push({
								src: src,
								type: "image",
								caption: link.getAttribute("data-caption") || (img ? img.alt : "") || "",
							});
						});
					return items;
				}

				slider.addEventListener("click", function (event) {
					var link = event.target.closest("a[data-fancybox='maps']");
					if (!link) {
						return;
					}
					event.preventDefault();
					if (mapsSwiper.animating || mapsSwiper.allowClick === false) {
						return;
					}
					var items = uniqueMapItems();
					var start = items.findIndex(function (item) {
						return item.src === link.getAttribute("href");
					});
					fancyboxOpts.startIndex = start < 0 ? 0 : start;
					Fancybox.show(items, fancyboxOpts);
				});
			}
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

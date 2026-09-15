(function () {
	'use strict';

	if (window.FeedivoFrontend) {
		return;
	}

	function i18n(key, fallback) {
		var table = (window.feedivoFront && window.feedivoFront.i18n) || {};
		return table[key] || fallback;
	}

	var gallery = { urls: [], index: 0, alt: '' };
	var lastFocus = null;
	var scrollLock = '';
	var wired = false;

	var CAROUSEL_INTERVAL = 4500;
	var CAROUSEL_HOLD = 7000;
	var SNAP_EDGE = 2;

	var PLATFORM_ICONS = {
		instagram: '<svg class="bi" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 0C5.829 0 5.556.01 4.703.048 3.85.088 3.269.222 2.76.42a3.9 3.9 0 0 0-1.417.923A3.9 3.9 0 0 0 .42 2.76C.222 3.268.087 3.85.048 4.7.01 5.555 0 5.827 0 8.001c0 2.172.01 2.444.048 3.297.04.852.174 1.433.372 1.942.205.526.478.972.923 1.417.444.445.89.719 1.416.923.51.198 1.09.333 1.942.372C5.555 15.99 5.827 16 8 16s2.444-.01 3.298-.048c.851-.04 1.434-.174 1.943-.372a3.9 3.9 0 0 0 1.416-.923c.445-.445.718-.891.923-1.417.197-.509.332-1.09.372-1.942C15.99 10.445 16 10.173 16 8s-.01-2.445-.048-3.299c-.04-.851-.175-1.433-.372-1.941a3.9 3.9 0 0 0-.923-1.417A3.9 3.9 0 0 0 13.24.42c-.51-.198-1.092-.333-1.943-.372C10.443.01 10.172 0 7.998 0zm-.717 1.442h.718c2.136 0 2.389.007 3.232.046.78.035 1.204.166 1.486.275.373.145.64.319.92.599s.453.546.598.92c.11.281.24.705.275 1.485.039.843.047 1.096.047 3.231s-.008 2.389-.047 3.232c-.035.78-.166 1.203-.275 1.485a2.5 2.5 0 0 1-.599.919c-.28.28-.546.453-.92.598-.28.11-.704.24-1.485.276-.843.038-1.096.047-3.232.047s-2.39-.009-3.233-.047c-.78-.036-1.203-.166-1.485-.276a2.5 2.5 0 0 1-.92-.598 2.5 2.5 0 0 1-.6-.92c-.109-.281-.24-.705-.275-1.485-.038-.843-.046-1.096-.046-3.233s.008-2.388.046-3.231c.036-.78.166-1.204.276-1.486.145-.373.319-.64.599-.92s.546-.453.92-.598c.282-.11.705-.24 1.485-.276.738-.034 1.024-.044 2.515-.045zm4.988 1.328a.96.96 0 1 0 0 1.92.96.96 0 0 0 0-1.92m-4.27 1.122a4.109 4.109 0 1 0 0 8.217 4.109 4.109 0 0 0 0-8.217m0 1.441a2.667 2.667 0 1 1 0 5.334 2.667 2.667 0 0 1 0-5.334"/></svg>',
		facebook: '<svg class="bi" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M16 8.049c0-4.446-3.582-8.05-8-8.05C3.58 0-.002 3.603-.002 8.05c0 4.017 2.926 7.347 6.75 7.951v-5.625h-2.03V8.05H6.75V6.275c0-2.017 1.195-3.131 3.022-3.131.876 0 1.791.157 1.791.157v1.98h-1.009c-.993 0-1.303.621-1.303 1.258v1.51h2.218l-.354 2.326H9.25V16c3.824-.604 6.75-3.934 6.75-7.951"/></svg>',
		threads: '<svg class="bi" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M6.321 6.016c-.27-.18-1.166-.802-1.166-.802.756-1.081 1.753-1.502 3.132-1.502.975 0 1.803.327 2.394.948s.928 1.509 1.005 2.644q.492.207.905.484c1.109.745 1.719 1.86 1.719 3.137 0 2.716-2.226 5.075-6.256 5.075C4.594 16 1 13.987 1 7.994 1 2.034 4.482 0 8.044 0 9.69 0 13.55.243 15 5.036l-1.36.353C12.516 1.974 10.163 1.43 8.006 1.43c-3.565 0-5.582 2.171-5.582 6.79 0 4.143 2.254 6.343 5.63 6.343 2.777 0 4.847-1.443 4.847-3.556 0-1.438-1.208-2.127-1.27-2.127-.236 1.234-.868 3.31-3.644 3.31-1.618 0-3.013-1.118-3.013-2.582 0-2.09 1.984-2.847 3.55-2.847.586 0 1.294.04 1.663.114 0-.637-.54-1.728-1.9-1.728-1.25 0-1.566.405-1.967.868ZM8.716 8.19c-2.04 0-2.304.87-2.304 1.416 0 .878 1.043 1.168 1.6 1.168 1.02 0 2.067-.282 2.232-2.423a6.2 6.2 0 0 0-1.528-.161"/></svg>',
		pinterest: '<svg class="bi" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 0a8 8 0 0 0-2.915 15.452c-.07-.633-.134-1.606.027-2.297.146-.625.938-3.977.938-3.977s-.239-.479-.239-1.187c0-1.113.645-1.943 1.448-1.943.682 0 1.012.512 1.012 1.127 0 .686-.437 1.712-.663 2.663-.188.796.4 1.446 1.185 1.446 1.422 0 2.515-1.5 2.515-3.664 0-1.915-1.377-3.254-3.342-3.254-2.276 0-3.612 1.707-3.612 3.471 0 .688.265 1.425.595 1.826a.24.24 0 0 1 .056.23c-.061.252-.196.796-.222.907-.035.146-.116.177-.268.107-1-.465-1.624-1.926-1.624-3.1 0-2.523 1.834-4.84 5.286-4.84 2.775 0 4.932 1.977 4.932 4.62 0 2.757-1.739 4.976-4.151 4.976-.811 0-1.573-.421-1.834-.919l-.498 1.902c-.181.695-.669 1.566-.995 2.097A8 8 0 1 0 8 0"/></svg>',
		youtube: '<svg class="bi" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8.051 1.999h.089c.822.003 4.987.033 6.11.335a2.01 2.01 0 0 1 1.415 1.42c.101.38.172.883.22 1.402l.01.104.022.26.008.104c.065.914.073 1.77.074 1.957v.075c-.001.194-.01 1.108-.082 2.06l-.008.105-.009.104c-.05.572-.124 1.14-.235 1.558a2.01 2.01 0 0 1-1.415 1.42c-1.16.312-5.569.334-6.18.335h-.142c-.309 0-1.587-.006-2.927-.052l-.17-.006-.087-.004-.171-.007-.171-.007c-1.11-.049-2.167-.128-2.654-.26a2.01 2.01 0 0 1-1.415-1.419c-.111-.417-.185-.986-.235-1.558L.09 9.82l-.008-.104A31 31 0 0 1 0 7.68v-.123c.002-.215.01-.958.064-1.778l.007-.103.003-.052.008-.104.022-.26.01-.104c.048-.519.119-1.023.22-1.402a2.01 2.01 0 0 1 1.415-1.42c.487-.13 1.544-.21 2.654-.26l.17-.007.172-.006.086-.003.171-.007A100 100 0 0 1 7.858 2zM6.4 5.209v4.818l4.157-2.408z"/></svg>',
		website: '<svg class="bi" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8m7.5-6.923c-.67.204-1.335.82-1.887 1.855q-.215.403-.395.872c.705.157 1.472.257 2.282.287zM4.249 3.539q.214-.577.481-1.078a7 7 0 0 1 .597-.933A7 7 0 0 0 3.051 3.05q.544.277 1.198.49zM3.509 7.5c.036-1.07.188-2.087.436-3.008a9 9 0 0 1-1.565-.667A6.96 6.96 0 0 0 1.018 7.5zm1.4-2.741a12.3 12.3 0 0 0-.4 2.741H7.5V5.091c-.91-.03-1.783-.145-2.591-.332M8.5 5.09V7.5h2.99a12.3 12.3 0 0 0-.399-2.741c-.808.187-1.681.301-2.591.332zM4.51 8.5c.035.987.176 1.914.399 2.741A13.6 13.6 0 0 1 7.5 10.91V8.5zm3.99 0v2.409c.91.03 1.783.145 2.591.332.223-.827.364-1.754.4-2.741zm-3.282 3.696q.18.469.395.872c.552 1.035 1.218 1.65 1.887 1.855V11.91c-.81.03-1.577.13-2.282.287zm.11 2.276a7 7 0 0 1-.598-.933 9 9 0 0 1-.481-1.079 8.4 8.4 0 0 0-1.198.49 7 7 0 0 0 2.276 1.522zm-1.383-2.964A13.4 13.4 0 0 1 3.508 8.5h-2.49a6.96 6.96 0 0 0 1.362 3.675c.47-.258.995-.482 1.565-.667m6.728 2.964a7 7 0 0 0 2.275-1.521 8.4 8.4 0 0 0-1.197-.49 9 9 0 0 1-.481 1.078 7 7 0 0 1-.597.933M8.5 11.909v3.014c.67-.204 1.335-.82 1.887-1.855q.216-.403.395-.872A12.6 12.6 0 0 0 8.5 11.91zm3.555-.401c.57.185 1.095.409 1.565.667A6.96 6.96 0 0 0 14.982 8.5h-2.49a13.4 13.4 0 0 1-.437 3.008M14.982 7.5a6.96 6.96 0 0 0-1.362-3.675c-.47.258-.995.482-1.565.667.248.92.4 1.938.437 3.008zM11.27 2.461q.266.502.482 1.078a8.4 8.4 0 0 0 1.196-.49 7 7 0 0 0-2.275-1.52c.218.283.418.597.597.932m-.488 1.343a8 8 0 0 0-.395-.872C9.835 1.897 9.17 1.282 8.5 1.077V4.09c.81-.03 1.577-.13 2.282-.287z"/></svg>'
	};

	function platformLabel(platform) {
		if (platform === 'youtube') {
			return 'YouTube';
		}
		return platform ? platform.charAt(0).toUpperCase() + platform.slice(1) : '';
	}

	function cardPayload(card) {
		var el = card.querySelector('script.feedivo-post');
		if (!el && card.closest) {
			var row = card.closest('.post-row');
			el = row ? row.querySelector('script.feedivo-post') : null;
		}
		try {
			return JSON.parse(el ? el.textContent : '{}') || {};
		} catch (e) {
			return {};
		}
	}

	function getLightbox() {
		var box = document.querySelector('.feedivo-lightbox');
		if (!box || wired) {
			return box;
		}
		wired = true;

		box.addEventListener('click', function (event) {
			if (event.target === box || event.target.classList.contains('feedivo-lightbox-close')) {
				closeLightbox();
			}
			if (event.target.classList.contains('feedivo-lightbox-prev')) {
				stepGallery(-1);
			}
			if (event.target.classList.contains('feedivo-lightbox-next')) {
				stepGallery(1);
			}
		});
		document.addEventListener('keydown', function (event) {
			if (box.hidden) {
				return;
			}
			if (event.key === 'Escape') {
				closeLightbox();
			}
			if (event.key === 'ArrowLeft') {
				stepGallery(-1);
			}
			if (event.key === 'ArrowRight') {
				stepGallery(1);
			}
		});

		return box;
	}

	function stepGallery(direction) {
		var box = getLightbox();
		if (!box || gallery.urls.length < 2) {
			return;
		}
		gallery.index = (gallery.index + direction + gallery.urls.length) % gallery.urls.length;
		renderGallery(box);
	}

	function renderGallery(box) {
		var img = box.querySelector('.feedivo-lightbox-media > img');
		var count = box.querySelector('.feedivo-lightbox-count');
		img.src = gallery.urls[gallery.index] || '';

		var alt = gallery.alt || i18n('post', 'Beitrag');
		img.alt = gallery.urls.length > 1
			? alt + ' — ' + i18n('image', 'Bild') + ' ' + (gallery.index + 1)
				+ ' ' + i18n('of', 'von') + ' ' + gallery.urls.length
			: alt;
		count.textContent = (gallery.index + 1) + ' / ' + gallery.urls.length;
	}

	function feedOf(card) {
		var embed = card && card.closest ? card.closest('.feed-embed') : null;

		var layout = card && card.closest ? card.closest('.feed-layout') : null;
		var von = layout || embed;
		var off = embed ? embed.getAttribute('data-feed-hide') : '';
		var stil = von ? getComputedStyle(von) : null;
		return {
			accent: stil ? stil.getPropertyValue('--feed-accent').trim() : '',
			radius: stil ? stil.getPropertyValue('--feed-radius').trim() : '',
			shows: function (part) {
				return !off || (',' + off + ',').indexOf(',' + part + ',') === -1;
			}
		};
	}

	function spiegle(box, name, wert) {
		if (wert) {
			box.style.setProperty(name, wert);
		} else {
			box.style.removeProperty(name);
		}
	}

	function openLightbox(card) {
		var box = getLightbox();
		if (!box) {
			return;
		}
		var badge = box.querySelector('.feedivo-lightbox-badge');
		var author = box.querySelector('.feedivo-lightbox-author');
		var caption = box.querySelector('.feedivo-lightbox-caption');
		var date = box.querySelector('.feedivo-lightbox-date');
		var link = box.querySelector('.feedivo-lightbox-link');
		var play = box.querySelector('.feedivo-lightbox-play');
		var details = box.querySelector('.feedivo-lightbox-details');

		var feed = feedOf(card);
		spiegle(box, '--feed-accent', feed.accent);
		spiegle(box, '--feed-radius', feed.radius);

		var d = cardPayload(card);
		var platform = d.platform || '';
		var permalink = d.permalink || '';
		var isVideo = d.type === 'video';
		var label = platformLabel(platform);

		gallery.urls = Array.isArray(d.gallery) ? d.gallery.slice() : [];
		gallery.index = 0;
		gallery.alt = d.alt || '';
		if (!gallery.urls.length) {
			gallery.urls = [(isVideo ? (d.thumbnail || d.media) : (d.media || d.thumbnail)) || ''];
		}

		var multiple = gallery.urls.length > 1;
		box.querySelector('.feedivo-lightbox-prev').hidden = !multiple;
		box.querySelector('.feedivo-lightbox-next').hidden = !multiple;
		box.querySelector('.feedivo-lightbox-count').hidden = !multiple;
		renderGallery(box);

		if (feed.shows('platform') && platform && PLATFORM_ICONS[platform]) {
			badge.className = 'feedivo-lightbox-badge badge-platform-' + platform;
			badge.innerHTML = PLATFORM_ICONS[platform] + '<span>' + label + '</span>';
			badge.hidden = false;
		} else {
			badge.className = 'feedivo-lightbox-badge';
			badge.hidden = true;
		}

		var authorText = feed.shows('author') && d.author ? '@' + d.author : '';
		var captionText = feed.shows('caption') ? (d.caption || '') : '';
		var dateText = feed.shows('date') ? (d.date || '') : '';
		author.textContent = authorText;
		author.hidden = authorText === '';
		caption.textContent = captionText;
		caption.hidden = captionText === '';
		date.textContent = dateText;
		date.hidden = dateText === '';

		var video = box.querySelector('.feedivo-lightbox-video');
		var embedBox = box.querySelector('.feedivo-lightbox-embed');
		var img = box.querySelector('.feedivo-lightbox-media > img');
		var videoUrl = d.video || '';
		var embedUrl = d.embed || '';
		embedBox.hidden = true;
		embedBox.innerHTML = '';
		if (embedUrl) {
			img.hidden = true;
			play.hidden = true;
			video.hidden = true;
			video.removeAttribute('src');
			box.querySelector('.feedivo-lightbox-prev').hidden = true;
			box.querySelector('.feedivo-lightbox-next').hidden = true;
			box.querySelector('.feedivo-lightbox-count').hidden = true;
			var frame = document.createElement('iframe');
			frame.src = embedUrl + (embedUrl.indexOf('?') === -1 ? '?' : '&') + 'autoplay=1&rel=0';
			frame.setAttribute('allow', 'autoplay; encrypted-media; picture-in-picture');
			frame.setAttribute('allowfullscreen', '');
			frame.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
			frame.title = label ? label + '-Video' : 'Video';
			embedBox.appendChild(frame);
			embedBox.hidden = false;
		} else if (isVideo && videoUrl) {
			img.hidden = true;
			play.hidden = true;
			box.querySelector('.feedivo-lightbox-prev').hidden = true;
			box.querySelector('.feedivo-lightbox-next').hidden = true;
			box.querySelector('.feedivo-lightbox-count').hidden = true;
			video.src = videoUrl;
			video.poster = d.thumbnail || d.media || '';
			video.hidden = false;

			var playing = video.play();
			if (playing && playing.catch) { playing.catch(function () {}); }
		} else {
			video.hidden = true;
			video.removeAttribute('src');
			img.hidden = false;
			if (isVideo && permalink) {
				play.href = permalink;
				play.hidden = false;
			} else {
				play.hidden = true;
			}
		}

		if (feed.shows('source') && permalink) {
			link.href = permalink;
			link.textContent = label
				? i18n('viewOn', 'Auf %s ansehen').replace('%s', label)
				: i18n('viewOriginal', 'Original ansehen');
			link.hidden = false;
		} else {
			link.hidden = true;
		}

		if (details) {
			details.hidden = badge.hidden && author.hidden && caption.hidden && date.hidden && link.hidden;
		}

		lastFocus = document.activeElement;

		scrollLock = document.body.style.overflow;
		document.body.style.setProperty('overflow', 'hidden', 'important');
		document.body.classList.add('feedivo-lightbox-open');
		box.hidden = false;
		var closeBtn = box.querySelector('.feedivo-lightbox-close');
		if (closeBtn) { closeBtn.focus(); }
	}

	function closeLightbox() {
		var box = document.querySelector('.feedivo-lightbox');
		if (box) {
			box.hidden = true;
			var v = box.querySelector('.feedivo-lightbox-video');
			if (v) { v.pause(); v.removeAttribute('src'); v.load(); }

			var e = box.querySelector('.feedivo-lightbox-embed');
			if (e) { e.hidden = true; e.innerHTML = ''; }
		}
		document.body.style.overflow = scrollLock;
		document.body.classList.remove('feedivo-lightbox-open');
		if (lastFocus && typeof lastFocus.focus === 'function') { lastFocus.focus(); }
		lastFocus = null;
	}

	function initMasonry(root) {
		var scope = root || document;
		var grids = scope.querySelectorAll('.feed-layout--masonry');
		Array.prototype.forEach.call(grids, function (grid) {

			grid.style.setProperty('grid-auto-rows', '1px', 'important');
			grid.style.setProperty('row-gap', '0', 'important');

			function relayout() {
				var gap = parseFloat(getComputedStyle(grid).columnGap) || 0;
				Array.prototype.forEach.call(grid.querySelectorAll('.post-card'), function (card) {
					var h = card.getBoundingClientRect().height;
					card.style.gridRowEnd = 'span ' + Math.ceil(h + gap);
				});
			}

			var pending = null;
			function schedule() {
				if (pending) { return; }
				pending = requestAnimationFrame(function () { pending = null; relayout(); });
			}

			if (!grid.getAttribute('data-masonry-on')) {
				grid.setAttribute('data-masonry-on', '1');
				if (typeof ResizeObserver === 'function') {
					grid._feedivoMasonryRO = new ResizeObserver(schedule);
					grid._feedivoMasonryRO.observe(grid);
				} else {
					window.addEventListener('resize', schedule);
				}
			}

			var ro = grid._feedivoMasonryRO;
			if (ro) {
				Array.prototype.forEach.call(grid.querySelectorAll('.post-card'), function (card) {
					ro.observe(card);
				});
			}
			Array.prototype.forEach.call(grid.querySelectorAll('img'), function (img) {
				if (img.complete || img.getAttribute('data-masonry-img')) { return; }
				img.setAttribute('data-masonry-img', '1');
				img.addEventListener('load', schedule);
				img.addEventListener('error', schedule);
			});

			relayout();
		});
	}

	function initCarousels(root) {
		var scope = root || document;
		var wraps = scope.querySelectorAll('.feed-carousel');
		Array.prototype.forEach.call(wraps, function (wrap) {
			var track = wrap.querySelector('.feed-layout--carousel');
			var prev = wrap.querySelector('.feed-carousel-prev');
			var next = wrap.querySelector('.feed-carousel-next');
			if (!track || !prev || !next || wrap.getAttribute('data-carousel-on')) {
				return;
			}
			wrap.setAttribute('data-carousel-on', '1');

			var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
			var behavior = reduce ? 'auto' : 'smooth';
			var direction = 1;
			var lastFrom = -1;
			var positions = null, counted = -1, measured = -1;

			function snapPositions() {
				var cards = track.querySelectorAll('.post-card');
				if (positions && cards.length === counted && track.scrollWidth === measured) {
					return positions;
				}
				counted = cards.length;
				measured = track.scrollWidth;
				var base = track.getBoundingClientRect().left;
				var pos = track.scrollLeft;
				var max = Math.max(0, track.scrollWidth - track.clientWidth);
				var list = [];
				Array.prototype.forEach.call(cards, function (card) {
					var left = Math.round(Math.min(max, Math.max(0, pos + card.getBoundingClientRect().left - base)));
					if (!list.length || left - list[list.length - 1] > SNAP_EDGE) {
						list.push(left);
					}
				});
				positions = list;
				return list;
			}

			function snapTarget(dir) {
				var list = snapPositions();
				var pos = track.scrollLeft;
				for (var i = 0; i < list.length; i++) {
					var left = dir > 0 ? list[i] : list[list.length - 1 - i];
					if (dir > 0 ? left > pos + SNAP_EDGE : left < pos - SNAP_EDGE) {
						return left;
					}
				}
				return null;
			}

			function step(dir) {
				var target = snapTarget(dir);
				if (target !== null) {
					track.scrollTo({ left: target, behavior: behavior });
				}
				return target;
			}

			function overflowing() { return track.scrollWidth > track.clientWidth + SNAP_EDGE; }

			function updateArrows() {
				var on = overflowing();
				prev.hidden = next.hidden = !on;
				prev.disabled = !on || snapTarget(-1) === null;
				next.disabled = !on || snapTarget(1) === null;
			}

			var queued = false;
			function queueArrows() {
				if (queued) { return; }
				queued = true;
				requestAnimationFrame(function () { queued = false; updateArrows(); });
			}
			function remeasure() { positions = null; queueArrows(); }

			track.addEventListener('scroll', queueArrows, { passive: true });

			var ro = null, io = null;
			if (typeof ResizeObserver === 'function') {
				ro = new ResizeObserver(remeasure);
				ro.observe(track);
			} else {
				window.addEventListener('resize', remeasure);
			}

			Array.prototype.forEach.call(track.querySelectorAll('img'), function (img) {
				if (!img.complete) { img.addEventListener('load', remeasure); }
			});
			updateArrows();

			var timer = null;
			var hovering = false;
			var holdUntil = 0;
			var onScreen = true;

			function hold() { holdUntil = Date.now() + CAROUSEL_HOLD; }
			function stop() { clearTimeout(timer); timer = null; }

			function blocked() {
				return hovering
					|| !onScreen
					|| document.hidden
					|| Date.now() < holdUntil
					|| document.body.classList.contains('feedivo-lightbox-open')
					|| keyboardFocusInside()
					|| !overflowing();
			}

			function keyboardFocusInside() {
				try {
					return !!wrap.querySelector(':focus-visible');
				} catch (e) {
					return false;
				}
			}

			function advance() {
				var from = track.scrollLeft;
				if (Math.abs(from - lastFrom) <= SNAP_EDGE) { direction = -direction; }
				lastFrom = from;
				if (step(direction) === null) {
					direction = -direction;
					step(direction);
				}
			}

			function run() {
				timer = null;

				if (!document.body.contains(track)) {
					if (ro) { ro.disconnect(); }
					if (io) { io.disconnect(); }
					return;
				}
				if (!blocked()) { advance(); }
				timer = setTimeout(run, CAROUSEL_INTERVAL);
			}

			var autoplay = !reduce && track.getAttribute('data-feed-autoplay') !== 'off';
			function resume() { if (autoplay && timer === null) { timer = setTimeout(run, CAROUSEL_INTERVAL); } }

			prev.addEventListener('click', function () { step(-1); hold(); });
			next.addEventListener('click', function () { step(1); hold(); });

			wrap.addEventListener('pointerenter', function (event) {
				if (event.pointerType === 'mouse') { hovering = true; stop(); }
			});
			wrap.addEventListener('pointerleave', function (event) {
				if (event.pointerType === 'mouse') { hovering = false; resume(); }
			});

			track.addEventListener('pointerdown', hold);
			track.addEventListener('touchstart', hold, { passive: true });
			track.addEventListener('keydown', hold);
			track.addEventListener('wheel', function (event) {
				if (Math.abs(event.deltaX) > Math.abs(event.deltaY)) { hold(); }
			}, { passive: true });

			if (autoplay) {
				if (typeof IntersectionObserver === 'function') {
					io = new IntersectionObserver(function (entries) {
						onScreen = entries[entries.length - 1].isIntersecting;
					});
					io.observe(track);
				}
				timer = setTimeout(run, CAROUSEL_INTERVAL);
			}
		});
	}

	function loadMoreWordPress(button, wrap) {
		var settings = window.feedivoFront || {};
		if (!settings.ajaxUrl) {
			return;
		}

		var page = parseInt(wrap.getAttribute('data-feedivo-page') || '1', 10) + 1;
		var label = button.textContent;

		button.disabled = true;
		button.textContent = i18n('loading', 'Lädt…');

		var body = new FormData();
		body.append('action', 'feedivo_load_more');
		body.append('nonce', settings.nonce || '');
		body.append('term', wrap.getAttribute('data-feedivo-term') || '0');
		body.append('page', String(page));
		body.append('overrides', wrap.getAttribute('data-feedivo-overrides') || '{}');

		fetch(settings.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
			.then(function (response) { return response.json(); })
			.then(function (result) {
				if (!result || !result.success) {

					if (result && result.data && result.data.code === 'nonce_expired' && !sessionStorage.getItem('feedivoReloaded')) {
						sessionStorage.setItem('feedivoReloaded', '1');
						window.location.reload();
						return;
					}
					button.disabled = false;
					button.textContent = label;
					return;
				}

				sessionStorage.removeItem('feedivoReloaded');
				wrap.querySelector('.feedivo-items').insertAdjacentHTML('beforeend', result.data.html || '');
				wrap.setAttribute('data-feedivo-page', String(page));

				initMasonry(wrap);

				if (result.data.has_more) {
					button.disabled = false;
					button.textContent = label;
				} else {

					var wrapEl = button.closest('.feed-loadmore-wrap') || button;
					wrapEl.parentNode.removeChild(wrapEl);
				}
			})
			.catch(function () {
				button.disabled = false;
				button.textContent = label;
			});
	}

	document.addEventListener('click', function (event) {
		if (!event.target.closest) {
			return;
		}

		var facade = event.target.closest('[data-feedivo-embed]');
		if (facade) {
			event.preventDefault();
			var url = facade.getAttribute('data-feedivo-embed') || '';
			var holder = document.createElement('div');
			holder.className = 'feedivo-single-embed';
			var frame = document.createElement('iframe');
			frame.src = url + (url.indexOf('?') === -1 ? '?' : '&') + 'autoplay=1&rel=0';
			frame.setAttribute('allow', 'autoplay; encrypted-media; picture-in-picture');
			frame.setAttribute('allowfullscreen', '');
			frame.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
			frame.title = facade.getAttribute('data-feedivo-embed-title') || i18n('play', 'Video abspielen');
			holder.appendChild(frame);
			facade.parentNode.replaceChild(holder, facade);
			return;
		}

		var more = event.target.closest('.feed-loadmore');
		if (more) {
			var feed = more.closest('.feedivo-feed[data-feedivo-term]');
			if (feed) {
				event.preventDefault();
				loadMoreWordPress(more, feed);
				return;
			}
		}

		var card = event.target.closest('[data-feedivo-lightbox]');
		if (card) {
			event.preventDefault();
			openLightbox(card);
		}
	});

	function initAll(root) {
		initCarousels(root);
		initMasonry(root);
	}

	if (document.readyState !== 'loading') {
		initAll(document);
	} else {
		document.addEventListener('DOMContentLoaded', function () { initAll(document); });
	}

	window.FeedivoFrontend = {
		initCarousels: initCarousels,
		initMasonry: initMasonry,
		init: initAll
	};
})();

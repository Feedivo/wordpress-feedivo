(function () {
	'use strict';

	document.addEventListener('submit', function (event) {
		var form = event.target;
		if (!form || typeof form.getAttribute !== 'function') {
			return;
		}

		var message = form.getAttribute('data-feedivo-confirm');
		if (message && !window.confirm(message)) {
			event.preventDefault();
		}
	});
})();

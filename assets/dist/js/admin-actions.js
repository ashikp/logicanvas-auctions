/**
 * Admin action buttons → real centered overlay modal.
 */
(function () {
	'use strict';

	function qs(sel, root) {
		return (root || document).querySelector(sel);
	}

	function parseFields(raw) {
		try {
			var data = JSON.parse(raw || '{}');
			return data && typeof data === 'object' ? data : {};
		} catch (e) {
			return {};
		}
	}

	function getModal() {
		var modal = qs('#wcap-action-modal');
		if (!modal) {
			return null;
		}
		// Always park on <body> so position:fixed is viewport-relative.
		if (modal.parentNode !== document.body) {
			document.body.appendChild(modal);
		}
		return modal;
	}

	function openModal(btn) {
		var modal = getModal();
		if (!modal) {
			return;
		}

		var action = btn.getAttribute('data-wcap-action') || '';
		var title = btn.getAttribute('data-title') || btn.getAttribute('data-label') || '';
		var confirm = btn.getAttribute('data-confirm') || '';
		var needReason = btn.getAttribute('data-need-reason') === '1';
		var reasonRequired = btn.getAttribute('data-reason-required') === '1';
		var reasonLabel = btn.getAttribute('data-reason-label') || 'Reason';
		var submitLabel = btn.getAttribute('data-label') || 'Confirm';
		var fields = parseFields(btn.getAttribute('data-fields'));

		var actionField = qs('[data-wcap-action-field]', modal);
		var extra = qs('[data-wcap-action-extra]', modal);
		var titleEl = qs('[data-wcap-action-title]', modal);
		var confirmEl = qs('[data-wcap-action-confirm]', modal);
		var reasonWrap = qs('[data-wcap-action-reason-wrap]', modal);
		var reasonLabelEl = qs('[data-wcap-action-reason-label]', modal);
		var reason = qs('[data-wcap-action-reason]', modal);
		var submit = qs('[data-wcap-action-submit]', modal);

		if (actionField) {
			actionField.value = action;
		}
		if (titleEl) {
			titleEl.textContent = title;
		}
		if (submit) {
			submit.textContent = submitLabel;
		}

		if (extra) {
			extra.innerHTML = '';
			Object.keys(fields).forEach(function (key) {
				var input = document.createElement('input');
				input.type = 'hidden';
				input.name = key;
				input.value = String(fields[key] == null ? '' : fields[key]);
				extra.appendChild(input);
			});
		}

		if (confirmEl) {
			if (confirm) {
				confirmEl.hidden = false;
				confirmEl.textContent = confirm;
			} else {
				confirmEl.hidden = true;
				confirmEl.textContent = '';
			}
		}

		if (reasonWrap && reason) {
			if (needReason) {
				reasonWrap.hidden = false;
				if (reasonLabelEl) {
					reasonLabelEl.textContent = reasonLabel;
				}
				reason.required = reasonRequired;
				reason.value = '';
				window.setTimeout(function () {
					reason.focus();
				}, 40);
			} else {
				reasonWrap.hidden = true;
				reason.required = false;
				reason.value = '';
			}
		}

		modal.classList.add('is-open');
		modal.setAttribute('aria-hidden', 'false');
		document.body.classList.add('wcap-action-modal-open');
	}

	function closeModal() {
		var modal = getModal();
		if (!modal) {
			return;
		}
		modal.classList.remove('is-open');
		modal.setAttribute('aria-hidden', 'true');
		document.body.classList.remove('wcap-action-modal-open');
	}

	document.addEventListener('click', function (event) {
		var btn = event.target.closest('[data-wcap-admin-action]');
		if (btn) {
			event.preventDefault();
			openModal(btn);
			return;
		}
		if (event.target.closest('[data-wcap-action-close]')) {
			event.preventDefault();
			closeModal();
		}
	});

	document.addEventListener('keydown', function (event) {
		if (event.key === 'Escape') {
			closeModal();
		}
	});

	document.addEventListener('DOMContentLoaded', function () {
		getModal();
		var form = qs('#wcap-action-modal form');
		if (!form) {
			return;
		}
		form.addEventListener('submit', function (event) {
			var reason = qs('[data-wcap-action-reason]', form);
			var wrap = qs('[data-wcap-action-reason-wrap]', form);
			if (wrap && !wrap.hidden && reason && reason.required && !String(reason.value || '').trim()) {
				event.preventDefault();
				reason.focus();
			}
		});
	});
})();

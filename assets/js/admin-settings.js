(function ($) {
	'use strict';

	const saveButton = $('.woocommerce-save-button');
	const rowOf = (selector) => $(selector).closest('tr');

	const toggleByValue = (selector, value, targets) => {
		const isMatch = $(selector).val() === value;
		targets.forEach((target) => {
			const row = rowOf(target);
			row.toggleClass('wbdpp-is-hidden', !isMatch);
			row.toggleClass('wbdpp-row-active', isMatch);
		});
	};

	const toggleByChecked = (selector, targets) => {
		const checked = $(selector).is(':checked');
		targets.forEach((target) => {
			const row = rowOf(target);
			row.toggleClass('wbdpp-is-hidden', !checked);
			row.toggleClass('wbdpp-row-active', checked);
		});
	};

	const refreshVisibility = () => {
		toggleByValue('#wbdpp_default_due_type', 'fixed', ['#wbdpp_default_due_fixed_date']);
		toggleByValue('#wbdpp_default_due_type', 'relative', ['#wbdpp_default_due_relative']);

		toggleByChecked('#wbdpp_enable_booking_due', ['#wbdpp_booking_due_days_before']);

		toggleByValue('#wbdpp_deposit_refund_policy', 'partial', ['#wbdpp_deposit_refund_partial_percent']);
		toggleByChecked('#wbdpp_auto_cancel_overdue_enabled', ['#wbdpp_auto_cancel_overdue_days']);
	};

	const injectDepositMessagePreview = () => {
		const field = $('#wbdpp_ui_deposit_message');
		if (!field.length) {
			return;
		}

		const previewId = 'wbdpp-deposit-message-preview';
		if (!$('#' + previewId).length) {
			field.after('<div id="' + previewId + '" class="wbdpp-live-preview" aria-live="polite"></div>');
		}

		const value = field.val() || '';
		const sample = value
			.split('{deposit}').join('$120.00')
			.split('{remaining}').join('$480.00')
			.split('{due_date}').join('March 20, 2026');

		$('#' + previewId).text('Preview: ' + sample);
	};

	const injectReminderChips = () => {
		const field = $('#wbdpp_reminder_offsets');
		if (!field.length) {
			return;
		}

		const wrapId = 'wbdpp-reminder-chip-wrap';
		if (!$('#' + wrapId).length) {
			field.after('<div id="' + wrapId + '" class="wbdpp-reminder-chip-wrap" aria-live="polite"></div>');
		}

		const offsets = String(field.val() || '')
			.split(',')
			.map((item) => parseInt(item.trim(), 10))
			.filter((item) => Number.isInteger(item) && item >= 0)
			.sort((a, b) => b - a);

		const unique = [...new Set(offsets)];
		const html = unique.length
			? unique.map((offset) => '<span class="wbdpp-reminder-chip">' + (offset === 0 ? 'Due date' : offset + ' day(s) before') + '</span>').join('')
			: '<span class="wbdpp-reminder-chip">No valid reminders</span>';

		$('#' + wrapId).html(html);
	};

	$(document).ready(() => {
		$('.subsubsub').each(function () {
			this.innerHTML = this.innerHTML.replace(/\s*\|\s*/g, ' ');
		});

		refreshVisibility();
		injectDepositMessagePreview();
		injectReminderChips();
		$(document).on('change', '#wbdpp_default_due_type, #wbdpp_enable_booking_due, #wbdpp_deposit_refund_policy, #wbdpp_auto_cancel_overdue_enabled', refreshVisibility);
		$(document).on('input change', '#wbdpp_ui_deposit_message', injectDepositMessagePreview);
		$(document).on('input change', '#wbdpp_reminder_offsets', injectReminderChips);

		$(document).on('input change', 'table.form-table :input', () => {
			saveButton.addClass('wbdpp-is-dirty');
		});

		$('.woocommerce form').on('submit', () => {
			saveButton.removeClass('wbdpp-is-dirty');
		});
	});
})(jQuery);

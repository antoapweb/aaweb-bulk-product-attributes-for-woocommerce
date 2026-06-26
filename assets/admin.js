document.addEventListener('DOMContentLoaded', function() {
	const checkAll = document.getElementById('aaweb-check-all');
	const selectedCount = document.getElementById('aaweb-selected-count');
	const productBoxes = Array.from(document.querySelectorAll('input[name="product_ids[]"]'));
	const realModeRadios = Array.from(document.querySelectorAll('input[name="aaweb_assign_mode"]'));
	const stickyModeRadios = Array.from(document.querySelectorAll('input[name="aaweb_mode_shadow"]'));

	function updateCounter() {
		const count = productBoxes.filter(function(checkbox) { return checkbox.checked; }).length;

		if (selectedCount) {
			selectedCount.textContent = count;
		}

		if (checkAll) {
			checkAll.checked = productBoxes.length > 0 && count === productBoxes.length;
			checkAll.indeterminate = count > 0 && count < productBoxes.length;
		}
	}

	if (checkAll) {
		checkAll.addEventListener('change', function() {
			productBoxes.forEach(function(checkbox) {
				checkbox.checked = checkAll.checked;
			});
			updateCounter();
		});
	}

	productBoxes.forEach(function(checkbox) {
		checkbox.addEventListener('change', updateCounter);
	});

	stickyModeRadios.forEach(function(radio) {
		radio.addEventListener('change', function() {
			realModeRadios.forEach(function(realRadio) {
				realRadio.checked = realRadio.value === radio.value;
			});
		});
	});

	realModeRadios.forEach(function(radio) {
		radio.addEventListener('change', function() {
			stickyModeRadios.forEach(function(stickyRadio) {
				stickyRadio.checked = stickyRadio.value === radio.value;
			});
		});
	});

	document.querySelectorAll('[data-aaweb-attr-panel]').forEach(function(panel) {
		const search = panel.querySelector('.aaweb-attr-search');
		const options = Array.from(panel.querySelectorAll('.aaweb-term-option'));
		const selectVisible = panel.querySelector('[data-aaweb-select-visible]');
		const clearPanel = panel.querySelector('[data-aaweb-clear-panel]');

		if (search) {
			search.addEventListener('input', function() {
				const value = search.value.toLowerCase().trim();

				options.forEach(function(option) {
					const text = option.textContent.toLowerCase();
					option.style.display = text.indexOf(value) !== -1 ? '' : 'none';
				});
			});
		}

		if (selectVisible) {
			selectVisible.addEventListener('click', function() {
				options.forEach(function(option) {
					if (option.style.display !== 'none') {
						const input = option.querySelector('input[type="checkbox"]');
						if (input) {
							input.checked = true;
						}
					}
				});
			});
		}

		if (clearPanel) {
			clearPanel.addEventListener('click', function() {
				options.forEach(function(option) {
					const input = option.querySelector('input[type="checkbox"]');
					if (input) {
						input.checked = false;
					}
				});
			});
		}
	});

	updateCounter();
});

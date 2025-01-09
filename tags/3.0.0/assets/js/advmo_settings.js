addEventListener("DOMContentLoaded", function () {
	// Select the cloud provider dropdown
	const cloudProviderSelect = document.querySelector(
		'select[name="advmo_settings[cloud_provider]"]',
	);

	if (!cloudProviderSelect) {
		console.error("Cloud provider select not found");
		return;
	}

	// Select the form (assuming it's the parent form of the select field)
	const form = cloudProviderSelect.closest("form");

	if (!form) {
		console.error("Parent form not found");
		return;
	}

	// Add event listener to the select field
	cloudProviderSelect.addEventListener("change", function (e) {
		// Look for a submit button
		const submitButton = form.querySelector(
			'input[type="submit"], button[type="submit"]',
		);

		if (submitButton) {
			// If found, click the submit button
			submitButton.click();
		} else {
			// If no submit button found, dispatch a submit event
			const submitEvent = new Event("submit", {
				bubbles: true,
				cancelable: true,
			});
			form.dispatchEvent(submitEvent);
		}
	});

	const advmo_test_connection = document.querySelector(
		".advmo_js_test_connection",
	);

	if (advmo_test_connection) {
		advmo_test_connection.addEventListener("click", function (e) {
			e.preventDefault();

			// Save the original text and disable the link
			const originalText = advmo_test_connection.textContent;
			advmo_test_connection.textContent = "Loading...";
			advmo_test_connection.disabled = true;

			const data = {
				action: "advmo_test_connection",
				security_nonce: advmo_ajax_object.nonce,
			};

			fetch(advmo_ajax_object.ajax_url, {
				method: "POST",
				headers: {
					"Content-Type": "application/x-www-form-urlencoded",
				},
				body: new URLSearchParams(data),
			})
				.then((response) => response.json())
				.then((data) => {
					// Restore the original text and re-enable the link
					advmo_test_connection.textContent = originalText;
					advmo_test_connection.disabled = false;
					const success_message = document.querySelector(
						".advmo-test-success",
					);

					const error_message =
						document.querySelector(".advmo-test-error");

					if (data.success) {
						success_message.style.display = "block";
						error_message.style.display = "none";
					} else {
						error_message.style.display = "block";
						success_message.style.display = "none";
					}
				})
				.catch((error) => {
					// Restore the original text and re-enable the link on error
					advmo_test_connection.textContent = originalText;
					advmo_test_connection.disabled = false;
					alert("Error: " + error.message);
				});
		});
	}

	// Enable Path Prefix input if checkbox was enabled
	var pathPrefixCheckbox = document.getElementById("path_prefix_active");
	var pathPrefixInput = document.getElementById("path_prefix");

	if (pathPrefixCheckbox && pathPrefixInput) {
		pathPrefixCheckbox.addEventListener("change", function () {
			pathPrefixInput.disabled = !this.checked;
		});
	}
});

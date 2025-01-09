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

	// Bulk Offload Ajax
	const startButton = document.getElementById("bulk-offload-button");
	const progressContainer = document.getElementById("progress-container");
	const progressBar = document.getElementById("offload-progress");
	const progressText = document.getElementById("progress-text");
	const processedCount = document.getElementById("processed-count");
	const totalCount = document.getElementById("total-count");
	const messageContainer = document.createElement("div");
	messageContainer.id = "advmo-message-container";
	progressContainer.parentNode.insertBefore(
		messageContainer,
		progressContainer,
	);

	function showMessage(message, isError = false) {
		messageContainer.textContent = message;
		messageContainer.className = isError
			? "error-message"
			: "success-message";
		messageContainer.style.display = "block";
		setTimeout(() => {
			messageContainer.style.display = "none";
		}, 5000);
	}

	function startBulkOffload() {
		startButton.disabled = true;
		progressContainer.style.display = "block";

		const formData = new FormData();
		formData.append("action", "advmo_start_bulk_offload");
		formData.append("bulk_offload_nonce", advmo_ajax_object.nonce);
		formData.append("batch_size", 250); // You can make this configurable if needed

		fetch(advmo_ajax_object.ajax_url, {
			method: "POST",
			credentials: "same-origin",
			body: formData,
		})
			.then((response) => response.json())
			.then((data) => {
				if (data.success) {
					checkProgress();
				} else {
					showMessage(
						"Failed to start bulk offload process: " + data.data,
						true,
					);
					startButton.disabled = false;
				}
			})
			.catch((error) => {
				console.error("Error:", error);
				showMessage(
					"An error occurred while starting the bulk offload process",
					true,
				);
				startButton.disabled = false;
			});
	}

	function checkProgress() {
		const formData = new FormData();
		formData.append("action", "advmo_check_bulk_offload_progress");
		formData.append(
			"bulk_offload_nonce",
			advmo_ajax_object.bulk_offload_nonce,
		);

		fetch(advmo_ajax_object.ajax_url, {
			method: "POST",
			credentials: "same-origin",
			body: formData,
		})
			.then((response) => response.json())
			.then((data) => {
				if (data.success) {
					updateProgressUI(data.data);
				} else {
					showMessage("Failed to check progress: " + data.data, true);
				}
			})
			.catch((error) => {
				console.error("Error:", error);
				showMessage(
					"An error occurred while checking the progress",
					true,
				);
			});
	}

	let progressCheckInterval;

	function updateProgressUI(progressData) {
		const processed = parseInt(progressData.processed);
		const total = parseInt(progressData.total);
		const progress =
			processed !== 0 && total !== 0 ? (processed / total) * 100 : 0;

		progressBar.style.width = progress + "%";
		progressText.textContent = Math.round(progress) + "%";

		// Update the processed and total counts
		processedCount.textContent = processed;
		totalCount.textContent = total;

		if (total === processed && total !== 0) {
			progressText.textContent = "Offload complete!";
			startButton.disabled = false;
			showMessage("Bulk offload process completed successfully!");
			clearTimeout(progressCheckInterval); // Clear the interval
		} else if (total === 0) {
			progressText.textContent = "No files to offload";
			startButton.disabled = false;
			progressContainer.style.display = "none";
			showMessage("No files to offload");
		} else {
			progressCheckInterval = setTimeout(checkProgress, 5000); // Check again in 5 seconds
		}
	}

	// Check status on page load
	if (progressContainer.dataset.status === "processing") {
		if (startButton) {
			startButton.disabled = true;
		}
		progressContainer.style.display = "block";
		checkProgress();
	}

	// Add click event listener to start button
	if (startButton) {
		startButton.addEventListener("click", startBulkOffload);
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

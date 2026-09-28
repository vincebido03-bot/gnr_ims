document.addEventListener("DOMContentLoaded", function () {
	const modal = document.getElementById("jobDetailsModal");
	const frame = document.getElementById("jobDetailsFrame");
	const previousButton = document.getElementById("jobDetailsPrevious");
	const nextButton = document.getElementById("jobDetailsNext");
	const positionLabel = document.getElementById("jobDetailsPosition");
	let customerJobs = [];
	let customerJobIndex = -1;
	let previousFocus = null;

	const detailLinks = Array.from(document.querySelectorAll("[data-job-detail-open]"));
	if (!modal || !frame || !previousButton || !nextButton || !positionLabel || !detailLinks.length) {
		return;
	}

	function renderCurrentJob() {
		if (customerJobIndex < 0 || customerJobIndex >= customerJobs.length) {
			return;
		}

		const currentJob = customerJobs[customerJobIndex];
		frame.src = "job-details.php?job_id=" + encodeURIComponent(currentJob.id) + "&embedded=1";
		positionLabel.textContent = "Job " + (customerJobIndex + 1) + " / " + customerJobs.length;
		previousButton.disabled = customerJobIndex === 0;
		nextButton.disabled = customerJobIndex === customerJobs.length - 1;
	}

	function openForJob(selectedJob) {
		try {
			customerJobs = JSON.parse(selectedJob.dataset.customerJobs || "[]");
		} catch (error) {
			customerJobs = [];
		}
		if (!customerJobs.length) {
			customerJobs = [{ id: selectedJob.dataset.jobId }];
		}
		customerJobIndex = customerJobs.findIndex(function (job) {
			return String(job.id) === selectedJob.dataset.jobId;
		});
		if (customerJobIndex < 0) {
			return;
		}
		previousFocus = document.activeElement;
		renderCurrentJob();
		modal.classList.add("active");
		modal.setAttribute("aria-hidden", "false");
		document.body.classList.add("job-details-modal-open");
		modal.querySelector(".job-details-modal-close")?.focus();
	}

	function closeModal() {
		modal.classList.remove("active");
		modal.setAttribute("aria-hidden", "true");
		document.body.classList.remove("job-details-modal-open");
		frame.src = "about:blank";
		if (previousFocus instanceof HTMLElement) {
			previousFocus.focus();
		}
	}

	detailLinks.forEach(function (link) {
		link.addEventListener("click", function (event) {
			event.preventDefault();
			openForJob(link);
		});
	});

	previousButton.addEventListener("click", function () {
		if (customerJobIndex > 0) {
			customerJobIndex--;
			renderCurrentJob();
		}
	});

	nextButton.addEventListener("click", function () {
		if (customerJobIndex < customerJobs.length - 1) {
			customerJobIndex++;
			renderCurrentJob();
		}
	});

	modal.querySelectorAll("[data-job-details-close]").forEach(function (control) {
		control.addEventListener("click", closeModal);
	});

	document.addEventListener("keydown", function (event) {
		if (modal.classList.contains("active") && event.key === "Escape") {
			event.preventDefault();
			closeModal();
		}
	});

	window.addEventListener("message", function (event) {
		if (event.origin === window.location.origin && event.data?.type === "close-job-details") {
			closeModal();
		}
	});
});

function replaceDocument(html) {
  document.open();
  document.write(html);
  document.close();
}

function setProgressState(progressRoot, isActive) {
  if (!progressRoot) {
    return;
  }

  progressRoot.classList.toggle("is-active", isActive);
}

function updateProgress(progressRoot, percentage) {
  if (!progressRoot) {
    return;
  }

  const progressBar = progressRoot.querySelector("[data-upload-progress-bar]");
  const progressLabel = progressRoot.querySelector("[data-upload-progress-label]");

  if (progressBar) {
    progressBar.value = percentage;
  }

  if (progressLabel) {
    progressLabel.textContent = `Upload: ${percentage}%`;
  }
}

function showUploadError(progressRoot, message) {
  if (!progressRoot) {
    return;
  }

  const errorElement = progressRoot.querySelector("[data-upload-progress-error]");

  if (!errorElement) {
    return;
  }

  errorElement.textContent = message;
  errorElement.hidden = false;
}

function resetUploadError(progressRoot) {
  if (!progressRoot) {
    return;
  }

  const errorElement = progressRoot.querySelector("[data-upload-progress-error]");

  if (!errorElement) {
    return;
  }

  errorElement.textContent = "";
  errorElement.hidden = true;
}

function bindUploadProgress(form) {
  if (form.dataset.uploadProgressBound === "1") {
    return;
  }

  form.dataset.uploadProgressBound = "1";

  form.addEventListener("submit", (event) => {
    if (form.dataset.uploadInProgress === "1") {
      event.preventDefault();

      return;
    }

    const fileInput = form.querySelector('input[type="file"]');

    // Fallback to normal form submit when no file input is present.
    if (!fileInput) {
      return;
    }

    // Preserve server-side validation flow when no file is selected.
    if (!(fileInput instanceof HTMLInputElement) || !fileInput.files || fileInput.files.length === 0) {
      return;
    }

    event.preventDefault();

    const progressRoot = form.querySelector("[data-upload-progress]");

    form.dataset.uploadInProgress = "1";
    setProgressState(progressRoot, true);
    resetUploadError(progressRoot);
    updateProgress(progressRoot, 0);

    const request = new XMLHttpRequest();
    request.open((form.method || "POST").toUpperCase(), form.action, true);
    request.responseType = "text";
    request.setRequestHeader("X-Requested-With", "XMLHttpRequest");

    request.upload.addEventListener("progress", (progressEvent) => {
      if (!progressEvent.lengthComputable) {
        return;
      }

      const percentage = Math.min(100, Math.round((progressEvent.loaded / progressEvent.total) * 100));
      updateProgress(progressRoot, percentage);
    });

    request.addEventListener("load", () => {
      form.dataset.uploadInProgress = "0";

      const responseText = typeof request.responseText === "string" ? request.responseText : "";
      if (responseText.trim() !== "") {
        replaceDocument(responseText);

        return;
      }

      if (request.status >= 200 && request.status < 400) {
        window.location.reload();

        return;
      }

      setProgressState(progressRoot, false);
      showUploadError(progressRoot, "Upload fehlgeschlagen. Bitte versuche es erneut.");
    });

    request.addEventListener("error", () => {
      form.dataset.uploadInProgress = "0";
      setProgressState(progressRoot, false);
      showUploadError(progressRoot, "Upload fehlgeschlagen. Bitte pruefe deine Verbindung.");
    });

    request.send(new FormData(form));
  });
}

export default function initUploadProgress() {
  const uploadForms = document.querySelectorAll('form[data-upload-progress-form="true"]');

  uploadForms.forEach((form) => {
    bindUploadProgress(form);
  });
}
